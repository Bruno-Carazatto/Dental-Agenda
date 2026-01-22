<?php
// dental-agenda/app/auth/check.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Protege contra acesso sem login
if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Ajuda extra: impede cache do navegador em páginas privadas
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
