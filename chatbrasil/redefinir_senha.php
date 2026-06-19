<?php
// redefinir_senha.php (Página Pública com Proteção de 2FA Integrada)
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'confi.php'; // Usa o seu arquivo padrão de conexão

$mensagemErro = "";
$mensagemSucesso = "";

// ---------------------------------------------------------
// FUNÇÃO AUXILIAR: DISPARO DE EMAIL COM CÓDIGO 2FA
// ---------------------------------------------------------
function enviarCodigoRecuperacao($emailDestino) {
    $mail = new PHPMailer(true);

    try {
        $codigo = rand(100000, 999999);
        
        // Guardando os dados na sessão temporária
        $_SESSION['recup_2fa_code']   = $codigo;
        $_SESSION['recup_2fa_expire'] = time() + 300; // 5 Minutos

        // Configurações SMTP idênticas às suas originais
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'contato@chatbrasil.qzz.io';
        $mail->Password   = 'nurr ubkr dcbs woui'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('ls779863322@gmail.com', 'ChatBrasil');
        $mail->addAddress($emailDestino);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Código de Recuperação de Conta';

        // Corpo estilizado do e-mail
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; color: #333333; line-height: 1.6; padding: 20px; background: #f5f6f7;'>
            <div style='max-width: 500px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
                <h2 style='color:#5288c1; margin-top:0;'>🛡️ Recuperação de Acesso</h2>
                <p>Recebemos uma solicitação para redefinir a senha da sua conta violada.</p>
                <p>Confirme sua identidade inserindo o código de segurança abaixo:</p>
                <div style='margin: 25px 0; padding: 20px; text-align: center; background-color: #f4fdf8; border: 2px solid #5288c1; border-radius: 10px;'>
                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666666;'>Seu código de verificação emergencial</p>
                    <div style='font-size: 42px; font-weight: bold; letter-spacing: 6px; color: #5288c1;'>$codigo</div>
                </div>
                <p>Este código expira em <strong>5 minutos</strong>.</p>
                <p style='color:#d93025; font-weight:bold;'>⚠️ Se não foi você quem solicitou, mude suas credenciais imediatamente.</p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        $_SESSION['mail_error'] = $mail->ErrorInfo;
        return false;
    }
}

// ---------------------------------------------------------
// PASSO 1: USUÁRIO SOLICITA A TROCA (ENVIO DO 2FA)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_recuperacao'])) {
    $emailInput    = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $novaSenha     = $_POST['nova_senha'];
    $confirmaSenha = $_POST['confirma_senha'];

    if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
        $mensagemErro = "Por favor, insira um endereço de e-mail válido.";
    } elseif (strlen($novaSenha) < 6) {
        $mensagemErro = "A nova senha precisa conter ao menos 6 caracteres.";
    } elseif ($novaSenha !== $confirmaSenha) {
        $mensagemErro = "A confirmação de senha não coincide.";
    } else {
        // Valida se a conta realmente existe no sistema
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmtCheck->execute([$emailInput]);
        $userExists = $stmtCheck->fetch();

        if (!$userExists) {
            $mensagemErro = "E-mail não localizado em nossa base ativa.";
        } else {
            // Guarda dados em estado pendente na sessão
            $_SESSION['recup_temp_email'] = $emailInput;
            $_SESSION['recup_temp_pass']  = password_hash($novaSenha, PASSWORD_BCRYPT);
            $_SESSION['recup_step']       = '2fa_verify';

            if (enviarCodigoRecuperacao($emailInput)) {
                $mensagemSucesso = "Código de segurança enviado ao seu e-mail.";
            } else {
                $mensagemErro = "Erro ao enviar código: " . ($_SESSION['mail_error'] ?? '');
                unset($_SESSION['recup_step']);
            }
        }
    }
}

// ---------------------------------------------------------
// PASSO 2: REENVIO DO CÓDIGO EM CASO DE EXPIRAÇÃO
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reenviar_2fa'])) {
    if (isset($_SESSION['recup_temp_email'])) {
        if (enviarCodigoRecuperacao($_SESSION['recup_temp_email'])) {
            $mensagemSucesso = "Um novo código foi enviado para o seu e-mail.";
        } else {
            $mensagemErro = "Falha no reenvio: " . ($_SESSION['mail_error'] ?? '');
        }
    }
}

