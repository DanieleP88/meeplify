<?php

class TagHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        if (empty($action) || is_numeric($action)) {
            // Handle tag CRUD operations
            if ($method === 'GET' && empty($action)) {
                self::getTags($user_id);
            } elseif ($method === 'POST' && empty($action)) {
                self::createTag($user_id);
            } elseif ($method === 'PUT' && is_numeric($action)) {
                self::updateTag($user_id, $action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                self::deleteTag($user_id, $action);
            } else {
                sendJson(false, null, ['Method not allowed'], 405);
            }
        } else {
            // Handle special actions
            switch ($action) {
                case 'assign':
                    if ($method === 'POST') {
                        self::assignTag($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                case 'unassign':
                    if ($method === 'POST') {
                        self::unassignTag($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                case 'filter':
                    if ($method === 'GET') {
                        self::filterByTags($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                default:
                    sendJson(false, null, ['Invalid action'], 404);
            }
        }
    }

    private static function getTags($user_id) {
        $checklist_id = (int)($_GET['checklist_id'] ?? 0);
        
        if ($checklist_id && validateId($checklist_id)) {
            // Get tags for specific checklist
            checkPermission($checklist_id, $user_id, 'viewer');
            self::getChecklistTags($checklist_id);
        } else {
            // Get all user's tags
            self::getUserTags($user_id);
        }
    }

    private static function getUserTags($user_id) {
        $pdo = DB::getPDO();
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT t.id, t.name, t.emoji, t.color, t.created_at,
                   COUNT(DISTINCT it.item_id) as usage_count
            FROM tags t
            LEFT JOIN item_tags it ON t.id = it.tag_id
            LEFT JOIN items i ON it.item_id = i.id
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN checklists c ON s.checklist_id = c.id
            WHERE t.user_id = ? AND (c.deleted_at IS NULL OR c.deleted_at IS NOT NULL)
            GROUP BY t.id
            ORDER BY usage_count DESC, t.name ASC
        ");
        $stmt->execute([$user_id]);
        $tags = $stmt->fetchAll();

        sendJson(true, ['tags' => $tags]);
    }

    private static function getChecklistTags($checklist_id) {
        $pdo = DB::getPDO();
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT t.id, t.name, t.emoji, t.color, t.created_at,
                   COUNT(DISTINCT it.item_id) as usage_count
            FROM tags t
            JOIN item_tags it ON t.id = it.tag_id
            JOIN items i ON it.item_id = i.id
            JOIN sections s ON i.section_id = s.id
            WHERE s.checklist_id = ?
            GROUP BY t.id
            ORDER BY usage_count DESC, t.name ASC
        ");
        $stmt->execute([$checklist_id]);
        $tags = $stmt->fetchAll();

        sendJson(true, ['tags' => $tags]);
    }

    private static function createTag($user_id) {
        $input = getInput();
        
        $name = sanitizeString($input['name'] ?? '', 50);
        $emoji = sanitizeString($input['emoji'] ?? '🏷️', 10);
        $color = sanitizeString($input['color'] ?? '#gray', 20);
        
        if (empty($name)) {
            sendJson(false, null, ['Tag name is required'], 400);
        }

        // Validate color (should be a valid CSS color or hex)
        if (!preg_match('/^(#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3}|\w+)$/', $color)) {
            sendJson(false, null, ['Invalid color format'], 400);
        }

        rateLimit("create_tag_$user_id", 20, 300); // 20 per 5 minutes

        $pdo = DB::getPDO();

        // Check if user already has a tag with this name
        $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ?");
        $stmt->execute([$user_id, $name]);
        
        if ($stmt->fetchColumn()) {
            sendJson(false, null, ['Tag name already exists'], 400);
        }

        // Check tag limit (50 per user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tags WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();

        if ($count >= 50) {
            sendJson(false, null, ['Maximum tags limit reached (50)'], 400);
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO tags (user_id, name, emoji, color, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $name, $emoji, $color]);
            $tag_id = $pdo->lastInsertId();

            logAudit('TAG_CREATED', [
                'tag_id' => $tag_id,
                'name' => $name,
                'emoji' => $emoji,
                'color' => $color
            ], $user_id);

            sendJson(true, [
                'id' => (int)$tag_id,
                'name' => $name,
                'emoji' => $emoji,
                'color' => $color,
                'usage_count' => 0
            ]);

        } catch (PDOException $e) {
            logMessage('ERROR', "Failed to create tag: " . $e->getMessage());
            sendJson(false, null, ['Failed to create tag'], 500);
        }
    }

    private static function updateTag($user_id, $tag_id) {
        if (!validateId($tag_id)) {
            sendJson(false, null, ['Invalid tag ID'], 400);
        }

        $input = getInput();
        $name = sanitizeString($input['name'] ?? '', 50);
        $emoji = sanitizeString($input['emoji'] ?? '', 10);
        $color = sanitizeString($input['color'] ?? '', 20);
        
        if (empty($name)) {
            sendJson(false, null, ['Tag name is required'], 400);
        }

        if (!preg_match('/^(#[0-9a-fA-F]{6}|#[0-9a-fA-F]{3}|\w+)$/', $color)) {
            sendJson(false, null, ['Invalid color format'], 400);
        }

        $pdo = DB::getPDO();
        
        // Check if tag belongs to user
        $stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ? AND user_id = ?");
        $stmt->execute([$tag_id, $user_id]);
        $old_name = $stmt->fetchColumn();

        if (!$old_name) {
            sendJson(false, null, ['Tag not found'], 404);
        }

        // Check for duplicate name (excluding current tag)
        if ($name !== $old_name) {
            $stmt = $pdo->prepare("SELECT id FROM tags WHERE user_id = ? AND name = ? AND id != ?");
            $stmt->execute([$user_id, $name, $tag_id]);
            
            if ($stmt->fetchColumn()) {
                sendJson(false, null, ['Tag name already exists'], 400);
            }
        }

        try {
            $stmt = $pdo->prepare("UPDATE tags SET name = ?, emoji = ?, color = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $emoji, $color, $tag_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                logAudit('TAG_UPDATED', [
                    'tag_id' => $tag_id,
                    'old_name' => $old_name,
                    'new_name' => $name,
                    'emoji' => $emoji,
                    'color' => $color
                ], $user_id);

                sendJson(true, ['message' => 'Tag updated successfully']);
            } else {
                sendJson(false, null, ['Tag not found'], 404);
            }

        } catch (PDOException $e) {
            logMessage('ERROR', "Failed to update tag: " . $e->getMessage());
            sendJson(false, null, ['Failed to update tag'], 500);
        }
    }

    private static function deleteTag($user_id, $tag_id) {
        if (!validateId($tag_id)) {
            sendJson(false, null, ['Invalid tag ID'], 400);
        }

        $pdo = DB::getPDO();
        
        // Check if tag belongs to user
        $stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ? AND user_id = ?");
        $stmt->execute([$tag_id, $user_id]);
        $tag_name = $stmt->fetchColumn();

        if (!$tag_name) {
            sendJson(false, null, ['Tag not found'], 404);
        }

        try {
            $pdo->beginTransaction();

            // Remove all tag assignments
            $stmt = $pdo->prepare("DELETE FROM item_tags WHERE tag_id = ?");
            $stmt->execute([$tag_id]);
            $removed_assignments = $stmt->rowCount();

            // Delete the tag
            $stmt = $pdo->prepare("DELETE FROM tags WHERE id = ? AND user_id = ?");
            $stmt->execute([$tag_id, $user_id]);

            $pdo->commit();

            logAudit('TAG_DELETED', [
                'tag_id' => $tag_id,
                'name' => $tag_name,
                'removed_assignments' => $removed_assignments
            ], $user_id);

            sendJson(true, [
                'message' => 'Tag deleted successfully',
                'removed_assignments' => $removed_assignments
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to delete tag: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete tag'], 500);
        }
    }

    private static function assignTag($user_id) {
        $input = getInput();
        
        $item_id = (int)($input['item_id'] ?? 0);
        $tag_id = (int)($input['tag_id'] ?? 0);
        
        if (!validateId($item_id) || !validateId($tag_id)) {
            sendJson(false, null, ['Item ID and Tag ID are required'], 400);
        }

        $pdo = DB::getPDO();

        // Check if item exists and user has permission
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

        // Check if tag belongs to user
        $stmt = $pdo->prepare("SELECT name FROM tags WHERE id = ? AND user_id = ?");
        $stmt->execute([$tag_id, $user_id]);
        $tag_name = $stmt->fetchColumn();

        if (!$tag_name) {
            sendJson(false, null, ['Tag not found'], 404);
        }

        // Check if already assigned
        $stmt = $pdo->prepare("SELECT id FROM item_tags WHERE item_id = ? AND tag_id = ?");
        $stmt->execute([$item_id, $tag_id]);
        
        if ($stmt->fetchColumn()) {
            sendJson(false, null, ['Tag already assigned to this item'], 400);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO item_tags (item_id, tag_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$item_id, $tag_id]);

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            logAudit('TAG_ASSIGNED', [
                'item_id' => $item_id,
                'tag_id' => $tag_id,
                'tag_name' => $tag_name,
                'checklist_id' => $checklist_id
            ], $user_id);

            sendJson(true, ['message' => 'Tag assigned successfully']);

        } catch (PDOException $e) {
            logMessage('ERROR', "Failed to assign tag: " . $e->getMessage());
            sendJson(false, null, ['Failed to assign tag'], 500);
        }
    }

    private static function unassignTag($user_id) {
        $input = getInput();
        
        $item_id = (int)($input['item_id'] ?? 0);
        $tag_id = (int)($input['tag_id'] ?? 0);
        
        if (!validateId($item_id) || !validateId($tag_id)) {
            sendJson(false, null, ['Item ID and Tag ID are required'], 400);
        }

        $pdo = DB::getPDO();

        // Check if item exists and user has permission
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

        // Check if tag assignment exists
        $stmt = $pdo->prepare("
            SELECT it.id, t.name as tag_name 
            FROM item_tags it
            JOIN tags t ON it.tag_id = t.id
            WHERE it.item_id = ? AND it.tag_id = ? AND t.user_id = ?
        ");
        $stmt->execute([$item_id, $tag_id, $user_id]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            sendJson(false, null, ['Tag assignment not found'], 404);
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM item_tags WHERE item_id = ? AND tag_id = ?");
            $stmt->execute([$item_id, $tag_id]);

            // Update checklist updated_at
            $stmt = $pdo->prepare("UPDATE checklists SET updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklist_id]);

            logAudit('TAG_UNASSIGNED', [
                'item_id' => $item_id,
                'tag_id' => $tag_id,
                'tag_name' => $assignment['tag_name'],
                'checklist_id' => $checklist_id
            ], $user_id);

            sendJson(true, ['message' => 'Tag unassigned successfully']);

        } catch (PDOException $e) {
            logMessage('ERROR', "Failed to unassign tag: " . $e->getMessage());
            sendJson(false, null, ['Failed to unassign tag'], 500);
        }
    }

    private static function filterByTags($user_id) {
        $checklist_id = (int)($_GET['checklist_id'] ?? 0);
        $tag_ids = $_GET['tag_ids'] ?? '';
        
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Checklist ID is required'], 400);
        }

        checkPermission($checklist_id, $user_id, 'viewer');

        if (empty($tag_ids)) {
            sendJson(false, null, ['Tag IDs are required'], 400);
        }

        // Parse tag IDs
        $tag_id_array = array_filter(array_map('intval', explode(',', $tag_ids)));
        
        if (empty($tag_id_array)) {
            sendJson(false, null, ['Valid tag IDs are required'], 400);
        }

        $pdo = DB::getPDO();

        // Get items that have any of the specified tags
        $placeholders = str_repeat('?,', count($tag_id_array) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT DISTINCT i.id, i.text, i.completed, i.order_pos,
                   s.id as section_id, s.name as section_name,
                   GROUP_CONCAT(CONCAT(t.id, ':', t.name, ':', t.emoji, ':', t.color) SEPARATOR '|') as item_tags
            FROM items i
            JOIN sections s ON i.section_id = s.id
            JOIN item_tags it ON i.id = it.item_id
            JOIN tags t ON it.tag_id = t.id
            WHERE s.checklist_id = ? AND it.tag_id IN ($placeholders) AND t.user_id = ?
            GROUP BY i.id
            ORDER BY s.order_pos ASC, i.order_pos ASC
        ");
        
        $params = array_merge([$checklist_id], $tag_id_array, [$user_id]);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        // Process items and organize by section
        $sections = [];
        foreach ($items as $item) {
            $section_id = $item['section_id'];
            
            if (!isset($sections[$section_id])) {
                $sections[$section_id] = [
                    'id' => (int)$section_id,
                    'name' => $item['section_name'],
                    'items' => []
                ];
            }

            // Parse tags
            $tags = [];
            if ($item['item_tags']) {
                foreach (explode('|', $item['item_tags']) as $tag_data) {
                    $tag_parts = explode(':', $tag_data);
                    if (count($tag_parts) >= 4) {
                        $tags[] = [
                            'id' => (int)$tag_parts[0],
                            'name' => $tag_parts[1],
                            'emoji' => $tag_parts[2],
                            'color' => $tag_parts[3]
                        ];
                    }
                }
            }

            $sections[$section_id]['items'][] = [
                'id' => (int)$item['id'],
                'text' => $item['text'],
                'completed' => (bool)$item['completed'],
                'order_pos' => (int)$item['order_pos'],
                'tags' => $tags
            ];
        }

        sendJson(true, [
            'sections' => array_values($sections),
            'total_items' => count($items)
        ]);
    }
}

?>