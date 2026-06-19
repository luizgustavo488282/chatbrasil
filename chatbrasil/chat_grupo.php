<?php
session_start();

if (file_exists('confi.php')) {
    require_once 'confi.php';
}

if (!isset($conn) || $conn === null) {
    $servidor_db = "localhost";
    $usuario_db  = "root";
    $senha_db    = "";
    $nome_db     = "telegram_clone";
    $conn = new mysqli($servidor_db, $usuario_db, $senha_db, $nome_db);
    if ($conn->connect_error) {
        die("Erro ao conectar ao banco de dados: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}

if (!isset($_SESSION['user_id'])) {
    die("Você precisa estar logado.");
}
$user_id_logado = $_SESSION['user_id'];
$grupo_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Se tentar acessar o chat diretamente sem um ID, manda de volta para a lista
if ($grupo_id === 0) {
    header("Location: grupo.php");
    exit();
}

// Verificar se o usuário realmente pertence a este grupo
$stmt_check = $conn->prepare("SELECT g.nome FROM grupos g JOIN grupo_membros gm ON g.id = gm.grupo_id WHERE g.id = ? AND gm.user_id = ?");
$stmt_check->bind_param("ii", $grupo_id, $user_id_logado);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows == 0) {
    // Se não pertencer ou o grupo não existir, volta por segurança
    header("Location: grupo.php");
    exit();
}

$dados_grupo = $result_check->fetch_assoc();

// Buscar membros para a contagem da modal
$membros_grupo = [];
$query_membros = "SELECT u.id, u.username FROM grupo_membros gm JOIN users u ON gm.user_id = u.id WHERE gm.grupo_id = ? ORDER BY u.username ASC";
$stmt_memb = $conn->prepare($query_membros);
$stmt_memb->bind_param("i", $grupo_id);
$stmt_memb->execute();
$result_membros = $stmt_memb->get_result();
while ($row = $result_membros->fetch_assoc()) { $membros_grupo[] = $row; }
$total_membros = count($membros_grupo);

// Enviar nova mensagem
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enviar_mensagem'])) {
    $mensagem = trim($_POST['mensagem']);
    if (!empty($mensagem)) {
        $stmt_msg = $conn->prepare("INSERT INTO grupo_mensagens (grupo_id, user_id, mensagem) VALUES (?, ?, ?)");
        $stmt_msg->bind_param("iis", $grupo_id, $user_id_logado, $mensagem);
        $stmt_msg->execute();
        
        header("Location: chat_grupo.php?id=" . $grupo_id);
        exit();
    }
}

// Buscar as mensagens do grupo
$query_mensagens = "SELECT gm.*, u.username AS nome_usuario FROM grupo_mensagens gm JOIN users u ON gm.user_id = u.id WHERE gm.grupo_id = ? ORDER BY gm.id ASC";
$stmt_msgs = $conn->prepare($query_mensagens);
$stmt_msgs->bind_param("i", $grupo_id);
$stmt_msgs->execute();
$mensagens_resultado = $stmt_msgs->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($dados_grupo['nome']); ?></title>
    <style>
        * { 
            box-sizing: border-box; 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            -webkit-font-smoothing: antialiased; 
            -moz-osx-font-smoothing: grayscale;
            -webkit-tap-highlight-color: transparent;
        }
        
        body { 
            margin: 0; 
            padding: 0; 
            background-color: #0e1621; 
            display: flex; 
            justify-content: center; 
            overflow: hidden; 
            height: 100vh;
            height: -webkit-fill-available;
        }
        
        /* Container Principal ajustado para Modo Mobile Inteiro (Full Width) */
        .chat-app { 
            width: 100%; 
            max-width: 100%; /* Ocupa 100% da largura da tela no mobile */
            height: 100%; 
            display: flex; 
            flex-direction: column; 
            background: #0e1621; 
            position: relative; 
            box-shadow: none; /* Removido sombras laterais para mesclar perfeitamente com as bordas do aparelho */
        }
        
        /* Header idêntico ao Telegram Mobile */
        .chat-header { 
            background-color: #17212b; 
            color: white; 
            padding: 10px 14px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            border-bottom: 1px solid rgba(0,0,0,0.15); 
            z-index: 10; 
            height: 56px; 
            flex-shrink: 0;
            user-select: none;
        }
        
        .back-btn { 
            text-decoration: none; 
            color: #5288c1; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            transition: background-color 0.2s;
        }
        .back-btn:active {
            background-color: rgba(255, 255, 255, 0.08);
        }
        .back-btn svg {
            width: 24px;
            height: 24px;
            fill: #5288c1;
        }
        
        .group-avatar-header {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #629fa2, #407679);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 15px;
            text-transform: uppercase;
            user-select: none;
        }
        
        .group-info { 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            cursor: pointer; 
            flex: 1; 
            min-width: 0; 
            padding-left: 4px;
        }
        .group-name { 
            font-size: 16px; 
            font-weight: 600; 
            color: #ffffff; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            line-height: 1.2;
        }
        .group-status { 
            font-size: 13px; 
            color: #7f91a4; 
            margin-top: 2px; 
            line-height: 1;
        }

        /* Container de Mensagens com scroll suave e padding dinâmico */
        .chat-container { 
            flex: 1; 
            padding: 16px 12px; 
            overflow-y: auto; 
            display: flex; 
            flex-direction: column; 
            gap: 6px; 
            background-color: #0e1621; 
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        .chat-container::-webkit-scrollbar { 
            width: 4px; 
        }
        .chat-container::-webkit-scrollbar-thumb { 
            background: rgba(255, 255, 255, 0.08); 
            border-radius: 4px; 
        }
        
        /* Balões de Mensagem de Alta Fidelidade */
        .msg-box { 
            max-width: 82%; /* Leve aumento para melhor legibilidade em telas inteiras */
            padding: 6px 10px 6px 12px; 
            border-radius: 12px; 
            font-size: 15px; 
            line-height: 1.42; 
            position: relative; 
            box-shadow: 0 1px 1px rgba(0,0,0,0.2); 
            display: flex; 
            flex-direction: column; 
            animation: msgPop 0.15s cubic-bezier(0.1, 1, 0.1, 1) forwards;
        }
        @keyframes msgPop {
            from { transform: scale(0.96); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .msg-recebida { 
            background-color: #182533; 
            align-self: flex-start; 
            border-bottom-left-radius: 4px; 
            color: #f5f5f5; 
        }
        .msg-enviada { 
            background-color: #2b5278; 
            align-self: flex-end; 
            border-bottom-right-radius: 4px; 
            color: #ffffff; 
        }
        
        .msg-user { 
            font-weight: 600; 
            color: #5288c1; 
            font-size: 13px; 
            margin-bottom: 2px; 
            user-select: none;
        }
        
        /* Gerenciamento inteligente de espaço interno para os metadados (Hora/Check) */
        .msg-text-area { 
            word-wrap: break-word;
            word-break: break-word;
            padding-right: 46px; 
            display: inline-block; 
        }
        
        /* Alinhamento de Metadados Absoluto Interno */
        .msg-meta { 
            font-size: 11px; 
            color: #7f91a4; 
            display: flex; 
            align-items: center; 
            gap: 2px; 
            position: absolute; 
            right: 8px; 
            bottom: 4px; 
            user-select: none; 
            line-height: 1;
        }
        .msg-enviada .msg-meta { 
            color: #a2bccc; 
        }
        .status-check { 
            display: inline-flex;
            align-items: center;
        }
        .status-check svg {
            width: 15px;
            height: 15px;
            fill: #40df9f;
        }

        /* Footer Flutuante Premium */
        .chat-footer-wrapper { 
            background-color: #0e1621; 
            padding: 8px 10px; 
            flex-shrink: 0;
            padding-bottom: calc(8px + env(safe-area-inset-bottom));
        }
        .chat-footer { 
            display: flex; 
            gap: 8px; 
            align-items: flex-end; 
        }
        .input-container { 
            flex: 1; 
            background-color: #17212b; 
            border-radius: 22px; 
            display: flex; 
            align-items: center; 
            padding: 0 16px; 
            min-height: 44px; 
            border: 1px solid rgba(255,255,255,0.02);
        }
        .chat-footer input[type="text"] { 
            flex: 1; 
            border: none; 
            background: transparent; 
            padding: 10px 0; 
            font-size: 16px; 
            outline: none; 
            color: #ffffff; 
        }
        .chat-footer input[type="text"]::placeholder { 
            color: #7f91a4; 
        }
        
        /* Botão Enviar Circular com Ícone Nativo */
        .chat-footer button { 
            background-color: #5288c1; 
            color: white; 
            border: none; 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.3); 
            transition: background-color 0.2s, transform 0.1s; 
        }
        .chat-footer button:active { 
            transform: scale(0.95);
            background-color: #4a7cae;
        }
        .chat-footer button svg {
            width: 20px;
            height: 20px;
            fill: #ffffff;
            margin-left: 2px;
        }

        /* Janela Modal de Membros Nativa com efeito Slide-Up */
        .modal-overlay { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0, 0, 0, 0.56); 
            display: none; 
            justify-content: center; 
            align-items: flex-end; 
            z-index: 100; 
            backdrop-filter: blur(2px);
        }
        .modal-content { 
            width: 100%; 
            background: #17212b; 
            border-top-left-radius: 16px; 
            border-top-right-radius: 16px; 
            max-height: 80%; 
            display: flex; 
            flex-direction: column; 
            transform: translateY(100%); 
            transition: transform 0.22s cubic-bezier(0, 0, 0.2, 1); 
            padding-bottom: env(safe-area-inset-bottom);
        }
        .modal-header { 
            padding: 16px 20px; 
            background: #17212b; 
            border-top-left-radius: 16px; 
            border-top-right-radius: 16px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid rgba(255,255,255,0.05); 
        }
        .modal-title { 
            font-size: 17px; 
            font-weight: 600; 
            color: #ffffff; 
        }
        .modal-close { 
            background: none; 
            border: none; 
            color: #5288c1; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            padding: 4px 8px;
        }
        .modal-body { 
            padding: 8px; 
            overflow-y: auto; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
        }
        .modal-body::-webkit-scrollbar {
            width: 4px;
        }
        .modal-body::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.05);
            border-radius: 4px;
        }
        
        .member-item { 
            display: flex; 
            align-items: center; 
            padding: 10px 12px; 
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        .member-item:active {
            background-color: rgba(255, 255, 255, 0.04);
        }
        .member-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, #5288c1, #2b5278); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 15px; 
            margin-right: 14px; 
            text-transform: uppercase; 
            user-select: none;
        }
        .member-name { 
            font-size: 15px; 
            color: #ffffff; 
            font-weight: 500;
        }
    </style>