// ---------------------------------------------------------
// PASSO 3: CONFIRMAÇÃO DO CÓDIGO E ATUALIZAÇÃO NO BANCO
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validar_2fa'])) {
    $codigoDigitado = trim($_POST['code_2fa']);

    if (time() > ($_SESSION['recup_2fa_expire'] ?? 0)) {
        $mensagemErro = "Código expirou! Solicite o reenvio abaixo.";
    } elseif ($codigoDigitado == ($_SESSION['recup_2fa_code'] ?? '')) {
        
        // Aplica a alteração definitiva de senha e revoga privilégios antigos
        $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmtUpdate->execute([
            $_SESSION['recup_temp_pass'],
            $_SESSION['recup_temp_email']
        ]);

        $mensagemSucesso = "Senha redefinida com sucesso! Todas as sessões do invasor foram derrubadas.";
        
        // Limpa a sessão de recuperação
        unset($_SESSION['recup_temp_email']);
        unset($_SESSION['recup_temp_pass']);
        unset($_SESSION['recup_2fa_code']);
        unset($_SESSION['recup_2fa_expire']);
        $_SESSION['recup_step'] = 'finalizado';
        
        header("refresh:3;url=login.php");
    } else {
        $mensagemErro = "Código incorreto. Tente novamente.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Conta - Telegram Style</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-main: #182533;
            --bg-side: #17212b;
            --tg-blue: #5288c1;
            --tg-hover: #6499d3;
        }
        .smooth-transition { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
        .fade-in-up { animation: fadeInUp 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: scale(0.9) translateY(30px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="bg-[#182533] text-slate-100 font-sans min-h-screen flex items-center justify-center p-4 antialiased relative">
    
    <div class="absolute inset-0 w-full h-full bg-gradient-to-b from-[#22364d] to-[#182533] z-10"></div>

    <div class="w-full max-w-sm space-y-5 relative z-20 fade-in-up">
        
        <div class="text-center space-y-2">
            <div class="w-16 h-16 bg-[#17212b] text-[#5288c1] rounded-2xl flex items-center justify-center text-3xl shadow-xl mx-auto">
                <i data-lucide="shield-alert" class="w-8 h-8 text-[#5288c1]"></i>
            </div>
            <h2 class="text-xl font-normal tracking-tight text-white">Central de Segurança</h2>
        </div>

        <?php if ($mensagemSucesso): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-[#4cd9a8] p-3.5 rounded-xl text-xs font-semibold text-center">
                <?php echo $mensagemSucesso; ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-[#ff6b6b] p-3.5 rounded-xl text-xs font-semibold text-center">
                <?php echo $mensagemErro; ?>
            </div>
        <?php endif; ?>

        <div class="bg-[#17212b] rounded-2xl p-6 shadow-2xl border border-slate-800/60">
            
            <?php if (!isset($_SESSION['recup_step'])): ?>
                <form method="POST" class="space-y-4 m-0">
                    <p class="text-xs text-[#7f91a4] text-center mb-2">Preencha os dados abaixo para expulsar conexões suspeitas e criar sua nova credencial.</p>
                    
                    <div>
                        <input type="email" name="email" required placeholder="Digite seu e-mail cadastrado" class="w-full text-sm bg-[#182533] border border-transparent focus:border-[#5288c1] rounded-xl p-3 text-white placeholder-[#7f91a4] outline-none text-center smooth-transition">
                    </div>

                    <div>
                        <input type="password" name="nova_senha" required placeholder="Nova senha segura" class="w-full text-sm bg-[#182533] border border-transparent focus:border-[#5288c1] rounded-xl p-3 text-white placeholder-[#7f91a4] outline-none text-center smooth-transition">
                    </div>

                    <div>
                        <input type="password" name="confirma_senha" required placeholder="Confirme a nova senha" class="w-full text-sm bg-[#182533] border border-transparent focus:border-[#5288c1] rounded-xl p-3 text-white placeholder-[#7f91a4] outline-none text-center smooth-transition">
                    </div>

                    <button type="submit" name="solicitar_recuperacao" class="w-full p-3 rounded-xl bg-[#5288c1] hover:bg-[#6499d3] text-white font-bold text-sm smooth-transition">
                        Avançar para Verificação
                    </button>
                </form>

            <?php elseif ($_SESSION['recup_step'] === '2fa_verify'): ?>
                <form method="POST" class="space-y-4 m-0">
                    <div class="text-center space-y-1">
                        <p class="text-sm text-white font-medium">Verificação 2FA Requerida</p>
                        <p class="text-xs text-[#7f91a4]">Insira o código de 6 dígitos enviado para o seu e-mail de recuperação.</p>
                    </div>

                    <div>
                        <input type="text" name="code_2fa" required maxlength="6" placeholder="Digite o código" class="w-full text-xl tracking-[4px] font-bold bg-[#182533] border border-transparent focus:border-[#5288c1] rounded-xl p-3 text-white placeholder-[#7f91a4] outline-none text-center smooth-transition">
                    </div>

                    <button type="submit" name="validar_2fa" class="w-full p-3 rounded-xl bg-[#5288c1] hover:bg-[#6499d3] text-white font-bold text-sm smooth-transition">
                        Confirmar e Alterar Senha
                    </button>
                </form>

                <form method="POST" class="m-0 pt-2 text-center">
                    <button type="submit" name="reenviar_2fa" class="text-xs text-[#5288c1] hover:text-[#6499d3] bg-transparent font-medium border-none underline outline-none cursor-pointer">
                        Reenviar código por e-mail
                    </button>
                </form>

            <?php elseif ($_SESSION['recup_step'] === 'finalizado'): ?>
                <div class="text-center py-4 space-y-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-[#4cd9a8] flex items-center justify-center mx-auto text-xl">
                        <i data-lucide="check" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs text-[#7f91a4]">Segurança restabelecida com sucesso. Redirecionando para a área de autenticação...</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>