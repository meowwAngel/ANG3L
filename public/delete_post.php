<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($post_id) {
        $stmt = $db->prepare("DELETE FROM posts WHERE id = :post_id AND user_id = :user_id");
        $stmt->execute([':post_id' => $post_id, ':user_id' => $user_id]);
    }
}

header('Location: index.php');
exit;