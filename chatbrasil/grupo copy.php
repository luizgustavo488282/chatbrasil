<?php
session_start();

if (file_exists('confi.php')) {
    require_once 'confi.php';
}

// Adaptação para usar PDO (padrão do seu login.php) em vez de mysqli
if (!isset($pdo) || $pdo === null) {
    try {
        $servidor_db = "localhost";
        $usuario_db  = "root";
        $senha_db    = "";
        $nome_db     = "telegram_clone";
        $pdo = new PDO("mysql:host=$servidor_db;dbname=$nome_db;charset=utf8mb4", $usuario_db, $senha_db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco de dados: " . $e->getMessage());
    }
}

if (!isset($_SESSION['user_id'])) {
    die("Você precisa estar logado.");
}
$user_id_logado = $_SESSION['user_id'];

// --- Buscar o username real do usuário logado na tabela 'users' ---
$nome_usuario_logado = "";
$query_user_real = "SELECT username FROM users WHERE id = ?";
$stmt_user_real = $pdo->prepare($query_user_real);
if ($stmt_user_real) {
    $stmt_user_real->execute([$user_id_logado]);
    $row_user_real = $stmt_user_real->fetch(PDO::FETCH_ASSOC);
    if ($row_user_real) {
        $nome_usuario_logado = $row_user_real['username'];
    }
}

// Fallback caso a tabela falhe
if (empty($nome_usuario_logado)) {
    $nome_usuario_logado = isset($_SESSION['username']) ? $_SESSION['username'] : "Usuário";
}

// Buscar a lista de todos os grupos que o usuário logado pertence
$lista_grupos_usuario = [];
$query_lista_grupos = "
    SELECT g.id, g.nome 
    FROM grupos g 
    JOIN grupo_membros gm ON g.id = gm.grupo_id 
    WHERE gm.user_id = ? 
    ORDER BY g.nome ASC
";
$stmt_lista = $pdo->prepare($query_lista_grupos);
if ($stmt_lista) {
    $stmt_lista->execute([$user_id_logado]);
    while ($row_grupo = $stmt_lista->fetch(PDO::FETCH_ASSOC)) {
        $lista_grupos_usuario[] = $row_grupo;
    }
} else {
    die("Erro na estrutura da tabela de grupos.");
}

// Se o usuário não pertence a grupo nenhum
if (empty($lista_grupos_usuario)) {
    die("<div style='background:#111b21; color:#fff; height:100vh; display:flex; justify-content:center; align-items:center; font-family:sans-serif; flex-direction:column; gap:10px;'><h2>Você não está em nenhum grupo!</h2><p style='color:#8696a0;'>Cadastre-se em um grupo antes de acessar.</p></div>");
}

// Pegar ID do grupo atual ativo (por padrão o primeiro da lista)
$grupo_atual_id = $lista_grupos_usuario[0]['id'];

// Buscar os membros deste grupo atual puxando a coluna 'username' correta da tabela 'users'
$membros_grupo = [];
$query_membros = "
    SELECT u.id, u.username AS nome 
    FROM users u
    JOIN grupo_membros gm ON u.id = gm.user_id
    WHERE gm.grupo_id = ?
    ORDER BY u.username ASC
";

$stmt_membros = $pdo->prepare($query_membros);

if ($stmt_membros) {
    $stmt_membros->execute([$grupo_atual_id]);
    while ($row_membro = $stmt_membros->fetch(PDO::FETCH_ASSOC)) {
        $membros_grupo[] = $row_membro;
    }
}

// Se a listagem de membros falhar, insere o usuário atual dinamicamente com seu nome real
if (empty($membros_grupo)) {
    $membros_grupo[] = ['id' => $user_id_logado, 'nome' => $nome_usuario_logado];
}

$total_membros = count($membros_grupo);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Telegram Mobile</title>
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
            box-sizing: border-box; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            -webkit-font-smoothing: antialiased; 
            margin: 0;
            padding: 0;
        }
        
        body { 
            background: var(--bg-main); 
            color: var(--text-color); 
            display: flex; 
            justify-content: center; 
            align-items: center;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* Fundo Dinâmico Idêntico ao do Login */
        .bg-gradient-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top, #22364d, var(--bg-main));
            z-index: 1;
        }
        
        /* CONTAINER PRINCIPAL RESPONSIVO (IGUAL AO LOGIN) */
        .app-container { 
            position: relative;
            width: 360px; 
            height: 85vh; 
            max-height: 740px;
            background-color: #0e1621; /* Azul escuro oficial de fundo do chat do Telegram */
            display: flex; 
            flex-direction: column; 
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            overflow: hidden;
            z-index: 2;
            animation: tgFadeIn 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
        }

        /* Efeito de surgimento elástico herdado do login */
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

        /* MODO MOBILE 100% (HERDADO COMPLETO DO LOGIN) */
        @media (max-width: 480px) {
            body {
                align-items: flex-start;
                background: var(--bg-side);
            }
            .bg-gradient-overlay {
                display: none;
            }
            .app-container {
                width: 100%;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
                box-shadow: none;
                animation: tgFadeInMobile 0.45s cubic-bezier(0.1, 0.8, 0.25, 1) forwards;
            }
            @keyframes tgFadeInMobile {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
        
        /* Cabeçalho superior */
        .header-top { 
            background-color: #17212b; /* Cor oficial das barras do Telegram */
            padding: 10px 14px; 
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 60px;
            flex-shrink: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .header-clickable-area {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            flex: 1;
        }

        .avatar-user-logged {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--tg-blue); /* Cor Azul Telegram */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
        }

        .header-actions {
            display: flex;
            gap: 18px;
            color: #7f91a4;
            cursor: pointer;
        }
        
        /* ÁREA DE MENSAGENS */
        .chat-messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 14px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end; 
            gap: 10px;
        }

        .msg-line {
            display: flex;
            width: 100%;
        }
        .msg-line.sent { justify-content: flex-end; }
        .msg-line.received { justify-content: flex-start; }

        .msg-bubble {
            max-width: 75%;
            padding: 8px 12px;
            border-radius: 8px; /* Arredondamento padrão por igual estilo Telegram */
            font-size: 14.5px;
            line-height: 19px;
            position: relative;
            box-shadow: 0 1px 1px rgba(0,0,0,0.15);
            word-wrap: break-word;
        }
        .msg-line.sent .msg-bubble {
            background-color: #2b5278; /* Azul Oficial Telegram para Balão Enviado */
            color: #f5f5f5;
        }
        .msg-line.received .msg-bubble {
            background-color: #182533; /* Cinza-Azulado Telegram para Balão Recebido */
            color: #f5f5f5;
        }

        .msg-meta {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 10px;
            color: #7f91a4;
            float: right;
            margin-top: 6px;
            margin-left: 8px;
        }

        .status-ticks {
            display: inline-flex;
            align-items: center;
        }
        .status-ticks.sent-only { color: #7f91a4; }     
        .status-ticks.viewed { color: #5288c1; } /* Ticks azulados do Telegram */       

        /* RODAPÉ E BARRA DE DIGITAÇÃO */
        .chat-input-footer {
            background: #17212b; /* Fundo sólido na barra inferior estilo Telegram */
            padding: 8px 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            flex-shrink: 0;
            z-index: 10;
        }

        .input-bar-wrapper {
            flex: 1;
            background-color: transparent;
            border-radius: 0;
            display: flex;
            align-items: center;
            padding: 2px 4px;
            gap: 10px;
        }

        .footer-icon-btn {
            color: #7f91a4;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .message-form {
            flex: 1;
            display: flex;
            align-items: center;
        }

        .message-input {
            width: 100%;
            background: none;
            border: none;
            padding: 10px 0;
            color: #f5f5f5;
            font-size: 15px;
            outline: none;
        }
        
        .message-input::placeholder {
            color: #7f91a4;
        }

        .send-btn-circle {
            width: 45px;
            height: 45px;
            background-color: transparent; /* No Telegram o botão de envio se mescla ou é apenas o ícone azul */
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--tg-blue); /* Ícone Azul */
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .send-btn-circle:active {
            transform: scale(0.95);
        }

        /* MODAL DE MEMBROS - ESTILO TELEGRAM COMPLETO (Fundo e cabeçalho Azul) */
        .telegram-members-modal {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #182533; 
            z-index: 100;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
        }

        .telegram-members-modal.active {
            transform: translateY(0);
        }

        .tg-modal-header {
            background-color: #202b36; 
            padding: 14px;
            display: flex;
            align-items: center;
            gap: 16px;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            border-bottom: 1px solid #141f29;
        }

        .tg-back-btn {
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2f8cc9; 
        }

        .tg-modal-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }

        .tg-section-title {
            padding: 10px 16px;
            font-size: 14px;
            color: #2f8cc9; 
            font-weight: bold;
        }

        .tg-member-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 16px;
            cursor: pointer;
        }

        .tg-member-item:active {
            background-color: #202b36;
        }

        .tg-member-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #50b5e9, #3a97d1); 
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #fff;
            font-size: 16px;
        }

        .tg-member-info {
            display: flex;
            flex-direction: column;
        }

        .tg-member-name {
            font-size: 15px;
            font-weight: 500;
            color: #ffffff;
        }

        .tg-member-status {
            font-size: 13px;
            color: #7f91a4; 
        }
    </style>
