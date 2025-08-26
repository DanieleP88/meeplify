<?php

class ChecklistHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        if (empty($action) || is_numeric($action)) {
            // Handle checklist CRUD operations
            if ($method === 'GET' && empty($action)) {
                self::getChecklists($user_id);
            } elseif ($method === 'POST' && empty($action)) {
                self::createChecklist($user_id);
            } elseif ($method === 'GET' && is_numeric($action)) {
                self::getChecklist($user_id, $action);
            } elseif ($method === 'PUT' && is_numeric($action)) {
                self::updateChecklist($user_id, $action);
            } elseif ($method === 'DELETE' && is_numeric($action)) {
                self::deleteChecklist($user_id, $action);
            } else {
                sendJson(false, null, ['Method not allowed'], 405);
            }
        } else {
            // Handle special actions
            switch ($action) {
                case 'shared':
                    self::getSharedChecklists($user_id, $method);
                    break;
                case 'trash':
                    self::getTrashChecklists($user_id, $method);
                    break;
                case 'restore':
                    if ($method === 'POST' && isset($uri_parts[2])) {
                        self::restoreChecklist($user_id, $uri_parts[2]);
                    } else {
                        sendJson(false, null, ['Invalid restore request'], 400);
                    }
                    break;
                case 'export':
                    if ($method === 'GET' && isset($uri_parts[2])) {
                        self::exportChecklist($user_id, $uri_parts[2]);
                    } else {
                        sendJson(false, null, ['Invalid export request'], 400);
                    }
                    break;
                case 'import':
                    if ($method === 'POST') {
                        self::importChecklist($user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                default:
                    sendJson(false, null, ['Invalid action'], 404);
            }
        }
    }

    private static function getChecklists($user_id) {
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $search = sanitizeString($_GET['search'] ?? '', 100);
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $pdo = DB::getPDO();
        
        $searchCondition = '';
        $params = [$user_id];
        
        if (!empty($search)) {
            $searchCondition = ' AND (c.title LIKE ? OR c.description LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.created_at, c.updated_at,
                   COUNT(DISTINCT s.id) as section_count,
                   COUNT(DISTINCT i.id) as item_count,
                   COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) as completed_count
            FROM checklists c
            LEFT JOIN sections s ON c.id = s.checklist_id
            LEFT JOIN items i ON c.id = i.checklist_id
            WHERE c.owner_id = ? AND c.deleted_at IS NULL $searchCondition
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        
        $stmt->execute($params);
        $checklists = $stmt->fetchAll();

        // Calculate progress for each checklist
        foreach ($checklists as &$checklist) {
            $total_items = (int)$checklist['item_count'];
            $completed_items = (int)$checklist['completed_count'];
            $checklist['progress'] = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
        }

        // Get total count for pagination
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM checklists c 
            WHERE c.owner_id = ? AND c.deleted_at IS NULL $searchCondition
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

            sendJson(true, [
                'checklists' => $checklists,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (Exception $e) {
            error_log("ChecklistHandler::getChecklists error: " . $e->getMessage());
            error_log("User ID: $user_id");
            error_log("Stack trace: " . $e->getTraceAsString());
            sendJson(false, null, ['Database error: ' . $e->getMessage()], 500);
        }
    }

    private static function createChecklist($user_id) {
        $input = getInput();
        
        $title = sanitizeString($input['title'] ?? '', 255);
        $description = sanitizeString($input['description'] ?? '', 1000);

        if (empty($title)) {
            sendJson(false, null, ['Title is required'], 400);
        }

        // Check limits
        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM checklists WHERE owner_id = ? AND deleted_at IS NULL");
        $stmt->execute([$user_id]);
        $count = $stmt->fetchColumn();

        if ($count >= 100) { // Limit to 100 checklists per user
            sendJson(false, null, ['Maximum checklist limit reached'], 400);
        }

        rateLimit("create_checklist_$user_id", 10, 300); // 10 per 5 minutes

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO checklists (title, description, owner_id, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $description, $user_id]);
            $checklist_id = $pdo->lastInsertId();

            $pdo->commit();

            logAudit('CHECKLIST_CREATED', [
                'checklist_id' => $checklist_id,
                'title' => $title
            ], $user_id);

            sendJson(true, ['id' => (int)$checklist_id]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to create checklist: " . $e->getMessage());
            sendJson(false, null, ['Failed to create checklist'], 500);
        }
    }

    private static function getChecklist($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        checkPermission($checklist_id, $user_id, 'viewer');

        $pdo = DB::getPDO();
        
        // Get checklist details
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as owner_name, u.email as owner_email
            FROM checklists c
            JOIN users u ON c.owner_id = u.id
            WHERE c.id = ? AND c.deleted_at IS NULL
        ");
        $stmt->execute([$checklist_id]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            sendJson(false, null, ['Checklist not found'], 404);
        }

        // Get sections with items
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.order_pos,
                   i.id as item_id, i.text as item_text, i.completed as item_completed,
                   i.order_pos as item_order, i.created_at as item_created
            FROM sections s
            LEFT JOIN items i ON s.id = i.section_id
            WHERE s.checklist_id = ?
            ORDER BY s.order_pos ASC, i.order_pos ASC
        ");
        $stmt->execute([$checklist_id]);
        $rows = $stmt->fetchAll();

        // Organize data
        $sections = [];
        foreach ($rows as $row) {
            if (!isset($sections[$row['id']])) {
                $sections[$row['id']] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'order_pos' => (int)$row['order_pos'],
                    'items' => []
                ];
            }
            
            if ($row['item_id']) {
                $sections[$row['id']]['items'][] = [
                    'id' => (int)$row['item_id'],
                    'text' => $row['item_text'],
                    'completed' => (bool)$row['item_completed'],
                    'order_pos' => (int)$row['item_order'],
                    'created_at' => $row['item_created']
                ];
            }
        }

        $checklist['sections'] = array_values($sections);

        // Get user's role
        $checklist['user_role'] = getChecklistRole($checklist_id, $user_id);

        sendJson(true, $checklist);
    }

    private static function updateChecklist($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        checkPermission($checklist_id, $user_id, 'owner');

        $input = getInput();
        $title = sanitizeString($input['title'] ?? '', 255);
        $description = sanitizeString($input['description'] ?? '', 1000);

        if (empty($title)) {
            sendJson(false, null, ['Title is required'], 400);
        }

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            UPDATE checklists 
            SET title = ?, description = ?, updated_at = NOW() 
            WHERE id = ? AND deleted_at IS NULL
        ");
        
        if ($stmt->execute([$title, $description, $checklist_id])) {
            if ($stmt->rowCount() > 0) {
                logAudit('CHECKLIST_UPDATED', [
                    'checklist_id' => $checklist_id,
                    'title' => $title
                ], $user_id);
                
                sendJson(true, ['message' => 'Checklist updated']);
            } else {
                sendJson(false, null, ['Checklist not found'], 404);
            }
        } else {
            sendJson(false, null, ['Failed to update checklist'], 500);
        }
    }

    private static function deleteChecklist($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        checkPermission($checklist_id, $user_id, 'owner');

        $pdo = DB::getPDO();
        
        // Soft delete
        $stmt = $pdo->prepare("
            UPDATE checklists 
            SET deleted_at = NOW() 
            WHERE id = ? AND deleted_at IS NULL
        ");
        
        if ($stmt->execute([$checklist_id])) {
            if ($stmt->rowCount() > 0) {
                logAudit('CHECKLIST_DELETED', ['checklist_id' => $checklist_id], $user_id);
                sendJson(true, ['message' => 'Checklist moved to trash']);
            } else {
                sendJson(false, null, ['Checklist not found'], 404);
            }
        } else {
            sendJson(false, null, ['Failed to delete checklist'], 500);
        }
    }

    private static function getSharedChecklists($user_id, $method) {
        if ($method !== 'GET') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.created_at, c.updated_at,
                   u.name as owner_name, col.role as user_role,
                   COUNT(DISTINCT s.id) as section_count,
                   COUNT(DISTINCT i.id) as item_count,
                   COUNT(DISTINCT CASE WHEN i.completed = 1 THEN i.id END) as completed_count
            FROM checklists c
            JOIN collaborators col ON c.id = col.checklist_id
            JOIN users u ON c.owner_id = u.id
            LEFT JOIN sections s ON c.id = s.checklist_id
            LEFT JOIN items i ON c.id = i.checklist_id
            WHERE col.user_id = ? AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT $limit OFFSET $offset
        ");
        
        $stmt->execute([$user_id]);
        $checklists = $stmt->fetchAll();

        foreach ($checklists as &$checklist) {
            $total_items = (int)$checklist['item_count'];
            $completed_items = (int)$checklist['completed_count'];
            $checklist['progress'] = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
        }

        sendJson(true, ['checklists' => $checklists]);
    }

    private static function getTrashChecklists($user_id, $method) {
        if ($method !== 'GET') {
            sendJson(false, null, ['Method not allowed'], 405);
        }

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            SELECT id, title, description, deleted_at
            FROM checklists 
            WHERE owner_id = ? AND deleted_at IS NOT NULL 
              AND deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY deleted_at DESC
        ");
        
        $stmt->execute([$user_id]);
        $checklists = $stmt->fetchAll();

        sendJson(true, ['checklists' => $checklists]);
    }

    private static function restoreChecklist($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        $pdo = DB::getPDO();
        
        // Check if user owns this deleted checklist and it's within recovery window
        $stmt = $pdo->prepare("
            SELECT id, title 
            FROM checklists 
            WHERE id = ? AND owner_id = ? AND deleted_at IS NOT NULL 
              AND deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$checklist_id, $user_id]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            sendJson(false, null, ['Checklist not found or cannot be restored'], 404);
        }

        $stmt = $pdo->prepare("UPDATE checklists SET deleted_at = NULL WHERE id = ?");
        
        if ($stmt->execute([$checklist_id])) {
            logAudit('CHECKLIST_RESTORED', [
                'checklist_id' => $checklist_id,
                'title' => $checklist['title']
            ], $user_id);
            
            sendJson(true, ['message' => 'Checklist restored']);
        } else {
            sendJson(false, null, ['Failed to restore checklist'], 500);
        }
    }

    private static function exportChecklist($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        checkPermission($checklist_id, $user_id, 'viewer');

        $format = $_GET['format'] ?? 'json';
        
        if (!in_array($format, ['json', 'csv'])) {
            sendJson(false, null, ['Unsupported format'], 400);
        }

        // Get full checklist data (reuse getChecklist logic)
        // This would need the full export implementation
        sendJson(true, ['message' => 'Export functionality will be implemented']);
    }

    private static function importChecklist($user_id) {
        $input = getInput();
        $data = $input['data'] ?? null;
        $format = $input['format'] ?? 'json';

        if (!$data) {
            sendJson(false, null, ['Import data required'], 400);
        }

        // Import implementation would go here
        sendJson(true, ['message' => 'Import functionality will be implemented']);
    }
}

?>