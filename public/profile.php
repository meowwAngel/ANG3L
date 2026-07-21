<?php
session_start();
require_once __DIR__ . '/../db.php';

$username = strtolower(trim($_GET['user'] ?? ''));

// Fetch user profile info case-insensitively including karma
$stmt = $db->prepare("SELECT id, username, role, karma, created_at FROM users WHERE LOWER(username) = ?");
$stmt->execute([$username]);
$profile_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile_user) {
    http_response_code(404);
    die("User not found.");
}

// Fetch posts by this user
$stmt_posts = $db->prepare("SELECT p.*, c.name as channel_name FROM posts p JOIN subchannels c ON p.subchannel_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
$stmt_posts->execute([$profile_user['id']]);
$user_posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profile: <?php echo htmlspecialchars($profile_user['username'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 800px; margin: 2rem auto; padding: 20px; background: #1a1a1a; border: 1px solid #333; }
        .post-card { background: #111; border: 1px solid #333; padding: 15px; margin-top: 1rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <h2 style="color: <?php echo getRoleColor($profile_user['role']); ?>;">
            <?php echo htmlspecialchars($profile_user['username'], ENT_QUOTES, 'UTF-8'); ?> 
            <span style="font-size: 0.9rem; color: #888;">[<?php echo htmlspecialchars($profile_user['role'], ENT_QUOTES, 'UTF-8'); ?>]</span>
        </h2>
        <p><strong>Karma:</strong> <?php echo $profile_user['karma']; ?></p>
        <p style="font-size: 0.85rem; color: #888;">Member since: <?php echo $profile_user['created_at']; ?></p>

        <hr style="border: 0; border-top: 1px solid #333; margin: 1.5rem 0;">
        
        <h3>Posts by <?php echo htmlspecialchars($profile_user['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <?php if (empty($user_posts)): ?>
            <p style="color: gray;">No posts yet.</p>
        <?php else: ?>
            <?php foreach ($user_posts as $post): ?>
                <div class="post-card">
                    <small style="color: #00ffcc;"><a href="/c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?>" style="color: #00ffcc; text-decoration: none;">c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?></a></small>
                    <h4 style="margin: 5px 0;"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p style="color: #ccc;"><?php echo nl2br(htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <small style="color: gray;">Votes: <?php echo $post['votes_count']; ?> | <?php echo $post['created_at']; ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>