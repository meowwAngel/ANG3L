<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php'); // Or wherever your login page is
    exit;
}

$post_id = $_POST['post_id'] ?? '';
$vote_type = intval($_POST['vote_type'] ?? 0);
$user_id = $_SESSION['user_id'];

if (empty($post_id) || !in_array($vote_type, [1, -1])) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
    exit;
}

$stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
    exit;
}
$author_id = $post['user_id'];

$stmt = $db->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user_id, $post_id]);
$existing_vote = $stmt->fetch(PDO::FETCH_ASSOC);

$vote_difference = 0;
$karma_difference = 0;

if ($existing_vote) {
    $old_type = intval($existing_vote['vote_type']);
    if ($old_type === $vote_type) {
        $stmt = $db->prepare("DELETE FROM votes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
        $vote_difference = -$old_type;
        $karma_difference = -$old_type;
    } else {
        $stmt = $db->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$vote_type, $user_id, $post_id]);
        $vote_difference = $vote_type - $old_type;
        $karma_difference = $vote_type - $old_type;
    }
} else {
    $stmt = $db->prepare("INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $post_id, $vote_type]);
    $vote_difference = $vote_type;
    $karma_difference = $vote_type;
}

$stmt = $db->prepare("UPDATE posts SET votes_count = votes_count + ? WHERE id = ?");
$stmt->execute([$vote_difference, $post_id]);

if ($karma_difference !== 0 && $user_id !== $author_id) {
    $stmt = $db->prepare("UPDATE users SET karma = karma + ? WHERE id = ?");
    $stmt->execute([$karma_difference, $author_id]);
}

// Redirect the user back to the page they voted from
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/index.php'));
exit;