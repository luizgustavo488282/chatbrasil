<?php
// ==========================
// CONFIGURAÇÃO DO BANCO LOCAL
// ==========================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // senha do MySQL local
define('DB_NAME', 'telegram_clone');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Opcional: mensagem de sucesso
    // echo "Conexão realizada com sucesso!";

} catch (PDOException $e) {
    die("❌ Erro na conexão com o banco de dados: " . $e->getMessage());
}

// ==========================
// SESSÃO SEGURA
// ==========================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>