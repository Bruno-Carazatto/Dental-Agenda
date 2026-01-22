<?php
// dental-agenda/public/install.php
declare(strict_types=1);

require_once __DIR__ . '/../app/config/db.php';

$pdo = db();

$schemaPath = __DIR__ . '/../sql/schema.sql';
$schema = file_get_contents($schemaPath);

if ($schema === false) {
    http_response_code(500);
    echo "Erro: não consegui ler o arquivo schema.sql.";
    exit;
}

// Executa o schema (pode levar alguns segundos)
$pdo->exec($schema);

// Cria o Super Admin inicial (apenas se ainda não existir)
$usuarioAdmin = 'Admin';
$nomeAdmin = 'Admin';
$senhaAdmin = 'Admin@123';
$roleAdmin = 'admin';

$stmt = $pdo->prepare("SELECT id FROM users WHERE usuario = :u LIMIT 1");
$stmt->execute([':u' => $usuarioAdmin]);
$exists = $stmt->fetch();

if (!$exists) {
    $hash = password_hash($senhaAdmin, PASSWORD_DEFAULT);

    $ins = $pdo->prepare("
        INSERT INTO users (nome, usuario, senha_hash, role, ativo)
        VALUES (:nome, :usuario, :senha_hash, :role, 1)
    ");
    $ins->execute([
        ':nome' => $nomeAdmin,
        ':usuario' => $usuarioAdmin,
        ':senha_hash' => $hash,
        ':role' => $roleAdmin,
    ]);

    echo "✅ Instalado! Admin criado: {$usuarioAdmin} (role: admin).<br>";
} else {
    echo "ℹ️ Banco já estava instalado e o admin já existe.<br>";
}

echo "<br><a href='login.php'>Ir para Login</a>";
