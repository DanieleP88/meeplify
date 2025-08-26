<?php

class AdminHandler {

    public static function getUsers() {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim($_GET['search'] ?? '');
        
        $pdo = DB::getPDO();
        
        // Build query with search filter
        $whereClause = "1=1";
        $params = [];
        
        if ($search) {
            $whereClause .= " AND (email LIKE ? OR name LIKE ?)";
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM users WHERE $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // Get users with pagination
        $query = "SELECT id, google_id, email, name, role, active, created_at, last_login,
                         (SELECT COUNT(*) FROM checklists c WHERE c.owner_id = users.id AND c.deleted_at IS NULL) as checklist_count
                  FROM users 
                  WHERE $whereClause
                  ORDER BY created_at DESC
                  LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([...$params, $limit, $offset]);
        $users = $stmt->fetchAll();
        
        $totalPages = ceil($total / $limit);
        
        sendJson(true, [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1
            ]
        ]);
    }

    public static function updateUser($userId) {
        $admin_user_id = checkAuth();
        if (!isAdmin($admin_user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($userId)) {
            sendJson(false, null, ['Invalid user ID'], 400);
        }
        
        $data = getInput();
        $pdo = DB::getPDO();
        
        // Get current user details
        $stmt = $pdo->prepare("SELECT email, name, role, active FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch();
        
        if (!$currentUser) {
            sendJson(false, null, ['User not found'], 404);
        }
        
        // Don't allow admin to demote themselves
        if ($userId == $admin_user_id && isset($data['role']) && $data['role'] !== 'admin') {
            sendJson(false, null, ['Cannot demote yourself'], 400);
        }
        
        // Don't allow admin to deactivate themselves
        if ($userId == $admin_user_id && isset($data['active']) && !$data['active']) {
            sendJson(false, null, ['Cannot deactivate yourself'], 400);
        }
        
        // Validate and prepare updates
        $updates = [];
        $params = [];
        
        if (isset($data['role'])) {
            if (!in_array($data['role'], ['user', 'admin'])) {
                sendJson(false, null, ['Role must be either "user" or "admin"'], 400);
            }
            $updates[] = 'role = ?';
            $params[] = $data['role'];
        }
        
        if (isset($data['active'])) {
            $updates[] = 'active = ?';
            $params[] = (bool)$data['active'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            sendJson(false, null, ['No valid fields to update'], 400);
        }
        
        $params[] = $userId;
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            
            $changes = [];
            if (isset($data['role']) && $data['role'] !== $currentUser['role']) {
                $changes['role'] = ['from' => $currentUser['role'], 'to' => $data['role']];
            }
            if (isset($data['active']) && (bool)$data['active'] !== (bool)$currentUser['active']) {
                $changes['active'] = ['from' => (bool)$currentUser['active'], 'to' => (bool)$data['active']];
            }
            
            logAudit('admin_user_updated', [
                'target_user_id' => $userId,
                'target_user_email' => $currentUser['email'],
                'changes' => $changes,
                'admin_user_id' => $admin_user_id
            ]);
            
            sendJson(true, ['message' => 'User updated successfully']);
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error updating user: " . $e->getMessage());
            sendJson(false, null, ['Failed to update user'], 500);
        }
    }

    public static function deleteUser($userId) {
        $admin_user_id = checkAuth();
        if (!isAdmin($admin_user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($userId)) {
            sendJson(false, null, ['Invalid user ID'], 400);
        }
        
        // Don't allow admin to delete themselves
        if ($userId == $admin_user_id) {
            sendJson(false, null, ['Cannot delete yourself'], 400);
        }
        
        $pdo = DB::getPDO();
        
        // Get user details for logging
        $stmt = $pdo->prepare("SELECT email, name, role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            sendJson(false, null, ['User not found'], 404);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get counts for logging
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM checklists WHERE owner_id = ?");
            $stmt->execute([$userId]);
            $checklistCount = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM collaborators WHERE user_id = ?");
            $stmt->execute([$userId]);
            $collaborationCount = (int)$stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tags WHERE created_by = ?");
            $stmt->execute([$userId]);
            $tagCount = (int)$stmt->fetchColumn();
            
            // Delete user's collaborations
            $stmt = $pdo->prepare("DELETE FROM collaborators WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user's tags and their associations
            $stmt = $pdo->prepare("DELETE it FROM item_tags it JOIN tags t ON it.tag_id = t.id WHERE t.created_by = ?");
            $stmt->execute([$userId]);
            $stmt = $pdo->prepare("DELETE FROM tags WHERE created_by = ?");
            $stmt->execute([$userId]);
            
            // Delete user's checklists (hard delete - admin action)
            $stmt = $pdo->prepare("DELETE it FROM item_tags it JOIN items i ON it.item_id = i.id JOIN checklists c ON i.checklist_id = c.id WHERE c.owner_id = ?");
            $stmt->execute([$userId]);
            $stmt = $pdo->prepare("DELETE i FROM items i JOIN checklists c ON i.checklist_id = c.id WHERE c.owner_id = ?");
            $stmt->execute([$userId]);
            $stmt = $pdo->prepare("DELETE s FROM sections s JOIN checklists c ON s.checklist_id = c.id WHERE c.owner_id = ?");
            $stmt->execute([$userId]);
            $stmt = $pdo->prepare("DELETE FROM collaborators WHERE checklist_id IN (SELECT id FROM checklists WHERE owner_id = ?)");
            $stmt->execute([$userId]);
            $stmt = $pdo->prepare("DELETE FROM checklists WHERE owner_id = ?");
            $stmt->execute([$userId]);
            
            // Delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            
            logAudit('admin_user_deleted', [
                'deleted_user_id' => $userId,
                'deleted_user_email' => $user['email'],
                'deleted_user_name' => $user['name'],
                'deleted_user_role' => $user['role'],
                'deleted_checklists' => $checklistCount,
                'deleted_collaborations' => $collaborationCount,
                'deleted_tags' => $tagCount,
                'admin_user_id' => $admin_user_id
            ]);
            
            sendJson(true, [
                'message' => 'User deleted successfully',
                'deleted_data' => [
                    'checklists' => $checklistCount,
                    'collaborations' => $collaborationCount,
                    'tags' => $tagCount
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            logMessage('ERROR', "Error deleting user: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete user'], 500);
        }
    }

    public static function getStats() {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        $pdo = DB::getPDO();
        
        // Basic KPIs
        $kpis = [
            'users_total' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'users_active' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'users_admin' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn(),
            'checklists_total' => (int)$pdo->query("SELECT COUNT(*) FROM checklists WHERE deleted_at IS NULL")->fetchColumn(),
            'checklists_deleted' => (int)$pdo->query("SELECT COUNT(*) FROM checklists WHERE deleted_at IS NOT NULL")->fetchColumn(),
            'items_total' => (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn(),
            'tags_total' => (int)$pdo->query("SELECT COUNT(*) FROM tags")->fetchColumn(),
            'collaborations_total' => (int)$pdo->query("SELECT COUNT(*) FROM collaborators")->fetchColumn()
        ];
        
        // Growth data for last 30 days
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM users 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ");
        $stmt->execute();
        $userGrowth = $stmt->fetchAll();
        
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM checklists 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
            GROUP BY DATE(created_at) 
            ORDER BY date ASC
        ");
        $stmt->execute();
        $checklistGrowth = $stmt->fetchAll();
        
        // Generate full date range for last 30 days
        $dates = [];
        for ($i = 29; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }
        
        // Map growth data to dates
        $userMap = [];
        foreach ($userGrowth as $row) {
            $userMap[$row['date']] = (int)$row['count'];
        }
        
        $checklistMap = [];
        foreach ($checklistGrowth as $row) {
            $checklistMap[$row['date']] = (int)$row['count'];
        }
        
        $growth = [];
        foreach ($dates as $date) {
            $growth[] = [
                'date' => $date,
                'user_signups' => $userMap[$date] ?? 0,
                'checklist_creations' => $checklistMap[$date] ?? 0
            ];
        }
        
        // Most active users
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, 
                   COUNT(DISTINCT c.id) as checklist_count,
                   COUNT(DISTINCT col.checklist_id) as collaboration_count,
                   COUNT(DISTINCT t.id) as tag_count
            FROM users u
            LEFT JOIN checklists c ON u.id = c.owner_id AND c.deleted_at IS NULL
            LEFT JOIN collaborators col ON u.id = col.user_id
            LEFT JOIN tags t ON u.id = t.created_by
            WHERE 1=1
            GROUP BY u.id, u.name, u.email
            HAVING checklist_count > 0 OR collaboration_count > 0
            ORDER BY (checklist_count + collaboration_count) DESC
            LIMIT 10
        ");
        $stmt->execute();
        $activeUsers = $stmt->fetchAll();
        
        sendJson(true, [
            'kpis' => $kpis,
            'growth' => $growth,
            'active_users' => $activeUsers
        ]);
    }

    public static function getAuditLogs() {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;
        $eventType = $_GET['event_type'] ?? '';
        $userId = $_GET['user_id'] ?? '';
        
        $pdo = DB::getPDO();
        
        // Build query with filters
        $whereClause = "1=1";
        $params = [];
        
        if ($eventType) {
            $whereClause .= " AND event_type = ?";
            $params[] = $eventType;
        }
        
        if ($userId && is_numeric($userId)) {
            $whereClause .= " AND user_id = ?";
            $params[] = (int)$userId;
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) FROM audit_log WHERE $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // Get audit logs with user details
        $query = "
            SELECT al.id, al.event_type, al.user_id, al.details, al.created_at,
                   u.name as user_name, u.email as user_email
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE $whereClause
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([...$params, $limit, $offset]);
        $auditLogs = $stmt->fetchAll();
        
        // Parse JSON details
        foreach ($auditLogs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        $totalPages = ceil($total / $limit);
        
        sendJson(true, [
            'audit_logs' => $auditLogs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1
            ]
        ]);
    }

    public static function getTemplates() {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        $pdo = DB::getPDO();
        
        // Get all templates including inactive ones
        $stmt = $pdo->prepare("
            SELECT t.id, t.name, t.description, t.category, t.difficulty_level, 
                   t.estimated_time, t.active, t.created_at, t.updated_at,
                   u.name as created_by_name,
                   (SELECT COUNT(*) FROM template_sections ts WHERE ts.template_id = t.id) as sections_count,
                   (SELECT COUNT(*) FROM template_items ti WHERE ti.template_id = t.id) as items_count
            FROM templates t
            JOIN users u ON t.created_by = u.id
            ORDER BY t.category ASC, t.name ASC
        ");
        $stmt->execute();
        $templates = $stmt->fetchAll();
        
        sendJson(true, $templates);
    }

    public static function createTemplate() {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        $data = getInput();
        
        // Validate required fields
        if (!isset($data['name']) || trim($data['name']) === '') {
            sendJson(false, null, ['Template name is required'], 400);
        }
        
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $category = trim($data['category'] ?? 'general');
        $difficultyLevel = $data['difficulty_level'] ?? 'beginner';
        $estimatedTime = $data['estimated_time'] ?? null;
        
        // Validate difficulty level
        if (!in_array($difficultyLevel, ['beginner', 'intermediate', 'advanced'])) {
            sendJson(false, null, ['Difficulty level must be beginner, intermediate, or advanced'], 400);
        }
        
        $pdo = DB::getPDO();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO templates (name, description, category, difficulty_level, estimated_time, created_by, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$name, $description, $category, $difficultyLevel, $estimatedTime, $user_id]);
            $templateId = $pdo->lastInsertId();
            
            logAudit('admin_template_created', [
                'template_id' => $templateId,
                'name' => $name,
                'category' => $category,
                'difficulty_level' => $difficultyLevel
            ]);
            
            sendJson(true, [
                'id' => $templateId,
                'name' => $name,
                'message' => 'Template created successfully'
            ]);
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error creating template: " . $e->getMessage());
            sendJson(false, null, ['Failed to create template'], 500);
        }
    }

    public static function updateTemplate($templateId) {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($templateId)) {
            sendJson(false, null, ['Invalid template ID'], 400);
        }
        
        $data = getInput();
        $pdo = DB::getPDO();
        
        // Get current template
        $stmt = $pdo->prepare("SELECT name, description, category, difficulty_level, estimated_time, active FROM templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $currentTemplate = $stmt->fetch();
        
        if (!$currentTemplate) {
            sendJson(false, null, ['Template not found'], 404);
        }
        
        // Validate and prepare updates
        $updates = [];
        $params = [];
        
        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                sendJson(false, null, ['Template name cannot be empty'], 400);
            }
            $updates[] = 'name = ?';
            $params[] = $name;
        }
        
        if (isset($data['description'])) {
            $updates[] = 'description = ?';
            $params[] = trim($data['description']);
        }
        
        if (isset($data['category'])) {
            $updates[] = 'category = ?';
            $params[] = trim($data['category']);
        }
        
        if (isset($data['difficulty_level'])) {
            if (!in_array($data['difficulty_level'], ['beginner', 'intermediate', 'advanced'])) {
                sendJson(false, null, ['Difficulty level must be beginner, intermediate, or advanced'], 400);
            }
            $updates[] = 'difficulty_level = ?';
            $params[] = $data['difficulty_level'];
        }
        
        if (isset($data['estimated_time'])) {
            $updates[] = 'estimated_time = ?';
            $params[] = $data['estimated_time'];
        }
        
        if (isset($data['active'])) {
            $updates[] = 'active = ?';
            $params[] = (bool)$data['active'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            sendJson(false, null, ['No valid fields to update'], 400);
        }
        
        $updates[] = 'updated_at = NOW()';
        $params[] = $templateId;
        
        try {
            $stmt = $pdo->prepare("UPDATE templates SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            
            logAudit('admin_template_updated', [
                'template_id' => $templateId,
                'changes' => $data
            ]);
            
            sendJson(true, ['message' => 'Template updated successfully']);
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error updating template: " . $e->getMessage());
            sendJson(false, null, ['Failed to update template'], 500);
        }
    }

    public static function deleteTemplate($templateId) {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($templateId)) {
            sendJson(false, null, ['Invalid template ID'], 400);
        }
        
        $pdo = DB::getPDO();
        
        // Get template details for logging
        $stmt = $pdo->prepare("SELECT name FROM templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            sendJson(false, null, ['Template not found'], 404);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Delete template items, sections, tags, then template
            $stmt = $pdo->prepare("DELETE FROM template_items WHERE template_id = ?");
            $stmt->execute([$templateId]);
            
            $stmt = $pdo->prepare("DELETE FROM template_sections WHERE template_id = ?");
            $stmt->execute([$templateId]);
            
            $stmt = $pdo->prepare("DELETE FROM template_tags WHERE template_id = ?");
            $stmt->execute([$templateId]);
            
            $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ?");
            $stmt->execute([$templateId]);
            
            $pdo->commit();
            
            logAudit('admin_template_deleted', [
                'template_id' => $templateId,
                'template_name' => $template['name']
            ]);
            
            sendJson(true, ['message' => 'Template deleted successfully']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            logMessage('ERROR', "Error deleting template: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete template'], 500);
        }
    }

    public static function hardDeleteChecklist($checklistId) {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($checklistId)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }
        
        $pdo = DB::getPDO();
        
        // Get checklist details for logging
        $stmt = $pdo->prepare("SELECT title, owner_id FROM checklists WHERE id = ?");
        $stmt->execute([$checklistId]);
        $checklist = $stmt->fetch();
        
        if (!$checklist) {
            sendJson(false, null, ['Checklist not found'], 404);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Hard delete everything related to the checklist
            $stmt = $pdo->prepare("DELETE it FROM item_tags it JOIN items i ON it.item_id = i.id WHERE i.checklist_id = ?");
            $stmt->execute([$checklistId]);
            
            $stmt = $pdo->prepare("DELETE FROM items WHERE checklist_id = ?");
            $stmt->execute([$checklistId]);
            
            $stmt = $pdo->prepare("DELETE FROM sections WHERE checklist_id = ?");
            $stmt->execute([$checklistId]);
            
            $stmt = $pdo->prepare("DELETE FROM collaborators WHERE checklist_id = ?");
            $stmt->execute([$checklistId]);
            
            $stmt = $pdo->prepare("DELETE FROM checklists WHERE id = ?");
            $stmt->execute([$checklistId]);
            
            $pdo->commit();
            
            logAudit('admin_checklist_hard_deleted', [
                'checklist_id' => $checklistId,
                'checklist_title' => $checklist['title'],
                'owner_id' => $checklist['owner_id']
            ]);
            
            sendJson(true, ['message' => 'Checklist permanently deleted']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            logMessage('ERROR', "Error hard deleting checklist: " . $e->getMessage());
            sendJson(false, null, ['Failed to delete checklist'], 500);
        }
    }

    public static function recoverChecklist($checklistId) {
        $user_id = checkAuth();
        if (!isAdmin($user_id)) {
            sendJson(false, null, ['Forbidden - Admin access required'], 403);
        }
        
        if (!is_numeric($checklistId)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }
        
        $pdo = DB::getPDO();
        
        // Check if checklist exists and is soft-deleted
        $stmt = $pdo->prepare("SELECT title, owner_id, deleted_at FROM checklists WHERE id = ? AND deleted_at IS NOT NULL");
        $stmt->execute([$checklistId]);
        $checklist = $stmt->fetch();
        
        if (!$checklist) {
            sendJson(false, null, ['Checklist not found or not deleted'], 404);
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE checklists SET deleted_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$checklistId]);
            
            logAudit('admin_checklist_recovered', [
                'checklist_id' => $checklistId,
                'checklist_title' => $checklist['title'],
                'owner_id' => $checklist['owner_id']
            ]);
            
            sendJson(true, ['message' => 'Checklist recovered successfully']);
            
        } catch (Exception $e) {
            logMessage('ERROR', "Error recovering checklist: " . $e->getMessage());
            sendJson(false, null, ['Failed to recover checklist'], 500);
        }
    }
}

?>