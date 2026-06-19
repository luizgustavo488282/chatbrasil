<?php
// 1. Inicia a sessão para ter acesso aos dados guardados
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Limpa todas as variáveis de sessão existentes
$_SESSION = array();

// 3. Destrói o cookie de sessão padrão do PHP no navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// =========================================================================
// CORREÇÃO: Limpa os cookies de "Lembrar de mim" que o login.php criou.
// Sem isso, o login.php te loga de volta automaticamente para a home.php
// =========================================================================
if (isset($_COOKIE['remember_user_id'])) {
    setcookie('remember_user_id', '', time() - 3600, "/");
}
if (isset($_COOKIE['remember_username'])) {
    setcookie('remember_username', '', time() - 3600, "/");
}

// 4. Destrói a sessão no servidor
session_destroy();

// 5. Redireciona o usuário imediatamente para a página de login
header("Location: login.php");
exit;