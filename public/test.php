<?php
require_once __DIR__ . '/../db.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/u/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    $_GET['user'] = $matches[1];
    require __DIR__ . '/profile.php';
    exit;
}

if (preg_match('#^/c/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    $_GET['name'] = $matches[1];
    require __DIR__ . '/channel.php';
    exit;
}

$channel_search = trim($_GET['c_search'] ?? '');
if (!empty($channel_search)) {
    $stmt_channels = $db->prepare("SELECT name, description FROM subchannels WHERE name LIKE ? ORDER BY name ASC");
    $stmt_channels->execute(['%' . $channel_search . '%']);
} else {
    $stmt_channels = $db->query("SELECT name, description FROM subchannels ORDER BY name ASC");
}
$channels = $stmt_channels->fetchAll(PDO::FETCH_ASSOC);

$current_logged_in_user = $_SESSION['user_id'] ?? '';
$current_role = $_SESSION['role'] ?? '';
$stmt = $db->prepare("
    SELECT p.*, u.username, u.role, c.name as channel_name,
    (SELECT vote_type FROM votes WHERE user_id = ? AND post_id = p.id) as user_vote
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    JOIN subchannels c ON p.subchannel_id = c.id 
    ORDER BY p.created_at DESC 
    LIMIT 20
");
$stmt->execute([$current_logged_in_user]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ANG3L Forum</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <link rel="stylesheet" type="text/css" href="/css/index.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="wrapper">
        <div class="feed-container">
            <h2>Feed</h2>
            <hr style="border: 0; border-top: 1px solid #333; margin: 1rem 0;">

            <?php if (empty($posts)): ?>
                <p style="color: gray;">No posts found yet. Be the first to post!</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        <div class="vote-section">
                            <form action="/vtest.php" method="POST" style="display: inline;">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="vote_type" value="1">
                                <button type="submit" class="vote-btn upvote <?php echo ($post['user_vote'] == 1) ? 'active' : ''; ?>">▲</button>
                            </form>

                            <span class="vote-count" id="vote-count-<?php echo $post['id']; ?>"><?php echo $post['votes_count']; ?></span>

                            <form action="/vtest.php" method="POST" style="display: inline;">
                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="vote_type" value="-1">
                                <button type="submit" class="vote-btn downvote <?php echo ($post['user_vote'] == -1) ? 'active' : ''; ?>">▼</button>
                            </form>
                        </div>

                        <div class="post-content">
                            <div class="meta">
                                <a class="channel-link" href="/c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?>">c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?></a> 
                                • Posted by 
                                <a href="/u/<?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>" style="color: <?php echo getRoleColor($post['role']); ?>; text-decoration: none;">
                                    <?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>
                                </a> 
                                at <?php echo $post['created_at']; ?>
                            </div>
                            
                            <a class="post-title" href="/post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></a>

                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] === $post['user_id'] || $current_role === 'admin')): ?>
                                <div style="margin-top: 0.5rem;">
                                    <form action="/delete_post.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" style="background: none; border: none; color: #ff5555; cursor: pointer; font-size: 0.8rem; padding: 0;">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="sidebar">
            <h3>Channels</h3>
            
            <form method="GET" action="index.php">
                <input type="text" name="c_search" placeholder="Search channels..." value="<?php echo htmlspecialchars($channel_search, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">Filter</button>
            </form>
            
            <?php if (!empty($channel_search)): ?>
                <div style="font-size: 0.8rem; margin-bottom: 10px; color: #aaa;">
                    Filtered by "<?php echo htmlspecialchars($channel_search, ENT_QUOTES, 'UTF-8'); ?>" 
                    <a href="index.php" style="color: #00ffcc; text-decoration: none; margin-left: 5px;">[Reset]</a>
                </div>
            <?php endif; ?>

            <ul class="channel-list">
                <?php if (empty($channels)): ?>
                    <li style="color: gray; font-size: 0.85rem; text-align: center; padding: 10px 0;">No channels found.</li>
                <?php else: ?>
                    <?php foreach ($channels as $chan): ?>
                        <li>
                            <a class="sidebar-c-link" href="/c/<?php echo htmlspecialchars($chan['name'], ENT_QUOTES, 'UTF-8'); ?>">c/<?php echo htmlspecialchars($chan['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php if (!empty($chan['description'])): ?>
                                <p class="channel-desc"><?php echo htmlspecialchars($chan['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            
            <?php if (in_array($_SESSION['role'] ?? '', ['beta_tester', 'legends', 'mods', 'admin'])): ?>
                <div style="margin-top: 1.5rem; text-align: center;">
                    <a href="/create_channel.php" style="display: block; background: #222; color: pink; border: 1px dashed pink; padding: 8px; text-decoration: none; font-size: 0.85rem;">+ Create New Channel</a>
                </div>
            <?php endif; ?>

        </div>
    </div>
</body>
</html>