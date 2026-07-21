<?php
session_start();
require_once __DIR__ . '/../db.php';

$post_id = $_GET['id'] ?? '';

if (empty($post_id)) {
    http_response_code(404);
    die("Post not found.");
}

// Fetch the post along with channel, author info, and the current user's vote state
$current_logged_in_user = $_SESSION['user_id'] ?? '';
$stmt = $db->prepare("
    SELECT p.*, u.username, u.role, c.name as channel_name,
    (SELECT vote_type FROM votes WHERE user_id = ? AND post_id = p.id) as user_vote
    FROM posts p 
    JOIN users u ON p.user_id = u.id 
    JOIN subchannels c ON p.subchannel_id = c.id 
    WHERE p.id = ?
");
$stmt->execute([$current_logged_in_user, $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    die("Post not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <style>
        body { background: #121212; color: whitesmoke; font-family: "JetBrainsMono NF", monospace; margin: 0; padding-top: 60px; }
        .container { max-width: 800px; margin: 2rem auto; padding: 20px; background: #1a1a1a; border: 1px solid #333; }
        
        /* Post Card Layout with Voting */
        .post-container { display: flex; gap: 20px; align-items: flex-start; }
        .vote-section { display: flex; flex-direction: column; align-items: center; min-width: 30px; }
        .vote-btn { background: none; border: none; color: #666; cursor: pointer; font-size: 1.2rem; padding: 0; line-height: 1; }
        .vote-btn:hover { color: whitesmoke; }
        .vote-btn.upvote.active { color: #00ff66; }
        .vote-btn.downvote.active { color: #ff4d4d; }
        .vote-count { font-size: 1rem; font-weight: bold; color: #ccc; margin: 6px 0; }
        
        .post-main { flex: 1; }
        .meta { font-size: 0.85rem; color: #888; margin-bottom: 10px; }
        .channel-link { color: #00ffcc; text-decoration: none; }
        .post-title { font-size: 1.5rem; margin-top: 0; margin-bottom: 15px; color: whitesmoke; }
        .post-body { color: #ccc; line-height: 1.6; font-size: 0.95rem; white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="post-container" data-post-id="<?php echo $post['id']; ?>">
            <!-- Vote Column -->
            <div class="vote-section">
                <button class="vote-btn upvote <?php echo ($post['user_vote'] == 1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', 1)">▲</button>
                <span class="vote-count" id="vote-count-<?php echo $post['id']; ?>"><?php echo $post['votes_count']; ?></span>
                <button class="vote-btn downvote <?php echo ($post['user_vote'] == -1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', -1)">▼</button>
            </div>

            <!-- Main Post Content -->
            <div class="post-main">
                <div class="meta">
                    <a class="channel-link" href="/c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?>">c/<?php echo htmlspecialchars($post['channel_name'], ENT_QUOTES, 'UTF-8'); ?></a> 
                    • Posted by 
                    <a href="/u/<?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>" style="color: <?php echo getRoleColor($post['role']); ?>; text-decoration: none;">
                        <?php echo htmlspecialchars($post['username'], ENT_QUOTES, 'UTF-8'); ?>
                    </a> 
                    at <?php echo $post['created_at']; ?>
                </div>
                
                <h1 class="post-title"><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
                
                <div class="post-body">
                    <?php echo htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Asynchronous Voting Script -->
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
                
                let container = document.querySelector(`[data-post-id="${postId}"]`);
                let upBtn = container.querySelector('.upvote');
                let downBtn = container.querySelector('.downvote');
                
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