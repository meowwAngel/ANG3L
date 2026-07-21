<?php
session_start();
require_once __DIR__ . '/../db.php';

// 1. Strict Security: Must be logged in and have the 'admin' role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Access Denied. Admins only.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = "";
$error = "";

// 2. Handle Admin Actions (Update Role / Update Karma / Delete User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $error = "CSRF validation failed. Action blocked.";
    } else {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? '';

        if ($target_user_id === intval($_SESSION['user_id']) && $action === 'delete') {
            $error = "You cannot delete your own admin account.";
        } else {
            if ($action === 'update_role') {
                $new_role = trim($_POST['role'] ?? '');
                $valid_roles = ['member', 'beta_tester', 'mods', 'legends', 'admin'];
                
                if (in_array($new_role, $valid_roles)) {
                    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $target_user_id]);
                    $success = "User role successfully updated.";
                } else {
                    $error = "Invalid role selected.";
                }
            } elseif ($action === 'update_karma') {
                $new_karma = intval($_POST['karma'] ?? 0);
                $stmt = $db->prepare("UPDATE users SET karma = ? WHERE id = ?");
                $stmt->execute([$new_karma, $target_user_id]);
                $success = "User karma successfully updated.";
            } elseif ($action === 'delete') {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$target_user_id]);
                $success = "User account deleted successfully.";
            }
        }
    }
}

// 3. Search & Fetch Users
$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $stmt = $db->prepare("SELECT id, username, role, karma, created_at FROM users WHERE username LIKE ? ORDER BY id DESC");
    $stmt->execute(['%' . $search . '%']);
} else {
    $stmt = $db->query("SELECT id, username, role, karma, created_at FROM users ORDER BY id DESC LIMIT 50");
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 1000px; margin: 2rem auto; padding: 20px; background: #1a1a1a; border: 1px solid #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 10px; border: 1px solid #333; text-align: left; font-size: 0.9rem; }
        th { background: #111; color: #00ffcc; }
        input[type="text"], input[type="number"], select { background: #000; color: whitesmoke; border: 1px solid #444; padding: 5px; }
        button { background: #333; color: whitesmoke; border: 1px solid #555; padding: 5px 10px; cursor: pointer; }
        button:hover { background: #444; }
        .btn-danger { background: #330000; border-color: #ff4d4d; color: #ff4d4d; }
        .btn-danger:hover { background: #550000; }
        .alert-success { color: #00ff66; background: #003311; padding: 10px; margin-bottom: 1rem; border: 1px solid #00ff66; }
        .alert-error { color: #ff4d4d; background: #330000; padding: 10px; margin-bottom: 1rem; border: 1px solid #ff4d4d; }
        .search-box { margin-bottom: 1.5rem; display: flex; gap: 10px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <h2>Admin Management Panel</h2>
        <p style="color: #888; font-size: 0.85rem;">Full database control interface restricted to administrators.</p>
        <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">

        <?php if (!empty($success)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Search Bar -->
        <form class="search-box" method="GET" action="admin.php">
            <input type="text" name="search" placeholder="Search username..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Search</button>
            <?php if (!empty($search)): ?>
                <a href="admin.php" style="color: #aaa; align-self: center; text-decoration: none; font-size: 0.85rem;">Clear</a>
            <?php endif; ?>
        </form>

        <!-- User Management Table -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Karma</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="6" style="text-align: center; color: gray;">No users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><a href="/u/<?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>" style="color: #00ffcc; text-decoration: none;"><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                            
                            <!-- Role Update Form -->
                            <td>
                                <form action="admin.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="update_role">
                                    <select name="role" onchange="this.form.submit()">
                                        <option value="member" <?php if($u['role']=='member') echo 'selected'; ?>>member</option>
                                        <option value="beta_tester" <?php if($u['role']=='beta_tester') echo 'selected'; ?>>beta_tester</option>
                                        <option value="mods" <?php if($u['role']=='mods') echo 'selected'; ?>>mods</option>
                                        <option value="legends" <?php if($u['role']=='legends') echo 'selected'; ?>>legends</option>
                                        <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>admin</option>
                                    </select>
                                </form>
                            </td>

                            <!-- Karma Update Form -->
                            <td>
                                <form action="admin.php" method="POST" style="display:inline; display:flex; gap:5px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="update_karma">
                                    <input type="number" name="karma" value="<?php echo $u['karma']; ?>" style="width: 70px;">
                                    <button type="submit">Save</button>
                                </form>
                            </td>

                            <td style="color: #888; font-size: 0.8rem;"><?php echo $u['created_at']; ?></td>

                            <!-- Delete User -->
                            <td>
                                <form action="admin.php" method="POST" onsubmit="return confirm('Are you sure you want to delete user <?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>?');" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>