<?php
// dental-agenda/public/logout.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';

session_start();

try {
    $pdo = db();
    $userId = $_SESSION['user']['id'] ?? null;
    $usuario = $_SESSION['user']['usuario'] ?? null;

    $log = $pdo->prepare("
        INSERT INTO auth_logs (user_id, usuario_digitado, evento, ip, user_agent)
        VALUES (:user_id, :usuario, 'logout', :ip, :ua)
    ");
    $log->execute([
        ':user_id' => $userId,
        ':usuario' => $usuario,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
} catch (Throwable $e) {
    // Se der erro no log, só faz logout mesmo (MVP)
}

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
