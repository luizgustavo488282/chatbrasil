<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// 🔥 ALTERADO PARA USAR O SEU ARQUIVO PADRÃO DE CONEXÃO
require_once 'confi.php'; 

$error = "";
$success = "";

if (!isset($_SESSION['2fa_code'])) {
    header("Location: register.php");
    exit;
}

// Garante que a sessão de expiração exista no primeiro clique/carregamento vindo do register.php
if (!isset($_SESSION['2fa_expire'])) {
    $_SESSION['2fa_expire'] = time() + 300;
}

function reenviarCodigo() {
    $mail = new PHPMailer(true);

    try {
        // Novo código
        $codigo = rand(100000, 999999);
        $_SESSION['2fa_code'] = $codigo;
        $_SESSION['2fa_expire'] = time() + 300;

        // SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'contato@chatbrasil.qzz.io';
        $mail->Password = 'nurr ubkr dcbs woui'; // Certifique-se de que esta é a Senha de App de 16 dígitos ativa no Google
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Configurações extras para evitar bloqueios de servidores locais (XAMPP/Windows)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom(
            'ls779863322@gmail.com',
            'ChatBrasil'
        );

        $mail->addAddress(
            $_SESSION['temp_email']
        );

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Novo Código';

        $mail->Body = "
        <div style='
            font-family: Arial, sans-serif;
            color: #333333;
            line-height: 1.6;
            padding: 20px;
            background: #f5f6f7;
        '>
            <div style='
                max-width: 500px;
                margin: auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            '>
                <h2 style='
                    color:#5288c1;
                    margin-top:0;
                '>
                    🔄 Novo Código
                </h2>
                <p>
                    Recebemos uma solicitação para autenticação da sua conta.
                    Utilize o código abaixo para continuar:
                </p>
                <div style='
                    margin: 25px 0;
                    padding: 20px;
                    text-align: center;
                    background-color: #f4fdf8;
                    border: 2px solid #5288c1;
                    border-radius: 10px;
                '>
                    <p style='
                        margin: 0 0 10px 0;
                        font-size: 14px;
                        color: #666666;
                    '>
                        Seu código de verificação
                    </p>
                    <div style='
                        font-size: 42px;
                        font-weight: bold;
                        letter-spacing: 6px;
                        color: #5288c1;
                    '>
                        $codigo
                    </div>
                </div>
                <p>
                    Este código expira em <strong>5 minutos</strong>.
                </p>
                <p style='
                    color:#d93025;
                    font-weight:bold;
                '>
                    ⚠️ Não compartilhe este código com ninguém.
                    Nossa equipe nunca solicitará esse código por e-mail,
                    telefone ou mensagem.
                </p>
                <p>
                    Caso você não tenha solicitado este acesso,
                    ignore esta mensagem.
                </p>
                <hr style='
                    border:none;
                    border-top:1px solid #e0e0e0;
                    margin:25px 0;
                '>
                <p style='
                    font-size:12px;
                    color:#888888;
                    text-align:center;
                '>
                    Esta é uma mensagem automática. Não responda este e-mail.
                </p>
            </div>
        </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        // Se houver um erro crítico no SMTP, ele imprimirá na tela para você saber exatamente o porquê falhou
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}

if (isset($_POST['resend'])) {
    if (reenviarCodigo()) {
        $success = "Novo código enviado";
    } else {
        $error = "Erro ao reenviar: " . ($_SESSION['mail_error'] ?? '');
    }
}

if (isset($_POST['verify'])) {
    $codigo = trim($_POST['code']);

    // Expirado
    if (time() > $_SESSION['2fa_expire']) {
        $error = "Código expirado";
    } elseif ($codigo == $_SESSION['2fa_code']) {

        // 🔥 MODIFICADO PARA USAR A SUA VARIÁVEL $pdo DO arquivo confi.php
        $stmt = $pdo->prepare("
            INSERT INTO users
            (username, email, password)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['temp_user'],
            $_SESSION['temp_email'],
            $_SESSION['temp_pass']
        ]);

        // 🔥 INSRE NA TABELA user_status IGUAL AO SEU ORIGINAL
        $userId = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO user_status (user_id) VALUES (?)")
            ->execute([$userId]);

        // Limpar session
        session_unset();
        $success = "Conta criada com sucesso";

        // 🔥 ALTERADO AQUI (login.php -> index.php)
        header("refresh:2;url=index.php");
    } else {
        $error = "Código inválido";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verificação</title>

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
    z-index: 1;
}

/* card */
.container {
    position: relative;
    width: 360px;
    background: var(--bg-side);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.4);
    animation: tgFadeIn 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
    z-index: 2;
    text-align: center;
}

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

.logo {
    text-align: center;
    font-size: 50px;
    color: var(--tg-blue);
    margin-bottom: 10px;
}

h2 {
    text-align: center;
    margin-bottom: 20px;
    font-weight: normal;
}

input {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 10px;
    border: none;
    background: var(--bg-main);
    color: white;
    outline: none;
    font-size: 15px;
    text-align: center;
    transition: 0.2s;
}

input:focus {
    border: 1px solid var(--tg-blue);
}

button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: var(--tg-blue);
    color: white;
    font-weight: bold;
    cursor: pointer;
    margin-top: 10px;
    transition: 0.2s;
}

button:hover {
    background: var(--tg-hover);
}

button[name="resend"] {
    background: transparent;
    color: var(--tg-blue);
    margin-top: 5px;
}

button[name="resend"]:hover {
    background: rgba(82, 136, 193, 0.1);
    text-decoration: underline;
}

.error {
    background: rgba(255,0,0,0.1);
    color: #ff6b6b;
    padding: 10px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 15px;
    text-align: center;
}

.success {
    background: rgba(0, 168, 132, 0.1);
    color: #4cd9a8;
    padding: 10px;
    border-radius: 8px;
    font-size: 13px;
    margin-bottom: 15px;
    text-align: center;
}

/* mobile */
@media(max-width: 480px){
    body {
        align-items: flex-start;
        background: var(--bg-side);
    }
    .bg {
        display: none;
    }
    .container {
        width: 100%;
        height: 100vh;
        border-radius: 0;
        box-shadow: none;
        padding: 60px 25px 30px 25px;
        background: var(--bg-side);
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
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
    input {
        font-size: 16px;
        padding: 14px;
    }
    button {
        padding: 14px;
        font-size: 16px;
    }
}
</style>
</head>
<body>

<div class="bg"></div>

<div class="container">

    <div class="logo">
        <i class="fa-solid fa-comment-dots"></i>
    </div>

    <h2>Verificação 2FA</h2>

    <?php if($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="code" placeholder="Digite o código" required>
        <button type="submit" name="verify">Verificar</button>
        <button type="submit" name="resend">Reenviar código</button>
    </form>

</div>
</body>
</html>