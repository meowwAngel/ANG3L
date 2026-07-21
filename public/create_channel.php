<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$allowed_roles = ['beta_tester', 'legends', 'mods', 'admin'];
$user_role = $_SESSION['role'] ?? 'member';

if (!in_array($user_role, $allowed_roles)) {
    header("Location: /index.php?error=unauthorized_channel_creation");
    exit;
}

$error = "";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $name));
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $error = "Channel name cannot be empty.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO subchannels (name, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $_SESSION['user_id']]);
            header("Location: /c/" . urlencode($name));
            exit;
        } catch (PDOException $e) {
            $error = "Error: That channel name already exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Subchannel | ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 600px; margin: 3rem auto; padding: 25px; background: #1a1a1a; border: 1px solid #333; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #ccc; }
        input[type="text"], textarea { width: 100%; background: #000; color: whitesmoke; border: 1px solid #444; padding: 8px; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; }
        button { background: #333; color: whitesmoke; border: 1px solid #555; padding: 8px 16px; cursor: pointer; }
        button:hover { background: #444; }
        .alert-error { color: #ff4d4d; background: #330000; padding: 10px; margin-bottom: 1rem; border: 1px solid #ff4d4d; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <h2>Create a New Subchannel</h2>
        <p style="color: #888; font-size: 0.85rem;">Unlocked by your <strong>[<?php echo htmlspecialchars($user_role, ENT_QUOTES, 'UTF-8'); ?>]</strong> status.</p>
        <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="create_channel.php" method="POST">
            <div class="form-group">
                <label>Channel Name (alphanumeric, dashes, underscores):</label>
                <div style="display: flex; align-items: center; background: #000; border: 1px solid #444;">
                    <span style="padding: 8px; color: #888; border-right: 1px solid #444;">c/</span>
                    <input type="text" name="name" placeholder="programming" required style="border: none;">
                </div>
            </div>

            <div class="form-group">
                <label>Description (Optional):</label>
                <textarea name="description" placeholder="What is this channel about?"></textarea>
            </div>

            <button type="submit">Create Channel</button>
        </form>
    </div>
</body>
</html>