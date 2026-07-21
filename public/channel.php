<?php
session_start();
require_once __DIR__ . '/../db.php';

$name = $_GET['name'] ?? '';

$stmt_chan = $db->prepare("SELECT * FROM subchannels WHERE name = ?");
$stmt_chan->execute([$name]);
$channel = $stmt_chan->fetch(PDO::FETCH_ASSOC);

if (!$channel) {
    http_response_code(404);
    echo "Channel not found.";
    exit;
}

$current_logged_in_user = $_SESSION['user_id'] ?? '';
$current_role = $_SESSION['role'] ?? '';

$stmt_posts = $db->prepare("
    SELECT p.*, u.username, u.role, c.name as channel_name,
    (SELECT vote_type FROM votes WHERE user_id = ? AND post_id = p.id) as user_vote
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    JOIN subchannels c ON p.subchannel_id = c.id 
    WHERE p.subchannel_id = ?
    ORDER BY p.created_at DESC
");
$stmt_posts->execute([$current_logged_in_user, $channel['id']]);
$posts = $stmt_posts->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L Forum</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <link rel="stylesheet" type="text/css" href="/css/index.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="wrapper">
        <div class="feed-container">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h2>c/<?php echo htmlspecialchars($channel['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/create_post.php?channel=<?php echo urlencode($channel['name']); ?>" style="background: #00ffcc; color: #000; padding: 6px 12px; text-decoration: none; font-size: 0.85rem; border-radius: 4px; font-weight: bold;">+ Create Post</a>
                    <?php endif; ?>
                    <?php if ($current_role === 'admin'): ?>
                        <form action="/delete_channel.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this channel and all its posts?');" style="margin: 0;">
                            <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                            <button type="submit" style="background: #ff5555; color: white; border: none; padding: 6px 12px; cursor: pointer; font-size: 0.85rem; border-radius: 4px;">Delete Channel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($channel['description'])): ?>
                <p style="color: #aaa; margin-top: 5px; font-size: 0.9rem;"><?php echo htmlspecialchars($channel['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <hr style="border: 0; border-top: 1px solid #333; margin: 1rem 0;">

            <?php if (empty($posts)): ?>
                <p style="color: gray;">No posts in this channel yet.</p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
                        <div class="vote-section">
                            <button class="vote-btn upvote <?php echo ($post['user_vote'] == 1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', 1)">▲</button>
                            <span class="vote-count" id="vote-count-<?php echo $post['id']; ?>"><?php echo $post['votes_count']; ?></span>
                            <button class="vote-btn downvote <?php echo ($post['user_vote'] == -1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', -1)">▼</button>
                        </div>

                        <div class="post-content">
                            <div class="meta">
                                Posted by 
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
            <h3>About Channel</h3>
            <p style="color: #aaa; font-size: 0.85rem;">Created on <?php echo $channel['created_at'] ?? 'N/A'; ?></p>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="/index.php" style="display: block; background: #222; color: #00ffcc; border: 1px dashed #00ffcc; padding: 8px; text-decoration: none; font-size: 0.85rem;">← Back to Global Feed</a>
            </div>
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