</head>
<body>

<div class="chat-app">
    <div class="chat-header">
        <a href="grupo.php" class="back-btn" aria-label="Voltar">
            <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        </a> 
        <div class="group-avatar-header">
            <?php echo mb_substr($dados_grupo['nome'], 0, 1); ?>
        </div>
        <div class="group-info" onclick="abrirModalMembros()">
            <div class="group-name"><?php echo htmlspecialchars($dados_grupo['nome']); ?></div>
            <div class="group-status"><?php echo $total_membros; ?> membros</div>
        </div>
    </div>

    <div class="chat-container" id="chatContainer">
        <?php while($msg = $mensagens_resultado->fetch_assoc()): 
            $es_minha = ($msg['user_id'] == $user_id_logado);
            $classe_msg = $es_minha ? 'msg-enviada' : 'msg-recebida';
            $hora_formatada = date('H:i', strtotime($msg['data_envio']));
        ?>
            <div class="msg-box <?php echo $classe_msg; ?>">
                <?php if(!$es_minha): ?>
                    <div class="msg-user"><?php echo htmlspecialchars($msg['nome_usuario']); ?></div>
                <?php endif; ?>
                
                <div class="msg-text-area">
                    <?php echo htmlspecialchars($msg['mensagem']); ?>
                </div>
                
                <div class="msg-meta">
                    <span><?php echo $hora_formatada; ?></span>
                    <?php if($es_minha): ?>
                        <span class="status-check">
                            <svg viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM1 14l1.41-1.41 4.59 4.59L5.59 18.59 1 14z"/></svg>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="chat-footer-wrapper">
        <form class="chat-footer" method="POST" action="">
            <div class="input-container">
                <input type="text" name="mensagem" placeholder="Mensagem" autocomplete="off" required>
            </div>
            <button type="submit" name="enviar_mensagem" aria-label="Enviar Mensagem">
                <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>
    </div>

    <div class="modal-overlay" id="modalMembros" onclick="fecharModalMembros(event)">
        <div class="modal-content" id="modalContent">
            <div class="modal-header">
                <div class="modal-title">Membros</div>
                <button type="button" class="modal-close" onclick="fecharModalMembros(null)">Fechar</button>
            </div>
            <div class="modal-body">
                <?php foreach ($membros_grupo as $membro): 
                    $inicial_membro = mb_substr($membro['username'], 0, 1);
                ?>
                    <div class="member-item">
                        <div class="member-avatar"><?php echo htmlspecialchars($inicial_membro); ?></div>
                        <div class="member-name">
                            <?php echo htmlspecialchars($membro['username']); ?>
                            <?php if ($membro['id'] == $user_id_logado) echo " <span style='color: #7f91a4; font-size: 13px; font-weight: normal;'>(Você)</span>"; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Rolagem automática imediata para o final do chat
    var chatContainer = document.getElementById("chatContainer");
    if(chatContainer) { 
        chatContainer.scrollTop = chatContainer.scrollHeight; 
    }

    // Gerenciador de Animação e Estados da Modal (Slide-Up)
    function abrirModalMembros() {
        const overlay = document.getElementById('modalMembros');
        const content = document.getElementById('modalContent');
        overlay.style.display = 'flex';
        setTimeout(() => { 
            content.style.transform = 'translateY(0)'; 
        }, 10);
    }

    function fecharModalMembros(event) {
        if (event && event.target !== document.getElementById('modalMembros')) return;
        const overlay = document.getElementById('modalMembros');
        const content = document.getElementById('modalContent');
        content.style.transform = 'translateY(100%)';
        setTimeout(() => { 
            overlay.style.display = 'none'; 
        }, 220);
    }
</script>

</body>
</html>