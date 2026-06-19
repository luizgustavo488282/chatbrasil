<?php 
session_start();

// IMPORTAR O VENDOR DO PHPMAILER
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

try {
    $conn = new PDO("mysql:host=localhost;dbname=telegram_clone;charset=utf8", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão");
}

$error = "";
$success = "";

if (isset($_POST['register'])) {

    $user = trim($_POST['username']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);

    // Validações
    if (empty($user) || empty($email) || empty($pass)) {
        $error = "Preencha todos os campos";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido";
    } elseif (strlen($user) < 3) {
        $error = "Usuário deve ter no mínimo 3 caracteres";
    } elseif (strlen($pass) < 5) {
        $error = "Senha deve ter no mínimo 5 caracteres";
    } else {

        // Verificar se usuário já existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$user, $email]);

        if ($stmt->rowCount() > 0) {
            $error = "Usuário ou email já existe";
        } else {

            // Criar senha segura
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // Gerar código 2FA
            $codigo = rand(100000, 999999);

            // Salvar temporariamente na sessão
            $_SESSION['temp_user'] = $user;
            $_SESSION['temp_email'] = $email;
            $_SESSION['temp_pass'] = $hash;
            $_SESSION['2fa_code'] = $codigo;
            $_SESSION['2fa_expire'] = time() + 300; // Define a expiração padrão de 5 minutos

            // ENVIAR EMAIL COM PHPMAILER (VENDOR)
            $assunto = "🔐 Seu código de verificação";

            $mensagem = "
            <!DOCTYPE html>
            <html lang='pt-br'>
            <head>
                <meta charset='UTF-8'>
            </head>
            <body style='margin:0;padding:0;background:#17212b;font-family:Segoe UI,Arial,sans-serif;'>
                <table width='100%' cellpadding='0' cellspacing='0' style='padding:40px 0;background:#17212b;'>
                    <tr>
                        <td align='center'>
                            <table width='600' cellpadding='0' cellspacing='0' style='background:#242f3d; border-radius:22px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,0.3);'>
                                <tr>
                                    <td style='background:linear-gradient(135deg,#5288c1,#3d6fa4); padding:45px; text-align:center; color:white;'>
                                        <h1 style='margin:0; font-size:34px; font-weight:700; letter-spacing:1px;'>🇧🇷 ChatBrasil</h1>
                                        <p style='margin-top:14px; font-size:16px; opacity:0.95;'>Segurança e proteção da sua conta</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding:50px 40px;color:#f5f5f5;'>
                                        <p style='font-size:20px; margin:0 0 20px; font-weight:600;'>Olá, <span style='color:#5288c1;'>$user</span> 👋</p>
                                        <p style='font-size:16px; line-height:1.9; color:#b1c4d6; margin-bottom:35px;'>Recebemos uma solicitação de verificação para acessar sua conta. Utilize o código abaixo para continuar:</p>
                                        <div style='text-align:center;margin:45px 0;'>
                                            <div style='display:inline-block; padding:28px 50px; border-radius:20px; background:linear-gradient(135deg,#1f2936,#1a2430); border:3px dashed #5288c1; box-shadow:0 8px 20px rgba(82,136,193,0.2);'>
                                                <span style='font-size:48px; letter-spacing:12px; font-weight:800; color:#5288c1;'>$codigo</span>
                                            </div>
                                        </div>
                                        <p style='font-size:15px; color:#7f91a4; line-height:1.8;'>⏳ Este código expira em <b>5 minutos</b>.</p>
                                        <p style='font-size:15px; color:#7f91a4; line-height:1.8;'>Caso você não tenha solicitado este acesso, ignore este email com segurança.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='background:#17212b; border-top:1px solid #1f2936; padding:28px; text-align:center;'>
                                        <p style='margin:0; font-size:14px; color:#7f91a4;'>© ".date('Y')." 🇧🇷 ChatBrasil • Todos os direitos reservados</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $mail = new PHPMailer(true);

            try {
                // Configurações do Servidor SMTP do Gmail
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ls779863322@gmail.com';
                $mail->Password   = 'ehkqnyzuwwfsvnlr';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                // Evita rejeições de SSL comuns no ambiente XAMPP do Windows
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // Remetente e Destinatário
                $mail->setFrom('ls779863322@gmail.com', 'ChatBrasil');
                $mail->addAddress($email);

                // Conteúdo do Email
                $mail->isHTML(true);
                $mail->Subject = $assunto;
                $mail->Body    = $mensagem;

                $mail->send();

                header("Location: verificar_2fa.php");
                exit;

            } catch (Exception $e) {
                $error = "Erro ao enviar e-mail: " . $mail->ErrorInfo;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Criar Conta - ChatBrasil</title>

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
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

/* fundo estilo app */
.bg {
    position: absolute;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top, #22364d, var(--bg-main));
    z-index: -1;
}

/* card */
.register-card {
    position: relative;
    width: 100%;
    min-height: 100vh;
    background: var(--bg-main);
    padding: 60px 25px 30px 25px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    animation: tgFadeInMobile 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
    z-index: 2;
}

@keyframes tgFadeInMobile {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* logo */
.logo {
    text-align: center;
    font-size: 65px;
    color: var(--tg-blue);
    margin-bottom: 15px;
}

/* título */
h2 {
    text-align: center;
    margin-bottom: 30px;
    font-size: 24px;
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
    padding: 14px 14px 14px 42px;
    border-radius: 10px;
    border: none;
    background: var(--bg-side);
    color: white;
    outline: none;
    font-size: 16px;
    transition: 0.2s;
}

.input-group input:focus {
    border: 1px solid var(--tg-blue);
}

/* botão */
button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: var(--tg-blue);
    color: white;
    font-weight: bold;
    font-size: 16px;
    cursor: pointer;
    margin-top: 10px;
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

.footer-links {
    margin-top: auto;
    padding-top: 40px;
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
    padding: 15px;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    display: none;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
    width: calc(100% - 30px);
    z-index: 9999;
    transition: bottom 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid rgba(255,255,255,0.05);
}

.cookie-banner.show {
    bottom: 15px;
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
    width: 100%;
    text-align: center;
}

.cookie-btn:hover {
    background: var(--tg-hover);
}

/* Suporte responsivo mantendo comportamento idêntico ao login desktop */
@media(min-width: 481px){
    body {
        align-items: center;
        justify-content: center;
    }
    .bg {
        display: block;
    }
    .register-card {
        width: 360px;
        min-height: auto;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        padding: 30px;
        background: var(--bg-side);
        animation: tgFadeIn 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
    }
    @keyframes tgFadeIn {
        from { opacity: 0; transform: scale(0.85) translateY(40px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .logo {
        font-size: 50px;
        margin-bottom: 10px;
    }
    h2 {
        margin-bottom: 20px;
    }
    .input-group input {
        background: var(--bg-main);
        padding: 12px 12px 12px 38px;
    }
    button {
        padding: 12px;
        margin-top: 10px;
    }
    .footer-links {
        margin-top: 25px;
        padding-top: 15px;
    }
    .cookie-banner {
        width: 90%;
        max-width: 600px;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 15px 25px;
    }
    .cookie-banner.show {
        bottom: 20px;
    }
    .cookie-btn {
        width: auto;
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
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="input-group">
            <i class="fa fa-user"></i>
            <input type="text" name="username" placeholder="Usuário" required>
        </div>

        <div class="input-group">
            <i class="fa fa-envelope"></i>
            <input type="email" name="email" placeholder="Gmail" required>
        </div>

        <div class="input-group">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" placeholder="Senha" required>
        </div>

        <button type="submit" name="register">Cadastrar</button>
    </form>

    <a href="login.php">Já tem conta? Entrar</a>

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
        <a href="politicas_de_privacidade.php">Políticas de Privacidade</a> e <a href="termos.php">Termos de Uso</a> e <a href="diretriz.php">Diretriz de Uso</a>.
    </div>
    <button class="cookie-btn" onclick="acceptCookies()">Aceitar</button>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const banner = document.getElementById('cookieBanner');

    if (!localStorage.getItem('cookiesAceitos')) {
        banner.style.display = 'flex';
        setTimeout(() => {
            banner.classList.add('show');
        }, 1000);
    }
});

function acceptCookies() {
    localStorage.setItem('cookiesAceitos', 'true');
    const banner = document.getElementById('cookieBanner');
    banner.style.display = 'none';
}
</script>

</body>
</html>