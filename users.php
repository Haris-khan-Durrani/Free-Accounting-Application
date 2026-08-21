<?php
require __DIR__ . '/bootstrap.php';
require_login();
require __DIR__ . '/layout.php';

$pdo = $GLOBALS['pdo'];
$tid = tenant_id();
$activeTenant = tenant();
$error = '';

// Team member management — owner or admin only
if (!has_role(['owner', 'admin'])) {
    flash('error', 'Access denied. Team management requires admin or owner access.');
    redirect('index');
}

// Check User Quota for Current Tenant
$stTenantPlan = $pdo->prepare("SELECT t.subscription_status, p.max_team_users, p.name plan_name FROM tenants t LEFT JOIN saas_plans p ON p.id = t.plan_id WHERE t.id = ?");
$stTenantPlan->execute([$tid]);
$tPlan = $stTenantPlan->fetch();

$isLifetime = ($tPlan['subscription_status'] ?? '') === 'lifetime';
$maxUsersAllowed = $isLifetime ? 999999 : (int)($tPlan['max_team_users'] ?? 5);

$stCurrentUsers = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE tenant_id = ?");
$stCurrentUsers->execute([$tid]);
$currentUsersCount = (int)$stCurrentUsers->fetchColumn();

$activeUserId = (int)($_SESSION['user_id'] ?? 0);
$isMasterSuperAdmin = ($tid === 1 && has_role(['owner']));

