<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$post_id = $data['post_id'] ?? '';
$vote_type = intval($data['vote_type'] ?? 0); // 1 for upvote, -1 for downvote
$user_id = $_SESSION['user_id'];

if (empty($post_id) || !in_array($vote_type, [1, -1])) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$stmt = $db->prepare("SELECT user_id FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
}
$author_id = $post['user_id'];

$stmt = $db->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND post_id = ?");
$stmt->execute([$user_id, $post_id]);
$existing_vote = $stmt->fetch(PDO::FETCH_ASSOC);

$vote_difference = 0;
$karma_difference = 0;
$new_user_vote = 0;

if ($existing_vote) {
    $old_type = intval($existing_vote['vote_type']);
    if ($old_type === $vote_type) {
        $stmt = $db->prepare("DELETE FROM votes WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$user_id, $post_id]);
        $vote_difference = -$old_type;
        $karma_difference = -$old_type;
        $new_user_vote = 0;
    } else {
        $stmt = $db->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND post_id = ?");
        $stmt->execute([$vote_type, $user_id, $post_id]);
        $vote_difference = $vote_type - $old_type;
        $karma_difference = $vote_type - $old_type;
        $new_user_vote = $vote_type;
    }
} else {
    $stmt = $db->prepare("INSERT INTO votes (user_id, post_id, vote_type) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $post_id, $vote_type]);
    $vote_difference = $vote_type;
    $karma_difference = $vote_type;
    $new_user_vote = $vote_type;
}

$stmt = $db->prepare("UPDATE posts SET votes_count = votes_count + ? WHERE id = ?");
$stmt->execute([$vote_difference, $post_id]);

// 4. Update the post author's karma ONLY IF the voter is NOT the author
if ($karma_difference !== 0 && $user_id !== $author_id) {
    $stmt = $db->prepare("UPDATE users SET karma = karma + ? WHERE id = ?");
    $stmt->execute([$karma_difference, $author_id]);
}

$stmt = $db->prepare("SELECT votes_count FROM posts WHERE id = ?");
$stmt->execute([$post_id]);
$updated_post = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'new_votes_count' => intval($updated_post['votes_count']),
    'user_vote' => $new_user_vote
]);