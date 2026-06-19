<?php
session_start();
require_once 'confi.php';

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Preencha todos os campos.";
    } else {

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->rowCount() > 0) {
            $error = "Usuário ou e-mail já existe.";
        } else {

            $hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$username, $email, $hash])) {

                $userId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO user_status (user_id) VALUES (?)")
                    ->execute([$userId]);

                $success = "Conta criada com sucesso! Agora faça login.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registrar</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --bg-main: #182533;
    --bg-side: #17212b;
    --tg-blue: #5288c1;
    --tg-hover: #6499d3;
    --text-color: #f5f5f5;
    --text-muted: #7f91a4;
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

/* fundo estilo app */
.bg {
    position: absolute;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top, #22364d, var(--bg-main));
}

/* card */
.register-card {
    position: relative;
    width: 360px;
    background: var(--bg-side);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    animation: fadeIn 0.4s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
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

/* sucesso */
.success {
    background: rgba(0,255,120,0.1);
    color: #4caf50;
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

/* mobile modificado para estilo Telegram nativo */
@media(max-width: 480px){
    body {
        align-items: flex-start;
        background: var(--bg-side);
    }
    .bg {
        display: none;
    }
    .register-card {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        box-shadow: none;
        padding: 60px 25px 30px 25px;
        background: var(--bg-side);
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
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
}
</style>
</head>

<body>

<div class="bg"></div>

<div class="register-card">

    <div class="logo">
        <i class="fa-solid fa-comment-dots"></i>
    </div>

    <h2>Criar Conta</h2>

    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <i class="fa fa-user"></i>
            <input type="text" name="username" placeholder="Usuário" required>
        </div>

        <div class="input-group">
            <i class="fa fa-envelope"></i>
            <input type="email" name="email" placeholder="E-mail" required>
        </div>

        <div class="input-group">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" placeholder="Senha" required>
        </div>

        <button type="submit">Criar conta</button>
    </form>

    <a href="login.php">Já tenho conta</a>

</div>

</body>
</html>