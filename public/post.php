<?php
require_once __DIR__ . '/../db.php';

$post_id = $_GET['id'] ?? '';

if (empty($post_id)) {
    http_response_code(404);
    die("Post not found.");
}

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

$stmt_comments = $db->prepare("
    SELECT c.*, u.username, u.role 
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");

$stmt_comments->execute([$post_id]);
$comments = $stmt_comments->fetchAll(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    die("Post not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_body'])) {
    if (!isset($_SESSION['user_id'])) {
        die("You must be logged in to comment.");
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die("CSRF token validation failed.");
    }

    $body = trim($_POST['comment_body']);

    if (!empty($body)) {
        $stmt = $db->prepare("INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $_SESSION['user_id'], $body]);

        header("Location: post.php?id=" . urlencode($post_id));
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?> - ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/header.css">
    <link rel="stylesheet" type="text/css" href="/css/post.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <div class="post-container" data-post-id="<?php echo $post['id']; ?>">
            <div class="vote-section">
                <button class="vote-btn upvote <?php echo ($post['user_vote'] == 1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', 1)">▲</button>
                <span class="vote-count" id="vote-count-<?php echo $post['id']; ?>"><?php echo $post['votes_count']; ?></span>
                <button class="vote-btn downvote <?php echo ($post['user_vote'] == -1) ? 'active' : ''; ?>" onclick="castVote('<?php echo $post['id']; ?>', -1)">▼</button>
            </div>

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

    <?php if (isset($_SESSION['user_id']) && ($_SESSION['user_id'] === $post['user_id'] || $current_role === 'admin')): ?>
        <div style="margin-top: 0.5rem;">
            <form action="/delete_post.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <button type="submit" style="background: none; border: none; color: #ff5555; cursor: pointer; font-size: 0.8rem; padding: 0;">Delete</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="comments-section" style="margin-top: 2rem;">
        <?php if (isset($_SESSION['user_id'])): ?>
        <form action="post.php?id=<?php echo urlencode($post_id); ?>" method="POST" style="margin-top: 1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div style="margin-bottom: 1rem;">
                <label for="comment_body">Leave a comment:</label><br>
                <textarea id="comment_body" name="comment_body" required style="width: 90%; height: 80px; background: #000; color: #fff; border: 1px solid #444; padding: 8px;"></textarea>
            </div>
            <button type="submit" style="background: #333; color: #fff; border: 1px solid #555; padding: 8px 16px; cursor: pointer;">Post Comment</button>
        </form>
    <?php else: ?>
        <p style="color: gray; margin-top: 1.5rem;"><a href="/login.php" style="color: #00ffcc;">Log in</a> to leave a comment.</p>
    <?php endif; ?>
        <?php if (empty($comments)): ?>
            <p style="color: gray;">No comments yet.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <div class="comment-card" style="border: 1px solid #333; padding: 10px; margin-bottom: 10px; background: #111;">
                    <div style="font-size: 0.85rem; color: #888; margin-bottom: 5px;">
                        <strong style="color: <?php echo getRoleColor($comment['role']); ?>;">
                            <?php echo htmlspecialchars($comment['username'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                        • <?php echo $comment['created_at']; ?>

                        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                            <form action="delete_comment.php" method="POST" style="display: inline; margin-left: 10px;" onsubmit="return confirm('Delete this comment?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                                <button type="submit" style="background: none; border: none; color: #ff5555; cursor: pointer; font-size: 0.8rem; padding: 0;">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div style="color: #ccc;"><?php echo nl2br(htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8')); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>