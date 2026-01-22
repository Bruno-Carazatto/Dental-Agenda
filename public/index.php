<?php
// dental-agenda/public/index.php
declare(strict_types=1);

session_start();

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;
