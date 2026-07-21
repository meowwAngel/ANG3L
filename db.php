<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['expire_time']) && time() > $_SESSION['expire_time']) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), 
            '', 
            time() - 42000,
            $params["path"], 
            $params["domain"],
            $params["secure"], 
            $params["httponly"]
        );
    }
    session_destroy();
}

try {
    // __DIR__ is the exact folder where db.php lives (your project root)
    $dbPath = __DIR__ . '/ang3l.db';
    
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    // Temporarily change this to print the real error while debugging:
    die("Database error: " . $e->getMessage());
}
?>

<?php
if (!function_exists('getRoleColor')) {
    function getRoleColor($role) {
        switch ($role) {
            case 'member': return '#00FF66';
            case 'beta_tester': return '#89CFF0';
            case 'mods': return '#FF9900';
            case 'legends': return '#00FFCC';
            case 'admin': return '#FF4D4D';
            default: return 'whitesmoke';
        }
    }
}