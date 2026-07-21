<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<header>
    <span class="TITLE"><a href="/index.php">ANG3L</a></span>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <p><a href="/logout.php">&nbsp;/ Log Out</a></p>
        <p><a href="/settings.php">&nbsp;/ Settings</a></p>
        
        <?php 
        $role = $_SESSION['role'] ?? 'member';
        $role_color = getRoleColor($role);
        $role_suffix = ' [' . $role . ']';
        $profile_url = '/u/' . urlencode($_SESSION['username']);
        ?>

        <p><a class='usrname' style="color: <?php echo $role_color; ?>;" href="<?php echo $profile_url; ?>"><?php echo htmlspecialchars($_SESSION['username'] . $role_suffix, ENT_QUOTES, 'UTF-8'); ?></a></p>

        <?php if ($role === 'admin'): ?>
            <p><a style="color: blue" href="/admin.php">&nbsp;/ Admin</a></p>
        <?php endif; ?>

    <?php else: ?>
        <p><a href="/register.php">Register /</a></p>
        <p><a href="/login.php">&nbsp;Login</a></p>
    <?php endif; ?>
</header>