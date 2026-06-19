<?php
// admin/alterar_senhas.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_clone');
define('DB_USER', 'root');
define('DB_PASS', '');

$usuarioAlvo = null;
$mensagemSucesso = null;
$mensagemErro = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Captura o ID vindo do clique no index.php
    $userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($userId > 0) {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $usuarioAlvo = $stmt->fetch();
    }

    // Se o usuário não existir no banco, volta pro index
    if (!$usuarioAlvo) {
        header("Location: index.php");
        exit;
    }

    // Processamento do Formulário de Envio de E-mail
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disparar_email'])) {
        $emailDestino = $_POST['user_email'];
        $nomeDestino  = $_POST['user_username'];
        $assunto      = $_POST['email_subject'];
        $conteudo     = $_POST['email_body'];

        // Cabeçalhos profissionais para e-mail HTML
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Suporte Telegram Clone <noreply@seuprovedor.com>" . "\r\n";

        // Montagem do Layout HTML do E-mail
        $corpoHTML = "
        <div style='background-color: #f8fafc; padding: 40px; font-family: sans-serif; text-align: center;'>
            <div style='max-width: 500px; background-color: #ffffff; margin: 0 auto; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; text-align: left;'>
                <h2 style='color: #0ea5e9; margin-top: 0;'>Olá, {$nomeDestino}!</h2>
                <p style='color: #475569; font-size: 14px; line-height: 1.6;'>Você recebeu uma notificação administrativa de redefinição de segurança para a sua conta.</p>
                <div style='background-color: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0; color: #1e293b; font-size: 13px; font-weight: bold;'>
                    {$conteudo}
                </div>
                <p style='color: #94a3b8; font-size: 11px; margin-top: 30px;'>Esta é uma mensagem automática gerada pelo Terminal de Controle.</p>
            </div>
        </div>";

        // Disparo real usando a função nativa mail() do PHP
        if (mail($emailDestino, $assunto, $corpoHTML, $headers)) {
            $mensagemSucesso = "E-mail de redefinição enviado com sucesso para " . htmlspecialchars($emailDestino);
        } else {
            $mensagemErro = "O servidor PHP não conseguiu processar o disparo local do e-mail. Verifique a diretiva sendmail no php.ini.";
        }
    }

} catch (PDOException $e) {
    $mensagemErro = "Erro na conexão: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de Senha - Ultimate Core</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .smooth-transition { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
        .fade-in-up { animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans antialiased">

    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-50 px-6 py-3.5 shadow-sm">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 bg-amber-500 text-white rounded-xl flex items-center justify-center font-black text-lg shadow-md shadow-amber-500/20">TG</div>
                <div>
                    <h2 class="text-sm font-black text-slate-900 leading-tight tracking-tight">Segurança Global</h2>
                    <span class="text-[11px] text-amber-600 font-bold flex items-center gap-1 mt-0.5">Módulo de Credenciais</span>
                </div>
            </div>
            <a href="index.php" class="px-4 py-2 text-xs font-bold rounded-lg smooth-transition bg-slate-100 text-slate-700 hover:bg-slate-200 shadow-sm flex items-center gap-2 border border-slate-200/40">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar ao Painel
            </a>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto p-4 md:p-12 space-y-6 butch-fade fade-in-up">
        
        <?php if ($mensagemSucesso): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs font-bold shadow-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                <span><?php echo $mensagemSucesso; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-xs font-bold shadow-sm flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                <span><?php echo $mensagemErro; ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50">
                <h1 class="text-base font-black text-slate-900 tracking-tight">Disparador de Segurança E-mail</h1>
                <p class="text-xs font-medium text-slate-500 mt-1">Preencha as diretrizes abaixo para enviar as instruções de nova senha para a conta selecionada.</p>
            </div>

            <form method="POST" class="p-6 space-y-4 m-0">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1.5">Nome capturado</label>
                        <input type="text" name="user_username" readonly value="<?php echo htmlspecialchars($usuarioAlvo['username']); ?>" class="w-full text-xs font-bold bg-slate-100 border border-slate-200 rounded-xl p-3 text-slate-500 cursor-not-allowed focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1.5">E-mail destino</label>
                        <input type="email" name="user_email" readonly value="<?php echo htmlspecialchars($usuarioAlvo['email']); ?>" class="w-full text-xs font-bold bg-slate-100 border border-slate-200 rounded-xl p-3 text-slate-500 cursor-not-allowed focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1.5">Assunto da Mensagem</label>
                    <input type="text" name="email_subject" required value="Recuperação de Acesso - Chatbrasil" class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl p-3 text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 smooth-transition shadow-sm">
                </div>

                <div>
                    <label class="block text-[11px] font-black uppercase text-slate-400 tracking-wider mb-1.5">Instruções / Conteúdo do E-mail</label>
                    <textarea name="email_body" rows="5" required class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl p-3 text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 smooth-transition shadow-sm">Uma solicitação de redefinição de credenciais foi efetuada pelo Administrador. Para definir sua nova senha de acesso, por favor utilize o link temporário enviado nos seus canais de autenticação.</textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 flex justify-end">
                    <button type="submit" name="disparar_email" class="px-5 py-2.5 bg-amber-500 text-white rounded-xl text-xs font-black shadow-md shadow-amber-500/20 hover:bg-amber-600 smooth-transition flex items-center gap-2">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i> Despachar Notificação
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>