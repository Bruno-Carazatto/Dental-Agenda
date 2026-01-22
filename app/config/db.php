<?php
// dental-agenda/app/config/db.php
// Conexão PDO segura com MySQL (phpMyAdmin / LAMP)

declare(strict_types=1);

function db(): PDO
{
    // ✅ Ajuste conforme seu ambiente:
    $dbHost = 'localhost';
    $dbName = 'dental_agenda';
    $dbUser = 'root';
    $dbPass = 'Lohbru@21'; // coloque sua senha do MySQL aqui se tiver

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erros como exceções
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false, // prepara de verdade (mais seguro)
    ];

    return new PDO($dsn, $dbUser, $dbPass, $options);
}
