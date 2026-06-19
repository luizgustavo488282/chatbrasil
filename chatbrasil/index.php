<?php
require_once 'config.php';

// Se o usuário já estiver logado, vai para a home.php
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
} else {
    // Se NÃO estiver logado, vai para a tela de login externa
    header('Location: login.php');
    exit;
}
?>