// Fetch accessible tenants for the logged-in admin user
if ($isMasterSuperAdmin) {
    $stAllTenants = $pdo->query("SELECT id, name, code FROM tenants ORDER BY name ASC");
    $allTenants = $stAllTenants->fetchAll();
    $accessibleTenants = $allTenants;
} else {
    $accessibleTenants = \Core\Tenant::getUserTenants($pdo, $activeUserId);
    $allTenants = $accessibleTenants;
}
$canManageMultipleTenants = (count($accessibleTenants) > 1 || $isMasterSuperAdmin);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'create_user';

    $accessibleTenantIds = array_map(fn($at) => (int)$at['id'], $accessibleTenants);
    $allowedRoles = $isMasterSuperAdmin ? ['owner', 'admin', 'accountant', 'sales', 'viewer'] : ['admin', 'accountant', 'sales', 'viewer'];

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'accountant';
        $requestedTenants = array_map('intval', (array)($_POST['target_tenant_ids'] ?? [$tid]));
        
        // Strict BOLA check: Filter target tenants strictly to caller's accessible tenant set
        $targetTenantIds = array_values(array_intersect($requestedTenants, $accessibleTenantIds));
        if (empty($targetTenantIds)) {
            $targetTenantIds = [$tid];
        }
        $primaryTenantId = $targetTenantIds[0];

        // Strict Server-Side Role Allowlist Verification
        if (!in_array($role, $allowedRoles, true)) {
            $role = 'accountant';
        }

        if (!$isLifetime && $currentUsersCount >= $maxUsersAllowed) {
            $error = "Team user limit reached ($currentUsersCount/$maxUsersAllowed allowed on your " . ($tPlan['plan_name'] ?? 'Plan') . "). Please upgrade your subscription plan to add more team members.";
        } elseif (!$name || !$email || strlen($password) < 12) {
            $error = 'Name, valid email, and strong password (min 12 chars) are required.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $st = $pdo->prepare("INSERT INTO users (tenant_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $st->execute([$primaryTenantId, $name, $email, $hash, $role]);
                $newUserId = (int)$pdo->lastInsertId();

                // Multi-assign user strictly to authorized workspace locations
                $stUt = $pdo->prepare("INSERT INTO user_tenants (user_id, tenant_id, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = ?");
                foreach ($targetTenantIds as $tId) {
                    $stUt->execute([$newUserId, $tId, $role, $role]);
                }

                // Find assigned workspace names
                $assignedTenantNames = [];
                foreach ($allTenants as $at) {
                    if (in_array((int)$at['id'], $targetTenantIds, true)) {
                        $assignedTenantNames[] = $at['name'];
                    }
                }
                $assignedTenantNameStr = implode(', ', $assignedTenantNames);

                // Dispatch Welcome Email (Security Best Practice: Do not email plaintext passwords)
                $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $host = preg_replace('/[^a-zA-Z0-9\.\:\-]/', '', $rawHost);
                $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $appUrl = getenv('APP_URL') ?: "{$protocol}://{$host}{$scriptDir}";
                $loginUrl = rtrim($appUrl, '/') . '/login.php';

                $subject = "Welcome to " . e($assignedTenantNameStr) . " - Your Workspace Account";
                $htmlBody = "
                    <div style='font-family: system-ui, -apple-system, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                        <div style='text-align: center; margin-bottom: 20px;'>
                            <h2 style='color: #0f172a; margin: 0; font-size: 22px;'>Welcome to " . e($assignedTenantNameStr) . "! 🎉</h2>
                            <p style='color: #64748b; font-size: 13px; margin-top: 6px;'>Your team member account has been created and assigned to your workspace locations.</p>
                        </div>
                        <div style='background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #f1f5f9;'>
                            <table style='width: 100%; border-collapse: collapse; font-size: 13px; color: #334155;'>
                                <tr><td style='padding: 6px 0; font-weight: bold; width: 140px;'>Assigned Workspaces:</td><td style='padding: 6px 0; font-weight: 700; color: #0f172a;'>" . e($assignedTenantNameStr) . "</td></tr>
                                <tr><td style='padding: 6px 0; font-weight: bold;'>Permission Role:</td><td style='padding: 6px 0;'><span style='background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 99px; font-weight: 800; font-size: 11px; text-transform: uppercase;'>" . e($role) . "</span></td></tr>
                                <tr><td style='padding: 6px 0; font-weight: bold;'>Login Email:</td><td style='padding: 6px 0; font-weight: 600;'>" . e($email) . "</td></tr>
                            </table>
                        </div>
                        <div style='text-align: center; margin-top: 24px;'>
                            <a href='" . e($loginUrl) . "' style='display: inline-block; padding: 12px 28px; background: linear-gradient(to right, #f59e0b, #d97706); color: #ffffff; text-decoration: none; font-weight: 800; border-radius: 12px; font-size: 14px;'>Log In to Workspace &rarr;</a>
                        </div>
                    </div>
                ";

                $emailNotice = '';
                try {
                    $sent = \Services\Mailer::send($pdo, $primaryTenantId, $email, $subject, $htmlBody);
                    if ($sent) {
                        $emailNotice = " & a Welcome Email was sent to $email!";
                    }
                } catch (Throwable $t) {
                    $emailNotice = " (Note: Welcome email could not be delivered. Check custom SMTP settings).";
                }

                log_audit($pdo, 'create_user', 'users', $newUserId, "Created user $email with role $role assigned to workspaces $assignedTenantNameStr");
                flash('success', "User account for $email created successfully and assigned to workspaces: '$assignedTenantNameStr'" . $emailNotice);
                redirect('users');
            } catch (PDOException $e) {
                $error = 'Failed to create user. Email address may already exist.';
            }
        }
    }

    if ($action === 'edit_user') {
        $editUserId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'accountant';
        $requestedTenants = array_map('intval', (array)($_POST['target_tenant_ids'] ?? [$tid]));
        
        // Strict BOLA check: Ensure edit user target belongs to caller's accessible tenant set
        $inClause = implode(',', array_fill(0, count($accessibleTenantIds), '?'));
        $stCheckUser = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id IN ($inClause)");
        $stCheckUser->execute(array_merge([$editUserId], $accessibleTenantIds));
        if ((int)$stCheckUser->fetchColumn() === 0) {
            flash('error', 'Access denied. Target user does not belong to your authorized workspace locations.');
            redirect('users');
        }

        // Filter target tenants strictly to caller's accessible tenant set
        $targetTenantIds = array_values(array_intersect($requestedTenants, $accessibleTenantIds));
        if (empty($targetTenantIds)) {
            $targetTenantIds = [$tid];
        }
        $primaryTenantId = $targetTenantIds[0];
        $password = $_POST['password'] ?? '';

        if (!in_array($role, $allowedRoles, true)) {
            $role = 'accountant';
        }

        // Check if target user belongs to external workspaces outside caller's accessible tenant set
        $stExtCheck = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id NOT IN ($inClause)");
        $stExtCheck->execute(array_merge([$editUserId], $accessibleTenantIds));
        $isSharedExternalUser = ((int)$stExtCheck->fetchColumn() > 0);

        // Fetch target user's existing email & primary tenant
        $stExistingUser = $pdo->prepare("SELECT email, tenant_id FROM users WHERE id = ?");
        $stExistingUser->execute([$editUserId]);
        $existingUser = $stExistingUser->fetch();

        if (!$editUserId || !$name || !$email) {
            $error = 'User ID, name, and email are required for updating.';
        } elseif ($isSharedExternalUser && (!empty($password) || strtolower($email) !== strtolower($existingUser['email'] ?? ''))) {
            $error = 'Security Protection: This user account is shared across external workspaces. Local administrators can update workspace role assignments, but cannot modify global login credentials (email/password). The user must manage their credentials directly.';
        } else {
            try {
                if (!$isSharedExternalUser) {
                    if (!empty($password) && strlen($password) >= 12) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stU = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, tenant_id = ?, password_hash = ? WHERE id = ?");
                        $stU->execute([$name, $email, $role, $primaryTenantId, $hash, $editUserId]);
                    } else {
                        $stU = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, tenant_id = ? WHERE id = ?");
                        $stU->execute([$name, $email, $role, $primaryTenantId, $editUserId]);
                    }
                } else {
                    // Update only local display name if updated
                    $stU = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                    $stU->execute([$name, $editUserId]);
                }

                // Re-sync all assigned workspace locations for this user strictly within accessible tenants
                $stDelUt = $pdo->prepare("DELETE FROM user_tenants WHERE user_id = ? AND tenant_id IN ($inClause)");
                $stDelUt->execute(array_merge([$editUserId], $accessibleTenantIds));

                $stInsUt = $pdo->prepare("INSERT INTO user_tenants (user_id, tenant_id, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = ?");
                foreach ($targetTenantIds as $tId) {
                    $stInsUt->execute([$editUserId, $tId, $role, $role]);
                }

                log_audit($pdo, 'edit_user', 'users', $editUserId, "Updated user #$editUserId ($email)");
                flash('success', "Team member '$name' workspace assignments updated successfully!");
                redirect('users');
            } catch (PDOException $e) {
                $error = 'Failed to update user. Email address may already be in use.';
            }
        }
    }

    if ($action === 'resend_welcome') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        // BOLA Check
        $inClause = implode(',', array_fill(0, count($accessibleTenantIds), '?'));
        $stCheckUser = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id IN ($inClause)");
        $stCheckUser->execute(array_merge([$targetUserId], $accessibleTenantIds));
        if ((int)$stCheckUser->fetchColumn() === 0) {
            flash('error', 'Access denied. Target user does not belong to your authorized workspace locations.');
            redirect('users');
        }

        $stU = $pdo->prepare("SELECT u.*, GROUP_CONCAT(t.name SEPARATOR ', ') as workspace_names 
                              FROM users u 
                              LEFT JOIN user_tenants ut ON ut.user_id = u.id 
                              LEFT JOIN tenants t ON t.id = ut.tenant_id 
                              WHERE u.id = ? GROUP BY u.id");
        $stU->execute([$targetUserId]);
        $uData = $stU->fetch();

        if (!$uData) {
            $error = 'User not found.';
        } else {
            $assignedTenantNameStr = $uData['workspace_names'] ?: 'Company Workspace';
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $host = preg_replace('/[^a-zA-Z0-9\.\:\-]/', '', $rawHost);
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $appUrl = getenv('APP_URL') ?: "{$protocol}://{$host}{$scriptDir}";
            $loginUrl = rtrim($appUrl, '/') . '/login.php';

            $subject = "Welcome to " . e($assignedTenantNameStr) . " - Workspace Access";
            $htmlBody = "
                <div style='font-family: system-ui, -apple-system, sans-serif; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #0f172a; margin: 0; font-size: 22px;'>Welcome to " . e($assignedTenantNameStr) . "! 🎉</h2>
                        <p style='color: #64748b; font-size: 13px; margin-top: 6px;'>Your team member workspace access reminder.</p>
                    </div>
                    <div style='background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #f1f5f9;'>
                        <table style='width: 100%; border-collapse: collapse; font-size: 13px; color: #334155;'>
                            <tr><td style='padding: 6px 0; font-weight: bold; width: 140px;'>Assigned Workspaces:</td><td style='padding: 6px 0; font-weight: 700; color: #0f172a;'>" . e($assignedTenantNameStr) . "</td></tr>
                            <tr><td style='padding: 6px 0; font-weight: bold;'>Permission Role:</td><td style='padding: 6px 0;'><span style='background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 99px; font-weight: 800; font-size: 11px; text-transform: uppercase;'>" . e($uData['role']) . "</span></td></tr>
                            <tr><td style='padding: 6px 0; font-weight: bold;'>Login Email:</td><td style='padding: 6px 0; font-weight: 600;'>" . e($uData['email']) . "</td></tr>
                        </table>
                    </div>
                    <div style='text-align: center; margin-top: 24px;'>
                        <a href='" . e($loginUrl) . "' style='display: inline-block; padding: 12px 28px; background: linear-gradient(to right, #f59e0b, #d97706); color: #ffffff; text-decoration: none; font-weight: 800; border-radius: 12px; font-size: 14px;'>Log In to Workspace &rarr;</a>
                    </div>
                </div>
            ";

            try {
                $sent = \Services\Mailer::send($pdo, (int)($uData['tenant_id'] ?: 1), $uData['email'], $subject, $htmlBody);
                if ($sent) {
                    log_audit($pdo, 'resend_welcome_email', 'users', $targetUserId, "Resent welcome email to {$uData['email']}");
                    flash('success', "Welcome email successfully dispatched to {$uData['email']}!");
                } else {
                    flash('warning', "Email dispatch attempted. Please verify custom SMTP settings.");
                }
            } catch (Throwable $t) {
                $error = 'Failed to send welcome email: ' . $t->getMessage();
            }
            redirect('users');
        }
    }

    if ($action === 'send_password_reset') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        // BOLA Check
        $inClause = implode(',', array_fill(0, count($accessibleTenantIds), '?'));
        $stCheckUser = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id IN ($inClause)");
        $stCheckUser->execute(array_merge([$targetUserId], $accessibleTenantIds));
        if ((int)$stCheckUser->fetchColumn() === 0) {
            flash('error', 'Access denied. Target user does not belong to your authorized workspace locations.');
            redirect('users');
        }

        $stU = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stU->execute([$targetUserId]);
        $uData = $stU->fetch();

        if (!$uData) {
            $error = 'User not found.';
        } else {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stUpd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
            $stUpd->execute([$tokenHash, $expiresAt, $targetUserId]);

            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $host = preg_replace('/[^a-zA-Z0-9\.\:\-]/', '', $rawHost);
            $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $appUrl = getenv('APP_URL') ?: "{$protocol}://{$host}{$scriptDir}";
            $resetUrl = rtrim($appUrl, '/') . "/reset_password.php?token={$rawToken}";

            $subject = "Password Reset Request - OneSol";
            $htmlBody = "
                <div style='font-family: sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background: #ffffff;'>
                    <div style='text-align: center; margin-bottom: 24px;'>
                        <h2 style='color: #0f172a; margin: 0 0 8px 0; font-size: 22px; font-weight: 800;'>Reset Your Password</h2>
                        <p style='color: #64748b; font-size: 13px; margin: 0;'>OneSol Invoice Manager Security System</p>
                    </div>
                    <p style='color: #334155; font-size: 14px; line-height: 1.5;'>Hello <strong>" . e($uData['name']) . "</strong>,</p>
                    <p style='color: #475569; font-size: 14px; line-height: 1.5;'>An administrator requested a password reset for your account (<strong>" . e($uData['email']) . "</strong>). Click the button below to set a new password:</p>
                    
                    <div style='text-align: center; margin: 28px 0;'>
                        <a href='" . e($resetUrl) . "' style='display: inline-block; background: #d97706; color: #ffffff; text-decoration: none; font-weight: 800; font-size: 14px; padding: 12px 28px; border-radius: 12px; shadow: 0 4px 6px -1px rgba(0,0,0,0.1);'>Reset Password Now</a>
                    </div>
                    
                    <p style='color: #64748b; font-size: 12px; line-height: 1.5;'>If the button above does not work, copy and paste the following link into your browser:</p>
                    <p style='word-break: break-all; font-size: 11px; color: #2563eb; background: #f8fafc; padding: 10px; border-radius: 8px; font-family: monospace;'>" . e($resetUrl) . "</p>
                    
                    <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
                    <p style='color: #94a3b8; font-size: 11px; margin: 0;'>This link is valid for <strong>1 hour</strong>.</p>
                </div>
            ";

            try {
                $sent = \Services\Mailer::send($pdo, (int)($uData['tenant_id'] ?: 1), $uData['email'], $subject, $htmlBody);
                if ($sent) {
                    log_audit($pdo, 'admin_password_reset_sent', 'users', $targetUserId, "Admin sent password reset email to {$uData['email']}");
                    flash('success', "Password reset link email successfully sent to {$uData['email']}!");
                } else {
                    flash('warning', "Password reset link generated. Please verify custom SMTP settings.");
                }
            } catch (Throwable $t) {
                $error = 'Failed to send password reset email: ' . $t->getMessage();
            }
            redirect('users');
        }
    }

    if ($action === 'delete_user') {
        $deleteUserId = (int)($_POST['user_id'] ?? 0);
        $activeUserId = (int)($_SESSION['user_id'] ?? 0);

        // BOLA Check
        $inClause = implode(',', array_fill(0, count($accessibleTenantIds), '?'));
        $stCheckUser = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ? AND tenant_id IN ($inClause)");
        $stCheckUser->execute(array_merge([$deleteUserId], $accessibleTenantIds));
        if ((int)$stCheckUser->fetchColumn() === 0) {
            flash('error', 'Access denied. Target user does not belong to your authorized workspace locations.');
            redirect('users');
        }

        if ($deleteUserId === $activeUserId) {
            $error = 'Security Protection: You cannot delete your own active user account.';
        } else {
            try {
                $st1 = $pdo->prepare("DELETE FROM user_tenants WHERE user_id = ? AND tenant_id = ?");
                $st1->execute([$deleteUserId, $tid]);

                // Check if user belongs to any other tenant
                $st2 = $pdo->prepare("SELECT COUNT(*) FROM user_tenants WHERE user_id = ?");
                $st2->execute([$deleteUserId]);
                if ($st2->fetchColumn() == 0) {
                    $st3 = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $st3->execute([$deleteUserId]);
                }

                log_audit($pdo, 'delete_user', 'users', $deleteUserId, "Removed user #$deleteUserId from workspace #$tid");
                flash('success', 'Team member removed from workspace successfully.');
                redirect('users');
            } catch (PDOException $e) {
                $error = 'Failed to delete user.';
            }
        }
    }
}

