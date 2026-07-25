<?php
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die("Get a job");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("CSRF validation failed. Action blocked.");
    }

    $channel_id = intval($_POST['channel_id'] ?? 0);

    if ($channel_id > 0) {
        $stmt = $db->prepare("DELETE FROM subchannels WHERE id = ?");
        $stmt->execute([$channel_id]);
    }
}

header('Location: /');
exit;
?>