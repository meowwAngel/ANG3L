<?php

require_once '../db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Please fill in all fields.";
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        $message = "Username must be 3-20 characters and contain only letters, numbers, underscores, and dashes.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {

            $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $password_hash]);

            $message = "Registration successful! You can now log in.";
        } catch (PDOException $e) {

            $message = "Error: That username is already taken.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Register | ANG3L</title>
    <link rel="stylesheet" type="text/css" href="/css/login.css">
    <link rel="stylesheet" type="text/css" href="/css/header.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>
        <h2>Register an Account</h2>

        <?php if (!empty($message)): ?>
            <p style="color: yellow;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div>
                <label>Username:</label><br>
                <input type="text" name="username" required>
            </div>
            <div>
                <label>Password:</label><br>
                <input type="password" name="password" required>
            </div>
            <br>
            <button type="submit">Sign Up</button>
        </form>

        <p><a href="/login.php">Log in</a></p>
    </main>
</body>
</html>