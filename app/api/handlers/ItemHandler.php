<?php

class ItemHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        if (empty($action) || is_numeric($action)) {
            // Handle item CRUD operations
            if ($method === 'POST' && empty($action)) {
                self::createItem($user_id);
            } elseif ($method === 'PUT' && is_numeric($action)) {
                self::updateItem($user_id, $action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                self::deleteItem($user_id, $action);
            } else {
                sendJson(false, null, ['Method not allowed'], 405);
            }
        } else {
            // Handle special actions
            switch ($action) {
                case 'toggle':
                    if ($method === 'POST' && isset($uri_parts[2])) {
                        self::toggleItem($user_id, $uri_parts[2]);
                    } else {
                        sendJson(false, null, ['Invalid toggle request'], 400);
                    }
                    break;
                case 'reorder':
                    if ($method === 'POST') {
                        self::reorderItems($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                case 'bulk':
                    if ($method === 'POST') {
                        self::bulkUpdateItems($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                default:
                    sendJson(false, null, ['Invalid action'], 404);
            }
        }
    }

    private static function createItem($user_id) {
        $input = getInput();
        
        $section_id = (int)($input['section_id'] ?? 0);
        $text = sanitizeString($input['text'] ?? '', 1000);
        $checklist_id = (int)($input['checklist_id'] ?? 0);
        
        if (!validateId($section_id) || empty($text)) {
            sendJson(false, null, ['Section ID and item text are required'], 400);
        }

        $pdo = DB::getPDO();
        
        // Get checklist_id from section if not provided
        if (!$checklist_id) {
            $stmt = $pdo->prepare("SELECT checklist_id FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);
            $checklist_id = $stmt->fetchColumn();
        }

        if (!$checklist_id) {
            sendJson(false, null, ['Section not found'], 404);
        }

        // Check permission and limits
        checkPermission($checklist_id, $user_id, 'collaborator');

        // Check item limit (1000 per checklist)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM items i 
            JOIN sections s ON i.section_id = s.id 
            WHERE s.checklist_id = ?
        ");
        $stmt->execute([$checklist_id]);
        $count = $stmt->fetchColumn();

        if ($count >= 1000) {
            sendJson(false, null, ['Maximum items limit reached (1000)'], 400);
        }

        rateLimit("create_item_$user_id", 30, 300); // 30 per 5 minutes

        try {
            $pdo->beginTransaction();

            // Get next order position within the section
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_pos), 0) + 1 FROM items WHERE section_id = ?");
            $stmt->execute([$section_id]);
            $order_pos = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO items (section_id, text, completed, order_pos, created_at) 
                VALUES (?, ?, 0, ?, NOW())
            ");
            $stmt->execute([$section_id, $text, $order_pos]);
            $item_id = $pdo->lastInsertId();

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('ITEM_CREATED', [
                'item_id' => $item_id,
                'section_id' => $section_id,
                'checklist_id' => $checklist_id,
                'text' => substr($text, 0, 100) // Log first 100 chars only
            ], $user_id);

            sendJson(true, [
                'id' => (int)$item_id,
                'order_pos' => (int)$order_pos,
                'completed' => false
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to create item: " . $e->getMessage());
            sendJson(false, null, ['Failed to create item'], 500);
        }
    }

    private static function updateItem($user_id, $item_id) {
        if (!validateId($item_id)) {
            sendJson(false, null, ['Invalid item ID'], 400);
        }

        $input = getInput();
        $text = sanitizeString($input['text'] ?? '', 1000);

        if (empty($text)) {
            sendJson(false, null, ['Item text is required'], 400);
        }

        $pdo = DB::getPDO();
        
        // Get checklist_id to check permission
        $stmt = $pdo->prepare("
            SELECT s.checklist_id FROM items i 
            JOIN sections s ON i.section_id = s.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$item_id]);
        $checklist_id = $stmt->fetchColumn();

        if (!$checklist_id) {
            sendJson(false, null, ['Item not found'], 404);
        }

        checkPermission($checklist_id, $user_id, 'collaborator');

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE items SET text = ? WHERE id = ?");
            $stmt->execute([$text, $item_id]);

            if ($stmt->rowCount() > 0) {
                // Update checklist updated_at
                $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$checklist_id]);

                $pdo->commit();

                logAudit('ITEM_UPDATED', [
                    'item_id' => $item_id,
                    'checklist_id' => $checklist_id,
                    'text' => substr($text, 0, 100)
                ], $user_id);

                sendJson(true, ['message' => 'Item updated']);
            } else {
                $pdo->rollback();
                sendJson(false, null, ['Item not found'], 404);
            }

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to update item: " . $e->getMessage());
            sendJson(false, null, ['Failed to update item'], 500);
        }
    }

