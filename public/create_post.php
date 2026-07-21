<?php
session_start();
require_once __DIR__ . '/../db.php';

// 1. Strict Security: Must be logged in to post
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$channel_name = $_GET['channel'] ?? '';

// Fetch subchannel ID
$stmt = $db->prepare("SELECT id, name FROM subchannels WHERE name = ?");
$stmt->execute([$channel_name]);
$channel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$channel) {
    http_response_code(404);
    die("Subchannel not found.");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if (empty($title) || empty($body)) {
        $error = "Title and body content cannot be empty.";
    } else {
        $stmt = $db->prepare("INSERT INTO posts (subchannel_id, user_id, title, body) VALUES (?, ?, ?, ?)");
        $stmt->execute([$channel['id'], $_SESSION['user_id'], $title, $body]);
        
        header("Location: /c/" . urlencode($channel['name']));
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Post in c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 700px; margin: 3rem auto; padding: 25px; background: #1a1a1a; border: 1px solid #333; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; color: #ccc; }
        input[type="text"], textarea { width: 100%; background: #000; color: whitesmoke; border: 1px solid #444; padding: 8px; box-sizing: border-box; }
        textarea { height: 150px; resize: vertical; }
        button { background: #333; color: whitesmoke; border: 1px solid #555; padding: 8px 16px; cursor: pointer; }
        button:hover { background: #444; }
        .alert-error { color: #ff4d4d; background: #330000; padding: 10px; margin-bottom: 1rem; border: 1px solid #ff4d4d; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <h2>Create Post in <span style="color: #00ffcc;">c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></span></h2>
        <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form action="create_post.php?channel=<?php echo urlencode($channel['name']); ?>" method="POST">
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
                <label>Body Text:</label>
                <textarea name="body" required></textarea>
            </div>

            <button type="submit">Submit Post</button>
        </form>
    </div>
</body>
</html>