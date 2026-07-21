<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_username)) {
        $error = "Username cannot be empty.";
    } elseif (empty($current_password)) {
        $error = "You must enter your current password to make changes.";
    } else {
        $stmt = $db->prepare("SELECT username, password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $current_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_user || !password_verify($current_password, $current_user['password_hash'])) {
            $error = "Incorrect current password.";
        } else {
            try {

                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        throw new Exception("New passwords do not match.");
                    }
                    if (strlen($new_password) < 6) {
                        throw new Exception("New password must be at least 6 characters long.");
                    }

                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_username, $new_hash, $user_id]);
                } else {
                    // Update username only
                    $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$new_username, $user_id]);
                }

                $_SESSION['username'] = $new_username;
                $message = "Settings updated successfully!";

            } catch (Exception $e) {
                $error = $e->getMessage() ?: "Error updating settings. That username might already be taken.";
            }
        }
    }
}

$stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Account Settings - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .settings-container { max-width: 600px; margin: 3rem auto; padding: 25px; background: #1a1a1a; border: 1px solid #333; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #ccc; }
        input[type="text"], input[type="password"] { width: 100%; background: #000; color: whitesmoke; border: 1px solid #444; padding: 8px; box-sizing: border-box; }
        button { background: #333; color: whitesmoke; border: 1px solid #555; padding: 8px 16px; cursor: pointer; }
        button:hover { background: #444; }
        .alert-success { color: #00ffcc; background: #003322; padding: 10px; margin-bottom: 1rem; border: 1px solid #00ffcc; }
        .alert-error { color: #ff4d4d; background: #330000; padding: 10px; margin-bottom: 1rem; border: 1px solid #ff4d4d; }
        .info-text { font-size: 0.85rem; color: #888; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="settings-container">
        <h2>Account Settings</h2>
        <p class="info-text">Current Role: <strong><?php echo htmlspecialchars($user['role'] ?? 'member', ENT_QUOTES, 'UTF-8'); ?></strong></p>
        <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">

        <?php if (!empty($message)): ?>
            <div class="alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="settings.php" method="POST">
            <div class="form-group">
                <label>Username:</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">
            <h3>Change Password</h3>
            <p class="info-text">Leave password fields blank if you do not want to change your password.</p>

            <div class="form-group" style="margin-top: 1rem;">
                <label>New Password (Optional):</label>
                <input type="password" name="new_password" placeholder="At least 6 characters">
            </div>

            <div class="form-group">
                <label>Confirm New Password:</label>
                <input type="password" name="confirm_password" placeholder="Re-type new password">
            </div>

            <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">

            <div class="form-group">
                <label>Current Password <span style="color: #ff4d4d;">*</span>:</label>
                <input type="password" name="current_password" placeholder="Required to save any changes" required>
                <div class="info-text">For your security, enter your current password to authorize updates.</div>
            </div>

            <button type="submit">Save Changes</button>
        </form>
    </div>
</body>
</html>