    private static function deleteItem($user_id, $item_id) {
        if (!validateId($item_id)) {
            sendJson(false, null, ['Invalid item ID'], 400);
        }

        $pdo = DB::getPDO();
        
        // Get checklist_id to check permission
        $stmt = $pdo->prepare("
            SELECT s.checklist_id, i.section_id FROM items i 
            JOIN sections s ON i.section_id = s.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$item_id]);
        $result = $stmt->fetch();

        if (!$result) {
            sendJson(false, null, ['Item not found'], 404);
        }

        $checklist_id = $result['checklist_id'];
        $section_id = $result['section_id'];

        checkPermission($checklist_id, $user_id, 'collaborator');

        try {
            $pdo->beginTransaction();

            // Delete the item
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$item_id]);

            if ($stmt->rowCount() > 0) {
                // Update checklist updated_at
                $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$checklist_id]);

                // Reorder remaining items in the section
                $stmt = $pdo->prepare("
                    UPDATE items 
                    SET order_pos = (@row_number := @row_number + 1)
                    WHERE section_id = ?
                    ORDER BY order_pos
                ");
                $pdo->query("SET @row_number = 0");
                $stmt->execute([$section_id]);

                $pdo->commit();

                logAudit('ITEM_DELETED', [
                    'item_id' => $item_id,
                    'section_id' => $section_id,
                    'checklist_id' => $checklist_id
                ], $user_id);

                sendJson(true, ['message' => 'Item deleted']);
            } else {
                $pdo->rollback();
                sendJson(false, null, ['Item not found'], 404);
            }

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to delete item: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete item'], 500);
        }
    }

    private static function toggleItem($user_id, $item_id) {
        if (!validateId($item_id)) {
            sendJson(false, null, ['Invalid item ID'], 400);
        }

        $pdo = DB::getPDO();
        
        // Get item details and checklist_id to check permission
        $stmt = $pdo->prepare("
            SELECT i.completed, s.checklist_id FROM items i 
            JOIN sections s ON i.section_id = s.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$item_id]);
        $result = $stmt->fetch();

        if (!$result) {
            sendJson(false, null, ['Item not found'], 404);
        }

        $checklist_id = $result['checklist_id'];
        $current_completed = (bool)$result['completed'];
        $new_completed = !$current_completed;

        checkPermission($checklist_id, $user_id, 'collaborator');

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE items SET completed = ? WHERE id = ?");
            $stmt->execute([$new_completed ? 1 : 0, $item_id]);

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('ITEM_TOGGLED', [
                'item_id' => $item_id,
                'checklist_id' => $checklist_id,
                'completed' => $new_completed
            ], $user_id);

            sendJson(true, [
                'completed' => $new_completed,
                'message' => $new_completed ? 'Item completed' : 'Item reopened'
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to toggle item: " . $e->getMessage());
            sendJson(false, null, ['Failed to toggle item'], 500);
        }
    }

    private static function reorderItems($user_id) {
        $input = getInput();
        $section_id = (int)($input['section_id'] ?? 0);
        $item_order = $input['item_order'] ?? [];

        if (!validateId($section_id) || !is_array($item_order)) {
            sendJson(false, null, ['Invalid section ID or item order'], 400);
        }

        $pdo = DB::getPDO();
        
        // Get checklist_id to check permission
        $stmt = $pdo->prepare("SELECT checklist_id FROM sections WHERE id = ?");
        $stmt->execute([$section_id]);
        $checklist_id = $stmt->fetchColumn();

        if (!$checklist_id) {
            sendJson(false, null, ['Section not found'], 404);
        }

        checkPermission($checklist_id, $user_id, 'collaborator');

        try {
            $pdo->beginTransaction();

            // Validate that all item IDs belong to this section
            if (!empty($item_order)) {
                $placeholders = str_repeat('?,', count($item_order) - 1) . '?';
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM items 
                    WHERE section_id = ? AND id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$section_id], $item_order));
                $valid_count = $stmt->fetchColumn();

                if ($valid_count != count($item_order)) {
                    throw new Exception('Invalid item IDs provided');
                }

                // Update order positions
                foreach ($item_order as $index => $item_id) {
                    $stmt = $pdo->prepare("UPDATE items SET order_pos = ? WHERE id = ? AND section_id = ?");
                    $stmt->execute([$index + 1, $item_id, $section_id]);
                }
            }

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('ITEMS_REORDERED', [
                'section_id' => $section_id,
                'checklist_id' => $checklist_id,
                'item_order' => $item_order
            ], $user_id);

            sendJson(true, ['message' => 'Items reordered']);

        } catch (Exception $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to reorder items: " . $e->getMessage());
            sendJson(false, null, ['Failed to reorder items'], 500);
        }
    }

    private static function bulkUpdateItems($user_id) {
        $input = getInput();
        $checklist_id = (int)($input['checklist_id'] ?? 0);
        $action = $input['action'] ?? '';
        $item_ids = $input['item_ids'] ?? [];

        if (!validateId($checklist_id) || !in_array($action, ['complete', 'incomplete', 'delete']) || !is_array($item_ids)) {
            sendJson(false, null, ['Invalid parameters'], 400);
        }

        if (empty($item_ids)) {
            sendJson(false, null, ['No items selected'], 400);
        }

        checkPermission($checklist_id, $user_id, 'collaborator');

        $pdo = DB::getPDO();

        try {
            $pdo->beginTransaction();

            // Validate that all item IDs belong to this checklist
            $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM items i 
                JOIN sections s ON i.section_id = s.id 
                WHERE s.checklist_id = ? AND i.id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$checklist_id], $item_ids));
            $valid_count = $stmt->fetchColumn();

            if ($valid_count != count($item_ids)) {
                throw new Exception('Some item IDs are invalid');
            }

            $affected_rows = 0;

            switch ($action) {
                case 'complete':
                    $stmt = $pdo->prepare("
                        UPDATE items SET completed = 1 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($item_ids);
                    $affected_rows = $stmt->rowCount();
                    break;

                case 'incomplete':
                    $stmt = $pdo->prepare("
                        UPDATE items SET completed = 0 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($item_ids);
                    $affected_rows = $stmt->rowCount();
                    break;

                case 'delete':
                    $stmt = $pdo->prepare("
                        DELETE FROM items 
                        WHERE id IN ($placeholders)
                    ");
                    $stmt->execute($item_ids);
                    $affected_rows = $stmt->rowCount();
                    break;
            }

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('ITEMS_BULK_UPDATE', [
                'checklist_id' => $checklist_id,
                'action' => $action,
                'item_count' => count($item_ids),
                'affected_rows' => $affected_rows
            ], $user_id);

            sendJson(true, [
                'message' => "Bulk $action completed",
                'affected_rows' => $affected_rows
            ]);

        } catch (Exception $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to bulk update items: " . $e->getMessage());
            sendJson(false, null, ['Failed to bulk update items'], 500);
        }
    }
}

?>