</head>
<body>

<div class="bg-gradient-overlay"></div>

<div class="app-container">
    
    <div class="header-top">
        <div class="header-clickable-area" onclick="openTelegramModal()">
            <div class="avatar-user-logged">
                <?php echo htmlspecialchars(mb_substr($lista_grupos_usuario[0]['nome'], 0, 1)); ?>
            </div>
            <div>
                <div style="font-size:15px; font-weight:500; color:#e9edef;"><?php echo htmlspecialchars($lista_grupos_usuario[0]['nome']); ?></div>
                <div style="font-size:11px; color:#7f91a4;"><?php echo $total_membros; ?> membros</div>
            </div>
        </div>
        <div class="header-actions">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
        </div>
    </div>

    <div class="chat-messages-container" id="chat-messages">
        <div class="msg-line received">
            <div class="msg-bubble">
                Seja bem-vindo ao grupo, <?php echo htmlspecialchars($nome_usuario_logado); ?>! As mensagens antigas ficam guardadas aqui.
                <span class="msg-meta">11:40</span>
            </div>
        </div>
        <div class="msg-line sent">
            <div class="msg-bubble">
                Opa! Agora sim está em modo mobile e colado lá embaixo por padrão!
                <span class="msg-meta">
                    11:42
                    <span class="status-ticks viewed">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l5 5L22 4"/><path d="M16 12l-2.5 2.5M7.5 12l2.5 2.5"/></svg>
                    </span>
                </span>
            </div>
        </div>
    </div>

    <div class="chat-input-footer">
        <div class="input-bar-wrapper">
            <div class="footer-icon-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
            </div>
            
            <form class="message-form" method="POST" action="" onsubmit="addMessagePlaceholder(event)">
                <input type="text" id="msg-text-input" class="message-input" placeholder="Mensagem" autocomplete="off">
            </form>

            <div class="footer-icon-btn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
            </div>
        </div>

        <button type="button" class="send-btn-circle" onclick="document.querySelector('.message-form').dispatchEvent(new Event('submit'))">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
        </button>
    </div>

    <div class="telegram-members-modal" id="telegram-modal">
        <div class="tg-modal-header">
            <div class="tg-back-btn" onclick="closeTelegramModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
            </div>
            <div>
                <div style="font-size: 18px; font-weight: bold; color: #fff;"><?php echo htmlspecialchars($lista_grupos_usuario[0]['nome']); ?></div>
                <div style="font-size: 13px; color: #7f91a4;"><?php echo $total_membros; ?> membros</div>
            </div>
        </div>
        
        <div class="tg-modal-content">
            <div class="tg-section-title">Membros</div>
            
            <?php foreach ($membros_grupo as $membro): ?>
                <div class="tg-member-item">
                    <div class="tg-member-avatar">
                        <?php echo htmlspecialchars(mb_substr($membro['nome'], 0, 1)); ?>
                    </div>
                    <div class="tg-member-info">
                        <span class="tg-member-name">
                            <?php 
                                echo htmlspecialchars($membro['nome']); 
                                if($membro['id'] == $user_id_logado) { echo " (Você)"; }
                            ?>
                        </span>
                        <span class="tg-member-status">online</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<script>
    const chatContainer = document.getElementById('chat-messages');
    const telegramModal = document.getElementById('telegram-modal');
    
    chatContainer.scrollTop = chatContainer.scrollHeight;

    function openTelegramModal() {
        telegramModal.classList.add('active');
    }

    function closeTelegramModal() {
        telegramModal.classList.remove('active');
    }

    function addMessagePlaceholder(e) {
        e.preventDefault();
        const input = document.getElementById('msg-text-input');
        if(!input.value.trim()) return;

        const now = new Date();
        const timeStr = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');

        const msgLine = document.createElement('div');
        msgLine.className = 'msg-line sent';
        
        msgLine.innerHTML = `
            <div class="msg-bubble">
                ${input.value.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                <span class="msg-meta">
                    ${timeStr}
                    <span class="status-ticks sent-only">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12l5 5L22 4"/><path d="M16 12l-2.5 2.5M7.5 12l2.5 2.5"/></svg>
                    </span>
                </span>
            </div>
        `;
        
        chatContainer.appendChild(msgLine);
        
        const currentTicks = msgLine.querySelector('.status-ticks');
        setTimeout(() => {
            if(currentTicks) {
                currentTicks.className = "status-ticks viewed";
            }
        }, 2000);

        input.value = '';
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
</script>

</body>
</html>