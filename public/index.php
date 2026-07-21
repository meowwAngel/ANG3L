<?php
session_start();
require_once __DIR__ . '/../db.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Intercept /u/username requests
if (preg_match('#^/u/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    $_GET['user'] = $matches[1];
    require __DIR__ . '/profile.php';
    exit;
}

// 2. Intercept /c/channelname requests
if (preg_match('#^/c/([a-zA-Z0-9_-]+)/?$#', $uri, $matches)) {
    $_GET['name'] = $matches[1];
    require __DIR__ . '/channel.php';
    exit;
}

// 3. Handle Channel Search Query from Sidebar
$channel_search = trim($_GET['c_search'] ?? '');
if (!empty($channel_search)) {
    $stmt_channels = $db->prepare("SELECT name, description FROM subchannels WHERE name LIKE ? ORDER BY name ASC");
    $stmt_channels->execute(['%' . $channel_search . '%']);
} else {
    $stmt_channels = $db->query("SELECT name, description FROM subchannels ORDER BY name ASC");
}
$channels = $stmt_channels->fetchAll(PDO::FETCH_ASSOC);

// 4. Default Homepage Global Feed Code (Includes Vote States)
$current_logged_in_user = $_SESSION['user_id'] ?? '';
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
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .wrapper { width: 100%; max-width: none; margin: 0; padding: 2rem 20px; display: grid; grid-template-columns: 1fr 300px; gap: 2rem; align-items: start; box-sizing: border-box; }
        .feed-container { background: transparent; width: 100%; min-width: 0; margin: 0; padding: 0; }
        .post-card { display: flex; gap: 15px; background: #1a1a1a; border: 1px solid #333; padding: 15px; margin-bottom: 1rem; align-items: center; }
        .post-title { color: whitesmoke; text-decoration: none; font-size: 1.2rem; font-weight: bold; word-break: break-word; }
        .post-title:hover { text-decoration: underline; }
        .meta { font-size: 0.85rem; color: #888; margin-bottom: 8px; }
        .channel-link { color: #00ffcc; text-decoration: none; }
        .vote-section { display: flex; flex-direction: column; align-items: center; min-width: 30px; }
        .vote-btn { background: none; border: none; color: #666; cursor: pointer; font-size: 1rem; padding: 0; line-height: 1; }
        .vote-btn:hover { color: whitesmoke; }
        .vote-btn.upvote.active { color: #00ff66; }
        .vote-btn.downvote.active { color: #ff4d4d; }
        .vote-count { font-size: 0.9rem; font-weight: bold; color: #ccc; margin: 4px 0; }
        .post-content { flex: 1; min-width: 0; }
        .sidebar { background: #1a1a1a; border: 1px solid #333; padding: 20px; position: sticky; top: 80px; }
        .sidebar h3 { margin-top: 0; color: #00ffcc; font-size: 1.1rem; border-bottom: 1px solid #333; padding-bottom: 10px; }
        .sidebar form { display: flex; gap: 5px; margin-bottom: 1rem; }
        .sidebar input[type="text"] { flex: 1; background: #000; color: whitesmoke; border: 1px solid #444; padding: 6px; font-family: inherit; font-size: 0.85rem; }
        .sidebar button { background: #333; color: whitesmoke; border: 1px solid #555; padding: 6px 10px; cursor: pointer; font-family: inherit; }
        .sidebar button:hover { background: #444; }
        .channel-list { list-style: none; padding: 0; margin: 0; max-height: 400px; overflow-y: auto; }
        .channel-list li { padding: 8px 0; border-bottom: 1px solid #222; }
        .channel-list li:last-child { border-bottom: none; }
        .sidebar-c-link { color: #00ffcc; text-decoration: none; font-weight: bold; font-size: 0.95rem; display: block; }
        .sidebar-c-link:hover { text-decoration: underline; }
        .channel-desc { font-size: 0.75rem; color: #888; margin: 2px 0 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 768px) {
            .wrapper { grid-template-columns: 1fr; }
            .sidebar { position: static; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="wrapper">
        <div class="feed-container">
            <h2>Global Feed</h2>
            <hr style="border: 0; border-top: 1px solid #333; margin: 1rem 0;">

            <?php if (empty($posts)): ?>
                <p style="color: gray;">No posts found yet. Be the first to post!</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        <!-- Vote Column -->
                        <div class="vote-section">
                            <button class="vote-btn upvote <?php echo ($post['user_vote'] == 1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', 1)">▲</button>
                            <span class="vote-count" id="vote-count-<?php echo $post['id']; ?>"><?php echo $post['votes_count']; ?></span>
                            <button class="vote-btn downvote <?php echo ($post['user_vote'] == -1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', -1)">▼</button>
                        </div>

                        <!-- Post Content -->
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
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="sidebar">
            <h3>Subchannels</h3>
            
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
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <div style="margin-top: 1.5rem; text-align: center;">
                    <a href="/create_channel.php" style="display: block; background: #222; color: #00ffcc; border: 1px dashed #00ffcc; padding: 8px; text-decoration: none; font-size: 0.85rem;">+ Create New Channel</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    async function castVote(postId, voteType) {
        try {
            let response = await fetch('/vote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ post_id: postId, vote_type: voteType })
            });
            
            let data = await response.json();
            if (data.success) {
                document.getElementById('vote-count-' + postId).innerText = data.new_votes_count;
                
                let card = document.querySelector(`[data-post-id="${postId}"]`);
                let upBtn = card.querySelector('.upvote');
                let downBtn = card.querySelector('.downvote');
                
                upBtn.classList.remove('active');
                downBtn.classList.remove('active');
                
                if (data.user_vote === 1) {
                    upBtn.classList.add('active');
                } else if (data.user_vote === -1) {
                    downBtn.classList.add('active');
                }
            } else {
                alert(data.error || 'You must be logged in to vote.');
            }
        } catch (err) {
            console.error('Voting failed:', err);
        }
    }
    </script>
</body>
</html>