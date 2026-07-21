<?php
session_start();
require_once __DIR__ . '/../db.php';

$channel_name = $_GET['name'] ?? '';

// Fetch channel info
$stmt = $db->prepare("SELECT * FROM subchannels WHERE name = ?");
$stmt->execute([$channel_name]);
$channel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$channel) {
    http_response_code(404);
    die("Subchannel not found.");
}

// Fetch posts for this specific channel
$stmt_posts = $db->prepare("
    SELECT p.*, u.username, u.role 
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.subchannel_id = ? 
    ORDER BY p.created_at DESC
");
$stmt_posts->execute([$channel['id']]);
$posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 800px; margin: 2rem auto; padding: 20px; }
        .channel-header { background: #1a1a1a; border: 1px solid #333; padding: 20px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .post-card { background: #1a1a1a; border: 1px solid #333; padding: 15px; margin-bottom: 1rem; }
        .post-title { color: whitesmoke; text-decoration: none; font-size: 1.2rem; font-weight: bold; }
        .post-title:hover { text-decoration: underline; }
        .btn { background: #333; color: whitesmoke; border: 1px solid #555; padding: 8px 14px; text-decoration: none; cursor: pointer; }
        .btn:hover { background: #444; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="channel-header">
            <div>
                <h2 style="color: #00ffcc; margin: 0 0 5px 0;">c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p style="margin: 0; color: #aaa; font-size: 0.9rem;"><?php echo htmlspecialchars($channel['description'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <div>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="btn" href="/create_post.php?channel=<?php echo urlencode($channel['name']); ?>">Create Post</a>
                <?php endif; ?>
            </div>
        </div>

        <h3>Posts</h3>
        <hr style="border: 0; border-top: 1px solid #333; margin: 1rem 0;">

        <?php if (empty($posts)): ?>
            <p style="color: gray;">No posts in this channel yet.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post-card">
                    <div style="font-size: 0.85rem; color: #888; margin-bottom: 8px;">
                        Posted by 
                        <a href="/u/<?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>" style="color: <?php echo getRoleColor($post['role']); ?>; text-decoration: none;">
                            <?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </a> 
                        at <?php echo $post['created_at']; ?>
                    </div>
                    
                    <a class="post-title" href="/post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                    
                    <div style="margin-top: 10px; font-size: 0.9rem; color: #ccc;">
                        Votes: <?php echo $post['votes_count']; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>