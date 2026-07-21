<?php

session_start();

require_once __DIR__ . '/../db.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Please fill in all fields.";
    } else {

        $stmt = $db->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'member'; // Fallback to 'member' if empty

            $duration = filter_input(INPUT_POST, 'remember_duration', FILTER_VALIDATE_INT);
            $max_duration = 604800;
            
            if ($duration && $duration > 0 && $duration <= $max_duration) {
                $_SESSION['expire_time'] = time() + $duration;
            } else {
                $_SESSION['expire_time'] = time() + 1800;
            }

            header("Location: index.php");
            exit;
        } else {
            $message = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login - ANG3L Forum</title>
    <link rel="stylesheet" type="text/css" href="/css/login.css">
    <link rel="stylesheet" type="text/css" href="/css/header.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <main>

        <h2>Log In</h2>

        <?php if (!empty($message)): ?>
            <p style="color: yellow;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div>
                <label>Username:</label><br>
                <input type="text" name="username" required>
            </div>
            <div>
                <label>Password:</label><br>
                <input type="password" name="password" required>
            </div>
            <div>
                <label>Keep me logged in for:</label><br>
                <select name="remember_duration">
                    <option value="3600">1h</option>
                    <option value="21600">6h</option>
                    <option value="43200">12h</option>
                    <option value="86400">24h</option>
                </select>
            </div>
            <br>
            <button type="submit">Log In</button>
        </form>

        <p><a href="register.php">Register</a></p>
    </main>
</body>
</html>