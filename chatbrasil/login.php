<?php
session_start();
require_once 'confi.php';

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// 1. VERIFICAÇÃO SE JÁ ESTÁ LOGADO (PELA SESSÃO OU COOKIE)
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
} elseif (isset($_COOKIE['remember_user_id']) && isset($_COOKIE['remember_username'])) {
    // Se a sessão expirou mas o cookie de "lembrar" existe, restaura a sessão
    $_SESSION['user_id'] = $_COOKIE['remember_user_id'];
    $_SESSION['username'] = $_COOKIE['remember_username'];
    
    header("Location: home.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']); // Verifica se marcou o "Lembrar de mim"

    if (empty($username) || empty($password)) {
        $error = "Preencha todos os campos.";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];

            // 2. SE MARCOU "LEMBRAR DE MIM", CRIA OS COOKIES (VÁLIDOS POR 30 DIAS)
            if ($remember) {
                setcookie('remember_user_id', $user['id'], time() + (86400 * 30), "/");
                setcookie('remember_username', $user['username'], time() + (86400 * 30), "/");
            }

            $pdo->prepare("
                INSERT INTO user_status (user_id, last_seen)
                VALUES (?, NOW())
                ON DUPLICATE KEY UPDATE last_seen = NOW()
            ")->execute([$user['id']]);

            header("Location: home.php");
            exit;

        } else {
            $error = "Usuário ou senha incorretos.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --bg-main: #182533;
    --bg-side: #17212b;
    --tg-blue: #5288c1;
    --tg-hover: #6499d3;
    --text-color: #f5f5f5;
    --text-muted: #7f91a4;
    --bg-cookie: #243447;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial;
}

body {
    background: var(--bg-main);
    color: var(--text-color);
    height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

/* TELA DE CARREGAMENTO ESTILO WHATSAPP */
.whatsapp-splash {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--bg-main);
    z-index: 99999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

.whatsapp-splash.fade-out {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

.splash-logo {
    font-size: 70px;
    color: var(--text-muted);
    opacity: 0.8;
    animation: pulseLogo 1.5s ease-in-out infinite alternate;
    margin-bottom: 40px;
}

@keyframes pulseLogo {
    from { transform: scale(0.95); opacity: 0.6; }
    to { transform: scale(1.05); opacity: 1; }
}

.splash-progress-container {
    position: absolute;
    bottom: 12%;
    width: 250px;
    height: 3px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    overflow: hidden;
}

.splash-progress-bar {
    width: 0%;
    height: 100%;
    background: var(--tg-blue);
    border-radius: 10px;
    animation: loadProgress 2.2s cubic-bezier(0.1, 0.5, 0.3, 1) forwards;
}

@keyframes loadProgress {
    0% { width: 0%; }
    20% { width: 25%; }
    50% { width: 65%; }
    85% { width: 90%; }
    100% { width: 100%; }
}

/* fundo estilo app */
.bg {
    position: absolute;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top, #22364d, var(--bg-main));
}

/* card */
.login-card {
    position: relative;
    width: 360px;
    background: var(--bg-side);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    /* Animação modificada para o padrão elástico do Telegram */
    animation: tgFadeIn 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
    z-index: 2;
}

/* Nova animação nativa estilo Telegram */
@keyframes tgFadeIn {
    from { 
        opacity: 0; 
        transform: scale(0.85) translateY(40px); 
    }
    to { 
        opacity: 1; 
        transform: scale(1) translateY(0); 
    }
}

/* logo */
.logo {
    text-align: center;
    font-size: 50px;
    color: var(--tg-blue);
    margin-bottom: 10px;
}

/* título */
h2 {
    text-align: center;
    margin-bottom: 20px;
}

/* input */
.input-group {
    position: relative;
    margin-bottom: 15px;
}

.input-group i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-muted);
}

.input-group input {
    width: 100%;
    padding: 12px 12px 12px 38px;
    border-radius: 10px;
    border: none;
    background: var(--bg-main);
    color: white;
    outline: none;
    transition: 0.2s;
}

.input-group input:focus {
    border: 1px solid var(--tg-blue);
}

/* Estilo do Lembrar de mim */
.remember-group {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    font-size: 14px;
    color: var(--text-muted);
    cursor: pointer;
}

.remember-group input {
    margin-right: 8px;
    accent-color: var(--tg-blue);
    cursor: pointer;
}

/* botão */
button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: var(--tg-blue);
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: 0.2s;
}

button:hover {
    background: var(--tg-hover);
}

/* erro */
.error {
    background: rgba(255,0,0,0.1);
    color: #ff6b6b;
    padding: 10px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 10px;
    text-align: center;
}

/* link */
a {
    display: block;
    text-align: center;
    margin-top: 15px;
    color: var(--tg-blue);
    text-decoration: none;
    font-size: 13px;
}

a:hover {
    text-decoration: underline;
}

/* Rodapé de Links Legais */
.footer-links {
    margin-top: 25px;
    padding-top: 15px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.footer-links a {
    margin-top: 0;
    color: var(--text-muted);
    font-size: 12px;
    transition: color 0.2s;
}

.footer-links a:hover {
    color: var(--tg-blue);
}

/* Banner de Cookies Estilo Telegram */
.cookie-banner {
    position: fixed;
    bottom: -100px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bg-cookie);
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    display: none; /* Começa oculto e o JS altera se necessário */
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 90%;
    max-width: 600px;
    z-index: 9999;
    transition: bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.05);
}

