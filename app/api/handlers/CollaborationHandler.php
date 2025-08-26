<?php

class CollaborationHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        // Handle different routes: 
        // /collaborations/{checklist_id}/invite
        // /collaborations/{checklist_id}/users
        // /collaborations/{checklist_id}/users/{user_id}
        // /collaborations/public/{checklist_id}
        
        if (is_numeric($action)) {
            $checklist_id = (int)$action;
            $sub_action = $uri_parts[2] ?? '';
            $target_user_id = $uri_parts[3] ?? null;
            
            switch ($sub_action) {
                case 'invite':
                    if ($method === 'POST') {
                        self::inviteCollaborator($user_id, $checklist_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                    
                case 'users':
                    if ($method === 'GET') {
                        self::getCollaborators($user_id, $checklist_id);
                    } elseif ($method === 'PUT' && $target_user_id) {
                        self::updateCollaboratorRole($user_id, $checklist_id, (int)$target_user_id);
                    } elseif ($method === 'DELETE' && $target_user_id) {
                        self::removeCollaborator($user_id, $checklist_id, (int)$target_user_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                    
                case 'public':
                    if ($method === 'POST') {
                        self::enablePublicSharing($user_id, $checklist_id);
                    } elseif ($method === 'DELETE') {
                        self::disablePublicSharing($user_id, $checklist_id);
                    } else {
                        sendJson(false, null, ['Method not allowed'], 405);
                    }
                    break;
                    
                default:
                    sendJson(false, null, ['Invalid collaboration endpoint'], 404);
            }
            
        } elseif ($action === 'public' && is_numeric($uri_parts[2] ?? 0)) {
            // Access public checklist by share token
            if ($method === 'GET') {
                self::getPublicChecklist($uri_parts[2]);
            } else {
                sendJson(false, null, ['Method not allowed'], 405);
            }
            
        } elseif ($action === 'accept' && $method === 'POST') {
            // Accept collaboration invitation (if implemented via email tokens)
            self::acceptInvitation($user_id);
            
        } else {
            sendJson(false, null, ['Invalid collaboration endpoint'], 404);
        }
    }

    private static function inviteCollaborator($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        // Only checklist owner can invite collaborators
        checkPermission($checklist_id, $user_id, 'owner');

        $input = getInput();
        $email = strtolower(trim($input['email'] ?? ''));
        $role = strtolower(trim($input['role'] ?? 'viewer'));

        if (!validateEmail($email)) {
            sendJson(false, null, ['Invalid email address'], 400);
        }

        if (!in_array($role, ['viewer', 'collaborator'])) {
            sendJson(false, null, ['Role must be "viewer" or "collaborator"'], 400);
        }

        checkCsrf();
        rateLimit("invite_collab_$user_id", 20, 3600); // 20 per hour

        $pdo = DB::getPDO();

        try {
            $pdo->beginTransaction();

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $target_user = $stmt->fetch();

            if (!$target_user) {
                $pdo->rollback();
                sendJson(false, null, ['User not found or inactive'], 404);
            }

            $target_user_id = $target_user['id'];

            // Cannot invite yourself
            if ($target_user_id == $user_id) {
                $pdo->rollback();
                sendJson(false, null, ['Cannot invite yourself'], 400);
            }

            // Check if user is already a collaborator
            $stmt = $pdo->prepare("SELECT id, role FROM collaborators WHERE checklist_id = ? AND user_id = ?");
            $stmt->execute([$checklist_id, $target_user_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                $pdo->rollback();
                sendJson(false, null, ['User is already a collaborator'], 400);
            }

            // Check collaboration limit - max 10 collaborators per checklist
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM collaborators WHERE checklist_id = ?");
            $stmt->execute([$checklist_id]);
            $count = $stmt->fetchColumn();

            if ($count >= 10) {
                $pdo->rollback();
                sendJson(false, null, ['Maximum collaborator limit reached (10 per checklist)'], 400);
            }

            // Get checklist details
            $stmt = $pdo->prepare("SELECT title, owner_id FROM checklists WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$checklist_id]);
            $checklist = $stmt->fetch();

            if (!$checklist) {
                $pdo->rollback();
                sendJson(false, null, ['Checklist not found'], 404);
            }

            // Add collaboration
            $stmt = $pdo->prepare("
                INSERT INTO collaborators (checklist_id, user_id, role, invited_by, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$checklist_id, $target_user_id, $role, $user_id]);
            $collaboration_id = $pdo->lastInsertId();

            $pdo->commit();

            logAudit('COLLABORATOR_INVITED', [
                'checklist_id' => $checklist_id,
                'target_user_id' => $target_user_id,
                'target_email' => $email,
                'target_name' => $target_user['name'],
                'role' => $role,
                'collaboration_id' => $collaboration_id
            ], $user_id);

            sendJson(true, [
                'message' => 'Collaborator invited successfully',
                'collaboration_id' => (int)$collaboration_id,
                'user_name' => $target_user['name'],
                'role' => $role
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to invite collaborator: " . $e->getMessage());
            sendJson(false, null, ['Failed to invite collaborator'], 500);
        }
    }

    private static function getCollaborators($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        // Must have at least viewer access
        checkPermission($checklist_id, $user_id, 'viewer');

        $pdo = DB::getPDO();

        // Get checklist owner and collaborators
        $stmt = $pdo->prepare("
            SELECT 
                u.id, u.name, u.email, 'owner' as role, c.created_at as joined_at,
                NULL as invited_by_name
            FROM checklists c
            JOIN users u ON c.owner_id = u.id
            WHERE c.id = ? AND c.deleted_at IS NULL
            
            UNION ALL
            
            SELECT 
                u.id, u.name, u.email, col.role, col.created_at as joined_at,
                inv.name as invited_by_name
            FROM collaborators col
            JOIN users u ON col.user_id = u.id
            LEFT JOIN users inv ON col.invited_by = inv.id
            WHERE col.checklist_id = ?
            
            ORDER BY role DESC, joined_at ASC
        ");
        $stmt->execute([$checklist_id, $checklist_id]);
        $collaborators = $stmt->fetchAll();

        sendJson(true, ['collaborators' => $collaborators]);
    }

    private static function updateCollaboratorRole($user_id, $checklist_id, $target_user_id) {
        if (!validateId($checklist_id) || !validateId($target_user_id)) {
            sendJson(false, null, ['Invalid checklist or user ID'], 400);
        }

        // Only checklist owner can update collaborator roles
        checkPermission($checklist_id, $user_id, 'owner');

        $input = getInput();
        $new_role = strtolower(trim($input['role'] ?? ''));

        if (!in_array($new_role, ['viewer', 'collaborator'])) {
            sendJson(false, null, ['Role must be "viewer" or "collaborator"'], 400);
        }

        checkCsrf();

        $pdo = DB::getPDO();

        try {
            $pdo->beginTransaction();

            // Get current collaboration details
            $stmt = $pdo->prepare("
                SELECT col.id, col.role, u.name, u.email
                FROM collaborators col
                JOIN users u ON col.user_id = u.id
                WHERE col.checklist_id = ? AND col.user_id = ?
            ");
            $stmt->execute([$checklist_id, $target_user_id]);
            $collaboration = $stmt->fetch();

            if (!$collaboration) {
                $pdo->rollback();
                sendJson(false, null, ['Collaboration not found'], 404);
            }

            if ($collaboration['role'] === $new_role) {
                $pdo->rollback();
                sendJson(false, null, ['User already has this role'], 400);
            }

            // Update role
            $stmt = $pdo->prepare("UPDATE collaborators SET role = ? WHERE checklist_id = ? AND user_id = ?");
            $stmt->execute([$new_role, $checklist_id, $target_user_id]);

            $pdo->commit();

            logAudit('COLLABORATOR_ROLE_UPDATED', [
                'checklist_id' => $checklist_id,
                'target_user_id' => $target_user_id,
                'target_email' => $collaboration['email'],
                'target_name' => $collaboration['name'],
                'old_role' => $collaboration['role'],
                'new_role' => $new_role
            ], $user_id);

            sendJson(true, ['message' => 'Collaborator role updated successfully']);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to update collaborator role: " . $e->getMessage());
            sendJson(false, null, ['Failed to update collaborator role'], 500);
        }
    }

    private static function removeCollaborator($user_id, $checklist_id, $target_user_id) {
        if (!validateId($checklist_id) || !validateId($target_user_id)) {
            sendJson(false, null, ['Invalid checklist or user ID'], 400);
        }

        $pdo = DB::getPDO();

        // Check if user is removing themselves or if they're the owner
        $is_self_removal = ($target_user_id == $user_id);
        
        if (!$is_self_removal) {
            // Only checklist owner can remove other collaborators
            checkPermission($checklist_id, $user_id, 'owner');
        } else {
            // User removing themselves - must at least be a collaborator
            checkPermission($checklist_id, $user_id, 'viewer');
        }

        checkCsrf();

        try {
            $pdo->beginTransaction();

            // Get collaboration details for logging
            $stmt = $pdo->prepare("
                SELECT col.id, col.role, u.name, u.email
                FROM collaborators col
                JOIN users u ON col.user_id = u.id
                WHERE col.checklist_id = ? AND col.user_id = ?
            ");
            $stmt->execute([$checklist_id, $target_user_id]);
            $collaboration = $stmt->fetch();

            if (!$collaboration) {
                $pdo->rollback();
                sendJson(false, null, ['Collaboration not found'], 404);
            }

            // Remove collaboration
            $stmt = $pdo->prepare("DELETE FROM collaborators WHERE checklist_id = ? AND user_id = ?");
            $stmt->execute([$checklist_id, $target_user_id]);

            $pdo->commit();

            logAudit('COLLABORATOR_REMOVED', [
                'checklist_id' => $checklist_id,
                'target_user_id' => $target_user_id,
                'target_email' => $collaboration['email'],
                'target_name' => $collaboration['name'],
                'role' => $collaboration['role'],
                'self_removal' => $is_self_removal
            ], $user_id);

            $message = $is_self_removal ? 'Left checklist successfully' : 'Collaborator removed successfully';
            sendJson(true, ['message' => $message]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to remove collaborator: " . $e->getMessage());
            sendJson(false, null, ['Failed to remove collaborator'], 500);
        }
    }

    private static function enablePublicSharing($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        // Only checklist owner can enable public sharing
        checkPermission($checklist_id, $user_id, 'owner');
        checkCsrf();

        $pdo = DB::getPDO();

        try {
            $pdo->beginTransaction();

            // Check if already has public sharing enabled
            $stmt = $pdo->prepare("SELECT share_view_token, is_public FROM checklists WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$checklist_id]);
            $checklist = $stmt->fetch();

            if (!$checklist) {
                $pdo->rollback();
                sendJson(false, null, ['Checklist not found'], 404);
            }

            if ($checklist['is_public']) {
                $pdo->rollback();
                sendJson(false, null, ['Public sharing is already enabled'], 400);
            }

            // Generate unique share token
            do {
                $share_token = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("SELECT id FROM checklists WHERE share_view_token = ?");
                $stmt->execute([$share_token]);
            } while ($stmt->fetchColumn()); // Ensure uniqueness

            // Enable public sharing
            $stmt = $pdo->prepare("
                UPDATE checklists 
                SET is_public = 1, share_view_token = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$share_token, $checklist_id]);

            $pdo->commit();

            logAudit('PUBLIC_SHARING_ENABLED', [
                'checklist_id' => $checklist_id,
                'share_token' => $share_token
            ], $user_id);

            sendJson(true, [
                'message' => 'Public sharing enabled successfully',
                'share_token' => $share_token,
                'share_url' => "/public/{$share_token}"
            ]);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to enable public sharing: " . $e->getMessage());
            sendJson(false, null, ['Failed to enable public sharing'], 500);
        }
    }

    private static function disablePublicSharing($user_id, $checklist_id) {
        if (!validateId($checklist_id)) {
            sendJson(false, null, ['Invalid checklist ID'], 400);
        }

        // Only checklist owner can disable public sharing
        checkPermission($checklist_id, $user_id, 'owner');
        checkCsrf();

        $pdo = DB::getPDO();

        try {
            $pdo->beginTransaction();

            // Check if public sharing is enabled
            $stmt = $pdo->prepare("SELECT share_view_token, is_public FROM checklists WHERE id = ? AND deleted_at IS NULL");
            $stmt->execute([$checklist_id]);
            $checklist = $stmt->fetch();

            if (!$checklist) {
                $pdo->rollback();
                sendJson(false, null, ['Checklist not found'], 404);
            }

            if (!$checklist['is_public']) {
                $pdo->rollback();
                sendJson(false, null, ['Public sharing is not enabled'], 400);
            }

            // Disable public sharing
            $stmt = $pdo->prepare("
                UPDATE checklists 
                SET is_public = 0, share_view_token = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$checklist_id]);

            $pdo->commit();

            logAudit('PUBLIC_SHARING_DISABLED', [
                'checklist_id' => $checklist_id,
                'old_share_token' => $checklist['share_view_token']
            ], $user_id);

            sendJson(true, ['message' => 'Public sharing disabled successfully']);

        } catch (PDOException $e) {
            $pdo->rollback();
            logMessage('ERROR', "Failed to disable public sharing: " . $e->getMessage());
            sendJson(false, null, ['Failed to disable public sharing'], 500);
        }
    }

    private static function getPublicChecklist($share_token) {
        if (empty($share_token) || strlen($share_token) !== 64) {
            sendJson(false, null, ['Invalid share token'], 400);
        }

        $pdo = DB::getPDO();

        // Get public checklist by share token
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as owner_name
            FROM checklists c
            JOIN users u ON c.owner_id = u.id
            WHERE c.share_view_token = ? AND c.is_public = 1 AND c.deleted_at IS NULL
        ");
        $stmt->execute([$share_token]);
        $checklist = $stmt->fetch();

        if (!$checklist) {
            sendJson(false, null, ['Public checklist not found or sharing disabled'], 404);
        }

        // Get sections and items (read-only)
        $stmt = $pdo->prepare("
            SELECT s.id, s.name, s.order_pos,
                   i.id as item_id, i.text as item_text, i.completed as item_completed,
                   i.order_pos as item_order, i.created_at as item_created
            FROM sections s
            LEFT JOIN items i ON s.id = i.section_id
            WHERE s.checklist_id = ?
            ORDER BY s.order_pos ASC, i.order_pos ASC
        ");
        $stmt->execute([$checklist['id']]);
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
        $checklist['is_public_view'] = true; // Flag for frontend

        // Calculate progress
        $total_items = 0;
        $completed_items = 0;
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $total_items++;
                if ($item['completed']) {
                    $completed_items++;
                }
            }
        }
        $checklist['progress'] = $total_items > 0 ? round(($completed_items / $total_items) * 100) : 0;
        $checklist['item_count'] = $total_items;
        $checklist['completed_count'] = $completed_items;

        sendJson(true, $checklist);
    }

    private static function acceptInvitation($user_id) {
        // This would be used for email-based invitations with tokens
        // For now, return a placeholder since we're doing direct invitations
        $input = getInput();
        $token = $input['token'] ?? '';

        if (empty($token)) {
            sendJson(false, null, ['Invitation token required'], 400);
        }

        // Implementation would verify invitation token and add collaboration
        sendJson(false, null, ['Email-based invitations not implemented yet'], 501);
    }
}

?>