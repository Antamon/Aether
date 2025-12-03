<?php
session_start();

// Controleer of ingelogd
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$userinfo = $_SESSION['user'];

var_dump($userinfo);
?>

<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <title>Welkom <?php echo htmlspecialchars($userinfo['displayName'] ?? 'Gebruiker'); ?></title>
</head>

<body>

    <h1>Welkom, <?php echo htmlspecialchars($userinfo['display_name'] ?? 'Gebruiker'); ?>!</h1>
    <p>Email: <?php echo htmlspecialchars($userinfo['email'] ?? ''); ?></p>
    <p>Gebruikers-ID: <?php echo htmlspecialchars($userinfo['id'] ?? ''); ?></p>

    <p><a href="logout.php">Uitloggen</a></p>
    <!-- <script src="js/mainFunctions.js"></script> -->
</body>

</html>