// Filter parameters
$filterSearch    = trim($_GET['q'] ?? '');
$filterWorkspace = isset($_GET['workspace_id']) && $_GET['workspace_id'] !== '' ? (int)$_GET['workspace_id'] : 0;
$filterRole      = trim($_GET['role'] ?? '');

$isMasterSuperAdmin = (has_role(['owner']) && tenant_id() === 1);

// Construct Query Dynamically
$where = [];
$params = [];

if ($isMasterSuperAdmin) {
    if ($filterWorkspace > 0) {
        $where[] = "(ut.tenant_id = ? OR u.tenant_id = ?)";
        $params[] = $filterWorkspace;
        $params[] = $filterWorkspace;
    }
} else {
    $where[] = "(ut.tenant_id = ? OR u.tenant_id = ?)";
    $params[] = $tid;
    $params[] = $tid;
}

if ($filterRole !== '') {
    $where[] = "(ut.role = ? OR u.role = ?)";
    $params[] = $filterRole;
    $params[] = $filterRole;
}

if ($filterSearch !== '') {
    $where[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT DISTINCT u.*, 
               COALESCE(ut.role, u.role) AS tenant_role, 
               t.name AS tenant_workspace_name, 
               t.code AS tenant_workspace_code 
        FROM users u 
        LEFT JOIN user_tenants ut ON ut.user_id = u.id 
        LEFT JOIN tenants t ON t.id = COALESCE(ut.tenant_id, u.tenant_id) 
        {$whereSql} 
        ORDER BY u.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$users = $st->fetchAll();

$userTenantMap = [];
if (!empty($users)) {
    $uIds = array_column($users, 'id');
    $inClause = implode(',', array_fill(0, count($uIds), '?'));
    $stUtMap = $pdo->prepare("SELECT ut.user_id, ut.tenant_id, t.name, t.code 
                              FROM user_tenants ut 
                              JOIN tenants t ON t.id = ut.tenant_id 
                              WHERE ut.user_id IN ($inClause)");
    $stUtMap->execute($uIds);
    foreach ($stUtMap->fetchAll() as $row) {
        $userTenantMap[$row['user_id']][] = $row;
    }
}

$hasActiveFilters = ($filterSearch !== '' || $filterWorkspace > 0 || $filterRole !== '');

page_start('Team & Permissions');
?>

<div class="sm:flex sm:items-center sm:justify-between mb-6">
    <div>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Team & Permissions</h1>
        <p class="mt-1 text-xs sm:text-sm text-slate-500">
            <?php if ($isMasterSuperAdmin && $filterWorkspace === 0): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-extrabold bg-purple-100 text-purple-800 mr-1.5"><i class="fa-solid fa-crown mr-1"></i>Super-Admin Global View</span>
                Managing team members across <strong>All SaaS Workspaces</strong>.
            <?php else: ?>
                Manage user access for <strong><?=e($activeTenant['name'])?></strong>. 
                Quota: <strong class="text-slate-800"><?=$currentUsersCount?> / <?=$isLifetime ? 'Unlimited (Internal)' : $maxUsersAllowed?> Allowed Users</strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="mt-4 sm:mt-0 flex items-center space-x-2">
        <?php if ($isMasterSuperAdmin): ?>
            <a href="tenants_admin" class="inline-flex items-center px-3.5 py-2 border border-purple-300 text-xs font-extrabold rounded-xl text-purple-800 bg-purple-50 hover:bg-purple-100 shadow-xs transition-all">
                <i class="fa-solid fa-building-user mr-1.5 text-purple-600"></i>+ Create Tenant Workspace
            </a>
        <?php endif; ?>
        <?php if ($isLifetime || $currentUsersCount < $maxUsersAllowed || $isMasterSuperAdmin): ?>
            <button onclick="document.getElementById('new-user-modal').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-extrabold rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-md transition-all">
                <i class="fa-solid fa-user-plus mr-1.5"></i>Add Team Member
            </button>
        <?php else: ?>
            <a href="billing" class="inline-flex items-center px-4 py-2 border border-rose-300 text-xs font-extrabold rounded-xl text-rose-700 bg-rose-50 hover:bg-rose-100 shadow-xs">
                <i class="fa-solid fa-lock mr-1.5"></i>User Limit Reached - Upgrade Plan
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 mb-6 text-sm font-semibold flex items-center">
        <i class="fa-solid fa-triangle-exclamation mr-2 text-rose-600"></i><?=e($error)?>
    </div>
<?php endif; ?>

<!-- 🔍 Advanced Search & Filter Bar -->
<form method="get" class="bg-white rounded-2xl border border-slate-200 shadow-xs p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
        <!-- Search Query Input -->
        <div class="<?=$isMasterSuperAdmin ? 'sm:col-span-5' : 'sm:col-span-7'?> relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input type="text" name="q" value="<?=e($filterSearch)?>" placeholder="Search team member by name or email address..." class="w-full pl-9 pr-3.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
        </div>

        <!-- Super-Admin Workspace Selector -->
        <?php if ($isMasterSuperAdmin): ?>
            <div class="sm:col-span-3">
                <select name="workspace_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                    <option value="">🏢 All Workspaces (Global)</option>
                    <?php foreach ($allTenants as $at): ?>
                        <option value="<?=$at['id']?>" <?=$filterWorkspace === (int)$at['id'] ? 'selected' : ''?>><?=e($at['name'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Role Filter Dropdown -->
        <div class="<?=$isMasterSuperAdmin ? 'sm:col-span-2' : 'sm:col-span-3'?>">
            <select name="role" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                <option value="">👑 All Roles</option>
                <option value="owner" <?=$filterRole==='owner'?'selected':''?>>Owner</option>
                <option value="admin" <?=$filterRole==='admin'?'selected':''?>>Admin</option>
                <option value="accountant" <?=$filterRole==='accountant'?'selected':''?>>Accountant</option>
                <option value="sales" <?=$filterRole==='sales'?'selected':''?>>Sales</option>
                <option value="viewer" <?=$filterRole==='viewer'?'selected':''?>>Viewer</option>
            </select>
        </div>

        <!-- Filter Submit / Reset Buttons -->
        <div class="sm:col-span-2 flex items-center space-x-2">
            <button type="submit" class="w-full px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs shadow-xs transition-all flex items-center justify-center">
                <i class="fa-solid fa-filter mr-1.5 text-2xs"></i>Filter
            </button>
            <?php if ($hasActiveFilters): ?>
                <a href="users.php" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold rounded-xl text-xs transition-all flex items-center" title="Reset Filters">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-12">
    <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-base sm:text-lg font-bold text-slate-900">Active Team Members (<?=count($users)?>)</h2>
        <span class="text-xs font-extrabold text-slate-500">
            <?php if ($isMasterSuperAdmin): ?>
                Total Team Members: <?=count($users)?>
            <?php else: ?>
                <?=$currentUsersCount?> / <?=$isLifetime ? 'Unlimited' : $maxUsersAllowed?> Limit
            <?php endif; ?>
        </span>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden sm:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    <th class="px-6 py-3.5">User Name</th>
                    <th class="px-6 py-3.5">Email Address</th>
                    <th class="px-6 py-3.5">Assigned Workspaces</th>
                    <th class="px-6 py-3.5">Role</th>
                    <th class="px-6 py-3.5">Date Added</th>
                    <th class="px-6 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm">
                <?php foreach ($users as $u): 
                    $assignedList = $userTenantMap[$u['id']] ?? [];
                    if (empty($assignedList)) {
                        $assignedList = [['tenant_id' => $u['tenant_id'], 'name' => $u['tenant_workspace_name'] ?: 'Corporate HQ']];
                    }
                    $assignedIds = array_column($assignedList, 'tenant_id');
                ?>
                    <tr class="hover:bg-slate-50/80 transition-all">
                        <td class="px-6 py-4 font-bold text-slate-900 flex items-center space-x-3">
                            <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-600 text-xs">
                                <?=strtoupper(substr($u['name'], 0, 2))?>
                            </div>
                            <span><?=e($u['name'])?></span>
                        </td>
                        <td class="px-6 py-4 text-slate-600"><?=e($u['email'])?></td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1 max-w-xs">
                                <?php foreach ($assignedList as $at): ?>
                                    <span class="px-2 py-0.5 rounded-lg text-2xs font-extrabold bg-slate-100 text-slate-800 border border-slate-200" title="Assigned Workspace">
                                        <i class="fa-solid fa-building text-amber-500 mr-1"></i><?=e($at['name'])?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-3 py-1 rounded-full text-xs font-extrabold bg-amber-100 text-amber-800">
                                <?=strtoupper(e($u['tenant_role'] ?: $u['role']))?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500"><?=e(date('d M Y', strtotime($u['created_at'])))?></td>
                        <td class="px-6 py-4 text-right space-x-1">
                            <button onclick="confirmResendWelcome(<?=$u['id']?>, '<?=e($u['email'])?>')" class="inline-flex items-center p-2 rounded-lg text-slate-500 hover:text-blue-600 hover:bg-blue-50 text-xs transition-all" title="Resend Welcome Email">
                                <i class="fa-solid fa-paper-plane text-sm"></i>
                            </button>
                            <button onclick="confirmSendReset(<?=$u['id']?>, '<?=e($u['email'])?>')" class="inline-flex items-center p-2 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 text-xs transition-all" title="Send Password Reset Link">
                                <i class="fa-solid fa-key text-sm"></i>
                            </button>
                            <button onclick='openEditUserModal(<?=json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT)?>, <?=json_encode($assignedIds)?>)' class="inline-flex items-center p-2 rounded-lg text-slate-500 hover:text-purple-600 hover:bg-purple-50 text-xs transition-all" title="Edit Member">
                                <i class="fa-solid fa-pen-to-square text-sm"></i>
                            </button>
                            <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                                <button onclick="confirmDeleteUser(<?=$u['id']?>, '<?=e($u['name'])?>')" class="inline-flex items-center p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 text-xs transition-all" title="Delete Member">
                                    <i class="fa-solid fa-trash-can text-sm"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch View -->
    <div class="sm:hidden divide-y divide-slate-100">
        <?php foreach ($users as $u): 
            $assignedList = $userTenantMap[$u['id']] ?? [];
            if (empty($assignedList)) {
                $assignedList = [['tenant_id' => $u['tenant_id'], 'name' => $u['tenant_workspace_name'] ?: 'Corporate HQ']];
            }
            $assignedIds = array_column($assignedList, 'tenant_id');
        ?>
            <div class="p-4 hover:bg-slate-50 transition-colors flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="h-9 w-9 rounded-full bg-slate-100 flex items-center justify-center font-bold text-slate-600 text-xs">
                        <?=strtoupper(substr($u['name'], 0, 2))?>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 text-sm"><?=e($u['name'])?></div>
                        <div class="text-2xs text-slate-400"><?=e($u['email'])?> &bull; <?=e(implode(', ', array_column($assignedList, 'name')))?></div>
                    </div>
                </div>
                <div class="flex items-center space-x-1">
                    <span class="px-2.5 py-0.5 rounded-full text-2xs font-extrabold bg-amber-100 text-amber-800">
                        <?=strtoupper(e($u['tenant_role'] ?: $u['role']))?>
                    </span>
                    <button onclick="confirmResendWelcome(<?=$u['id']?>, '<?=e($u['email'])?>')" class="p-1 text-slate-500 hover:text-blue-600" title="Resend Welcome Email">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                    </button>
                    <button onclick="confirmSendReset(<?=$u['id']?>, '<?=e($u['email'])?>')" class="p-1 text-slate-500 hover:text-amber-600" title="Send Password Reset Link">
                        <i class="fa-solid fa-key text-xs"></i>
                    </button>
                    <button onclick='openEditUserModal(<?=json_encode($u, JSON_HEX_APOS|JSON_HEX_QUOT)?>, <?=json_encode($assignedIds)?>)' class="p-1 text-slate-500 hover:text-purple-600">
                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                    </button>
                    <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                        <button onclick="confirmDeleteUser(<?=$u['id']?>, '<?=e($u['name'])?>')" class="p-1 text-slate-400 hover:text-rose-600">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add User Modal (Sleek 2-Column Responsive Layout - Zero Scrollbar Needed!) -->
<div id="new-user-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Add Team Member</h3>
                    <p class="text-2xs text-slate-500 font-medium">Create user account & assign workspace scope</p>
                </div>
            </div>
            <button onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="h-8 w-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center text-lg font-bold transition-all">×</button>
        </div>

        <!-- Form Body -->
        <form method="post" class="p-6 space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="create_user">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Sarah Jenkins" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Email Address *</label>
                    <input type="email" name="email" required placeholder="sarah@company.com" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Password * (min 8 chars)</label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Permission Role *</label>
                    <select name="role" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                        <option value="admin">Admin (Full Access)</option>
                        <option value="accountant" selected>Accountant (Invoices & Reports)</option>
                        <option value="sales">Sales (Proposals & Invoices)</option>
                        <option value="viewer">Viewer (Read Only)</option>
                    </select>
                </div>
            </div>

            <!-- Searchable Multi-Workspace Location Assignment Component -->
            <?php if ($canManageMultipleTenants): ?>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider">Assigned Workspaces / Locations *</label>
                        <div class="flex items-center space-x-2 text-3xs font-extrabold">
                            <button type="button" onclick="selectAllWorkspaces('create', true)" class="text-amber-600 hover:underline">+ Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="selectAllWorkspaces('create', false)" class="text-slate-400 hover:underline">Clear All</button>
                        </div>
                    </div>

                    <!-- Live Search Bar -->
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" onkeyup="filterWorkspaceSearch(this, 'create-workspace-item')" placeholder="Type to search workspace location by name or code..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                    </div>

                    <!-- Searchable Workspace Options List -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1.5">
                        <?php foreach ($accessibleTenants as $at): ?>
                            <label class="create-workspace-item flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200/80 hover:border-amber-400 hover:bg-amber-50/30 cursor-pointer transition-all shadow-2xs" data-name="<?=e(strtolower($at['name']))?>" data-code="<?=e(strtolower($at['code']))?>">
                                <div class="flex items-center space-x-2.5">
                                    <input type="checkbox" name="target_tenant_ids[]" value="<?=$at['id']?>" class="create-user-tenant-checkbox rounded text-amber-600 focus:ring-amber-500 w-4 h-4" <?=$at['id']==$tid?'checked':''?>>
                                    <span class="text-xs font-bold text-slate-900"><?=e($at['name'])?></span>
                                </div>
                                <span class="text-3xs font-extrabold px-2 py-0.5 bg-slate-100 text-slate-500 rounded-md">code: <?=e($at['code'])?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-3xs text-slate-400 mt-1 font-medium">Member will be able to easily switch between all checked workspace locations.</p>
                </div>
            <?php else: ?>
                <input type="hidden" name="target_tenant_ids[]" value="<?=$tid?>">
            <?php endif; ?>

            <!-- Scope Type Selection (Super-Admin Only) -->
            <?php if ($isMasterSuperAdmin): ?>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Account Access Scope</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center space-x-3 bg-slate-50/80 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:border-amber-400 hover:bg-amber-50/30 transition-all">
                            <input type="radio" name="account_scope" value="subaccount" checked class="w-4 h-4 text-amber-600 focus:ring-amber-500">
                            <div>
                                <div class="text-xs font-extrabold text-slate-900">Sub-Account Member</div>
                                <div class="text-3xs font-semibold text-slate-500">Scoped role permissions</div>
                            </div>
                        </label>
                        <label class="flex items-center space-x-3 bg-slate-50/80 p-2.5 rounded-xl border border-slate-200 cursor-pointer hover:border-purple-400 hover:bg-purple-50/30 transition-all">
                            <input type="radio" name="account_scope" value="tenant_admin" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                            <div>
                                <div class="text-xs font-extrabold text-slate-900">Tenant Admin</div>
                                <div class="text-3xs font-semibold text-slate-500">Full tenant administration</div>
                            </div>
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('new-user-modal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg shadow-amber-500/20 transition-all">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-md flex items-center justify-center z-[100] hidden p-4">
    <div class="bg-white rounded-3xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50/80">
            <div class="flex items-center space-x-3">
                <div class="h-10 w-10 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-600 flex items-center justify-center text-lg font-bold">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-900 tracking-tight">Edit Team Member</h3>
                    <p class="text-2xs text-slate-500 font-medium">Update member permissions & workspace assignment</p>
                </div>
            </div>
            <button onclick="document.getElementById('edit-user-modal').classList.add('hidden')" class="h-8 w-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center text-lg font-bold transition-all">×</button>
        </div>

        <form method="post" class="p-6 space-y-4">
            <?=csrf_field()?>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit-user-id">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Full Name *</label>
                    <input type="text" name="name" id="edit-user-name" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Email Address *</label>
                    <input type="email" name="email" id="edit-user-email" required class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Reset Password (Optional)</label>
                    <input type="password" name="password" placeholder="Leave blank to keep current" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider mb-1.5">Permission Role *</label>
                    <select name="role" id="edit-user-role" class="w-full rounded-xl border border-slate-300 bg-slate-50/80 px-3.5 py-2 text-xs font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                        <option value="admin">Admin (Full Access)</option>
                        <option value="accountant">Accountant (Invoices & Reports)</option>
                        <option value="sales">Sales (Proposals & Invoices)</option>
                        <option value="viewer">Viewer (Read Only)</option>
                    </select>
                </div>
            </div>

            <!-- Searchable Multi-Workspace Location Assignment Component (Edit Modal) -->
            <?php if ($canManageMultipleTenants): ?>
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-2xs font-extrabold text-slate-700 uppercase tracking-wider">Assigned Workspaces / Locations *</label>
                        <div class="flex items-center space-x-2 text-3xs font-extrabold">
                            <button type="button" onclick="selectAllWorkspaces('edit', true)" class="text-amber-600 hover:underline">+ Select All</button>
                            <span class="text-slate-300">|</span>
                            <button type="button" onclick="selectAllWorkspaces('edit', false)" class="text-slate-400 hover:underline">Clear All</button>
                        </div>
                    </div>

                    <!-- Live Search Bar -->
                    <div class="relative mb-2">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" onkeyup="filterWorkspaceSearch(this, 'edit-workspace-item')" placeholder="Type to search workspace location by name or code..." class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:bg-white focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 outline-none">
                    </div>

                    <!-- Searchable Workspace Options List -->
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-2 max-h-40 overflow-y-auto space-y-1.5">
                        <?php foreach ($accessibleTenants as $at): ?>
                            <label class="edit-workspace-item flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200/80 hover:border-amber-400 hover:bg-amber-50/30 cursor-pointer transition-all shadow-2xs" data-name="<?=e(strtolower($at['name']))?>" data-code="<?=e(strtolower($at['code']))?>">
                                <div class="flex items-center space-x-2.5">
                                    <input type="checkbox" name="target_tenant_ids[]" value="<?=$at['id']?>" class="edit-user-tenant-checkbox rounded text-amber-600 focus:ring-amber-500 w-4 h-4">
                                    <span class="text-xs font-bold text-slate-900"><?=e($at['name'])?></span>
                                </div>
                                <span class="text-3xs font-extrabold px-2 py-0.5 bg-slate-100 text-slate-500 rounded-md">code: <?=e($at['code'])?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="target_tenant_ids[]" value="<?=$tid?>">
            <?php endif; ?>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end space-x-3">
                <button type="button" onclick="document.getElementById('edit-user-modal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 shadow-lg shadow-amber-500/20 transition-all">Save Member Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Resend Welcome Modal Form -->
<form id="resend-welcome-form" method="post" class="hidden">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="resend_welcome">
    <input type="hidden" name="user_id" id="resend-welcome-user-id">
</form>

<!-- Send Reset Password Modal Form -->
<form id="send-reset-form" method="post" class="hidden">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="send_password_reset">
    <input type="hidden" name="user_id" id="send-reset-user-id">
</form>

<!-- Delete User Modal Form -->
<form id="delete-user-form" method="post" class="hidden">
    <?=csrf_field()?>
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="user_id" id="delete-user-id">
</form>

<script>
function filterWorkspaceSearch(inputEl, itemClass) {
    const query = inputEl.value.toLowerCase().trim();
    document.querySelectorAll('.' + itemClass).forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const code = item.getAttribute('data-code') || '';
        if (!query || name.includes(query) || code.includes(query)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectAllWorkspaces(modalType, checkAll) {
    const selector = modalType === 'create' ? '.create-user-tenant-checkbox' : '.edit-user-tenant-checkbox';
    document.querySelectorAll(selector).forEach(cb => {
        const parent = cb.closest('label');
        if (!parent || parent.style.display !== 'none') {
            cb.checked = checkAll;
        }
    });
}

function openEditUserModal(user, assignedTenantIds) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-name').value = user.name;
    document.getElementById('edit-user-email').value = user.email;
    document.getElementById('edit-user-role').value = user.tenant_role || user.role || 'accountant';

    assignedTenantIds = (assignedTenantIds || []).map(id => parseInt(id));
    if (assignedTenantIds.length === 0 && user.tenant_id) {
        assignedTenantIds = [parseInt(user.tenant_id)];
    }

    document.querySelectorAll('.edit-user-tenant-checkbox').forEach(cb => {
        cb.checked = assignedTenantIds.includes(parseInt(cb.value));
    });

    document.getElementById('edit-user-modal').classList.remove('hidden');
}

function confirmResendWelcome(userId, userEmail) {
    if (confirm("Resend account welcome email to " + userEmail + "?")) {
        document.getElementById('resend-welcome-user-id').value = userId;
        document.getElementById('resend-welcome-form').submit();
    }
}

function confirmSendReset(userId, userEmail) {
    if (confirm("Send password reset token link email to " + userEmail + "?")) {
        document.getElementById('send-reset-user-id').value = userId;
        document.getElementById('send-reset-form').submit();
    }
}

function confirmDeleteUser(userId, userName) {
    if (confirm("Are you sure you want to remove team member '" + userName + "' from this workspace?")) {
        document.getElementById('delete-user-id').value = userId;
        document.getElementById('delete-user-form').submit();
    }
}
</script>

<?php page_end(); ?>