.cookie-banner.show {
    bottom: 20px;
}

.cookie-text {
    font-size: 13px;
    color: var(--text-color);
    line-height: 1.4;
}

.cookie-text a {
    display: inline;
    color: var(--tg-blue);
    margin: 0 3px;
}

.cookie-btn {
    background: var(--tg-blue);
    color: #fff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    width: auto;
}

.cookie-btn:hover {
    background: var(--tg-hover);
}

/* mobile modificado para estilo Telegram nativo */
@media(max-width: 480px){
    body {
        align-items: flex-start;
        background: var(--bg-side);
    }
    .bg {
        display: none;
    }
    .login-card {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        box-shadow: none;
        padding: 60px 25px 30px 25px;
        background: var(--bg-side);
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        /* Mantém a animação suave também no mobile */
        animation: tgFadeInMobile 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
    }
    @keyframes tgFadeInMobile {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .logo {
        font-size: 65px;
        margin-bottom: 15px;
    }
    h2 {
        font-size: 24px;
        margin-bottom: 30px;
    }
    .input-group input {
        background: var(--bg-main);
        font-size: 16px;
        padding: 14px 14px 14px 42px;
    }
    button {
        padding: 14px;
        font-size: 16px;
        margin-top: 10px;
    }
    .cookie-banner {
        width: calc(100% - 30px);
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        padding: 15px;
    }
    .cookie-banner.show {
        bottom: 15px;
    }
    .cookie-btn {
        width: 100%;
        text-align: center;
    }
}
</style>
</head>

<body>

<div class="whatsapp-splash" id="whatsappSplash">
    <div class="splash-logo">
        <i class="fa-solid fa-comment-dots"></i>
    </div>
    <div class="splash-progress-container">
        <div class="splash-progress-bar"></div>
    </div>
</div>

<div class="bg"></div>

<div class="login-card">

    <div class="logo">
        <i class="fa-solid fa-comment-dots"></i>
    </div>

    <h2>Entrar</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <i class="fa fa-user"></i>
            <input type="text" name="username" placeholder="Usuário" required>
        </div>

        <div class="input-group">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" placeholder="Senha" required>
        </div>

        <label class="remember-group">
            <input type="checkbox" name="remember" checked>
            Mantenha-me conectado
        </label>

        <button type="submit">Entrar</button>
    </form>

    <a href="register.php">Criar conta</a>

    <div class="footer-links">
        <a href="politicas_de_privacidade.php">Privacidade</a>
        <a href="termos.php">Termos</a>
        <a href="diretriz.php">Diretrizes</a>
        <a href="regras.php">Regras</a>
    </div>

</div>

<div class="cookie-banner" id="cookieBanner">
    <div class="cookie-text">
        Nós usamos cookies para melhorar sua experiência. Ao continuar, você concorda com nossas 
        <a href="politicas_de_privacidade.php">Políticas de Privacidade</a> e <a href="termos.php">Termos de Uso</a>.
    </div>
    <button class="cookie-btn" onclick="acceptCookies()">Aceitar</button>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookieBanner');
    const splash = document.getElementById('whatsappSplash');

    // CONEXÃO COM O CONTADOR AO VIVO (Registra o hit de acesso legítimo)
    fetch('api_metrics.php?track=1').catch(err => console.log('Erro de tracking:', err));

    // Gatilho para remover o Splash Screen após a animação de carregamento terminar (2.2 segundos)
    setTimeout(() => {
        splash.classList.add('fade-out');
    }, 2200);

    // Se o usuário ainda NÃO aceitou, preparamos o banner e exibimos
    if (!localStorage.getItem('cookiesAceitos')) {
        banner.style.display = 'flex'; // Torna visível estruturalmente
        setTimeout(() => {
            banner.classList.add('show'); // Sobe o banner suavemente na tela
        }, 1000);
    }
});

function acceptCookies() {
    // Salva no navegador para não pedir novamente nas próximas visitas
    localStorage.setItem('cookiesAceitos', 'true');
    
    const banner = document.getElementById('cookieBanner');
    
    // SUMIR IMEDIATAMENTE (Garante que ele saia totalmente da tela sem empurrar nada para baixo)
    banner.style.display = 'none';
}
</script>

</body>
</html>