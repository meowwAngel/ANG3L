<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel_id = $_POST['channel_id'] ?? null;

    if ($channel_id) {
        // Optional: Add an admin check here if only admins can delete channels
        $stmt = $db->prepare("DELETE FROM channels WHERE id = :channel_id");
        $stmt->execute([':channel_id' => $channel_id]);
    }
}

header('Location: index.php');
exit;