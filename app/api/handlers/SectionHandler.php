<?php

class SectionHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        if (empty($action) || is_numeric($action)) {
            // Handle section CRUD operations
            if ($method === 'POST' && empty($action)) {
                self::createSection($user_id);
            } elseif ($method === 'PUT' && is_numeric($action)) {
                self::updateSection($user_id, $action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                self::deleteSection($user_id, $action);
            } else {
                sendJson(false, null, ['Method not allowed'], 405);
            }
        } else {
            // Handle special actions
            switch ($action) {
                case 'reorder':
                    if ($method === 'POST') {
                        self::reorderSections($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                default:
                    sendJson(false, null, ['Invalid action'], 404);
            }
        }
    }

    private static function createSection($user_id) {
        $input = getInput();
        
        $checklist_id = (int)($input['checklist_id'] ?? 0);
        $name = sanitizeString($input['name'] ?? '', 255);
        
        if (!validateId($checklist_id) || empty($name)) {
            sendJson(false, null, ['Checklist ID and section name are required'], 400);
        }

        // Check permission and limits
        checkPermission($checklist_id, $user_id, 'collaborator');

        $pdo = DB::getPDO();
        
        // Check section limit (100 per checklist)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE checklist_id = ?");
        $stmt->execute([$checklist_id]);
        $count = $stmt->fetchColumn();

        if ($count >= 100) {
            sendJson(false, null, ['Maximum sections limit reached (100)'], 400);
        }

        rateLimit("create_section_$user_id", 20, 300); // 20 per 5 minutes

        try {
            $pdo->beginTransaction();

            // Get next order position
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_pos), 0) + 1 FROM sections WHERE checklist_id = ?");
            $stmt->execute([$checklist_id]);
            $order_pos = $stmt->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO sections (checklist_id, name, order_pos, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$checklist_id, $name, $order_pos]);
            $section_id = $pdo->lastInsertId();

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('SECTION_CREATED', [
                'section_id' => $section_id,
                'checklist_id' => $checklist_id,
                'name' => $name
            ], $user_id);

            sendJson(true, [
                'id' => (int)$section_id,
                'order_pos' => (int)$order_pos
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to create section: " . $e->getMessage());
            sendJson(false, null, ['Failed to create section'], 500);
        }
    }

    private static function updateSection($user_id, $section_id) {
        if (!validateId($section_id)) {
            sendJson(false, null, ['Invalid section ID'], 400);
        }

        $input = getInput();
        $name = sanitizeString($input['name'] ?? '', 255);

        if (empty($name)) {
            sendJson(false, null, ['Section name is required'], 400);
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

            $stmt = $pdo->prepare("UPDATE sections SET name = ? WHERE id = ?");
            $stmt->execute([$name, $section_id]);

            if ($stmt->rowCount() > 0) {
                // Update checklist updated_at
                $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$checklist_id]);

                $pdo->commit();

                logAudit('SECTION_UPDATED', [
                    'section_id' => $section_id,
                    'checklist_id' => $checklist_id,
                    'name' => $name
                ], $user_id);

                sendJson(true, ['message' => 'Section updated']);
            } else {
                $pdo->rollback();
                sendJson(false, null, ['Section not found'], 404);
            }

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to update section: " . $e->getMessage());
            sendJson(false, null, ['Failed to update section'], 500);
        }
    }

    private static function deleteSection($user_id, $section_id) {
        if (!validateId($section_id)) {
            sendJson(false, null, ['Invalid section ID'], 400);
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

            // Delete all items in this section
            $stmt = $pdo->prepare("DELETE FROM items WHERE section_id = ?");
            $stmt->execute([$section_id]);

            // Delete the section
            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$section_id]);

            if ($stmt->rowCount() > 0) {
                // Update checklist updated_at
                $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$checklist_id]);

                // Reorder remaining sections
                $stmt = $pdo->prepare("
                    UPDATE sections 
                    SET order_pos = (@row_number := @row_number + 1)
                    WHERE checklist_id = ?
                    ORDER BY order_pos
                ");
                $pdo->query("SET @row_number = 0");
                $stmt->execute([$checklist_id]);

                $pdo->commit();

                logAudit('SECTION_DELETED', [
                    'section_id' => $section_id,
                    'checklist_id' => $checklist_id
                ], $user_id);

                sendJson(true, ['message' => 'Section deleted']);
            } else {
                $pdo->rollback();
                sendJson(false, null, ['Section not found'], 404);
            }

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to delete section: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete section'], 500);
        }
    }

    private static function reorderSections($user_id) {
        $input = getInput();
        $checklist_id = (int)($input['checklist_id'] ?? 0);
        $section_order = $input['section_order'] ?? [];

        if (!validateId($checklist_id) || !is_array($section_order)) {
            sendJson(false, null, ['Invalid checklist ID or section order'], 400);
        }

        checkPermission($checklist_id, $user_id, 'collaborator');

        $pdo = DB::getPDO();
        
        try {
            $pdo->beginTransaction();

            // Validate that all section IDs belong to this checklist
            $placeholders = str_repeat('?,', count($section_order) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM sections 
                WHERE checklist_id = ? AND id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$checklist_id], $section_order));
            $valid_count = $stmt->fetchColumn();

            if ($valid_count != count($section_order)) {
                throw new Exception('Invalid section IDs provided');
            }

            // Update order positions
            foreach ($section_order as $index => $section_id) {
                $stmt = $pdo->prepare("UPDATE sections SET order_pos = ? WHERE id = ? AND checklist_id = ?");
                $stmt->execute([$index + 1, $section_id, $checklist_id]);
            }

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('SECTIONS_REORDERED', [
                'checklist_id' => $checklist_id,
                'section_order' => $section_order
            ], $user_id);

            sendJson(true, ['message' => 'Sections reordered']);

        } catch (Exception $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to reorder sections: " . $e->getMessage());
            sendJson(false, null, ['Failed to reorder sections'], 500);
        }
    }
}

?>