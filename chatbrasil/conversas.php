<?php
// Define o fuso horário padrão corrigido logo no início de tudo
date_default_timezone_set('America/Sao_Paulo');

require_once 'confi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$chatId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$agoraLocal = date('Y-m-d H:i:s'); // Força o fuso horário de São Paulo para gravar no banco

// Garante que as colunas necessárias existam no seu banco de dados
try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN is_typing INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN last_typing_id INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE messages ADD COLUMN reply_to_id INT DEFAULT NULL"); } catch (Exception $e) {}

/* ========================================================
   AÇÕES DO JAVASCRIPT: DIGITANDO / PING / GET STATUS / PARAR LOCALIZAÇÃO
======================================================== */
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'typing') {
        header('Content-Type: application/json');
        $statusTyping = isset($_GET['status']) ? (int)$_GET['status'] : 0;
        
        $stmt = $pdo->prepare("UPDATE users SET is_typing = ?, last_typing_id = ?, last_activity = ? WHERE id = ?");
        $stmt->execute([$statusTyping, $chatId, $agoraLocal, $userId]);
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    if ($_GET['action'] === 'ping') {
        header('Content-Type: application/json');
        $stmt = $pdo->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
        $stmt->execute([$agoraLocal, $userId]);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($_GET['action'] === 'get_status') {
        header('Content-Type: application/json');
        // Força novamente o fuso horário dentro do escopo da requisição assíncrona
        date_default_timezone_set('America/Sao_Paulo');
        
        $stmt = $pdo->prepare("SELECT last_activity, is_typing, last_typing_id FROM users WHERE id = ?");
        $stmt->execute([$chatId]);
        $res = $stmt->fetch();
        
        $statusText = "offline";
        if ($res) {
            $lastSeenTime = strtotime($res['last_activity']);
            $tempoAtualFuso = strtotime(date('Y-m-d H:i:s')); // CORREÇÃO CRÍTICA: Sincroniza o relógio do AJAX
            $diff = $tempoAtualFuso - $lastSeenTime;
            
            if ((int)$res['is_typing'] === 1 && (int)$res['last_typing_id'] === $userId) {
                $statusText = "digitando...";
            } elseif ($diff <= 120) { // Tolerância estável de 2 minutos
                $statusText = "online";
            } else {
                $horaFormatada = date('H:i', $lastSeenTime);
                $hojeFuso = strtotime(date('Y-m-d 00:00:00')); // CORREÇÃO CRÍTICA: Meia-noite localizada via AJAX
                $ontemFuso = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))); // CORREÇÃO CRÍTICA: Ontem localizado via AJAX
                
                if ($lastSeenTime >= $hojeFuso) {
                    $statusText = "visto por último hoje às " . $horaFormatada;
                } elseif ($lastSeenTime >= $ontemFuso) {
                    $statusText = "visto por último ontem às " . $horaFormatada;
                } else {
                    $statusText = "visto por último em " . date('d/m/Y', $lastSeenTime) . " às " . $horaFormatada;
                }
            }
        }
        echo json_encode(['statusText' => $statusText]);
        exit;
    }

    if ($_GET['action'] === 'stop_location') {
        header('Content-Type: application/json');
        $msgId = isset($_GET['msg_id']) ? (int)$_GET['msg_id'] : 0;
        if ($msgId > 0) {
            $stmt = $pdo->prepare("UPDATE messages SET message = 'LOCALIZACAO_ENCERRADA' WHERE id = ? AND sender_id = ?");
            $stmt->execute([$msgId, $userId]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }
}

/* ========================================================
   ENVIAR MENSAGEM (POST ASSÍNCRONO VIA FETCH API)
======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $msg = trim($_POST['message'] ?? '');
    $isViewOnce = isset($_POST['view_once']) ? (int)$_POST['view_once'] : 0;
    $isAudio = isset($_POST['is_audio']) ? (int)$_POST['is_audio'] : 0;
    $replyToId = isset($_POST['reply_to_id']) && (int)$_POST['reply_to_id'] > 0 ? (int)$_POST['reply_to_id'] : null;

    if (($msg !== '' || !empty($_FILES['media']['name'])) && $chatId > 0) {
        $mediaUrl = null;
        $mediaType = null;

        if (!empty($_FILES['media']['name'])) {
            $dir = "uploads/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);

            $rawFileName = basename($_FILES['media']['name']);
            $ext = strtolower(pathinfo($rawFileName, PATHINFO_EXTENSION));

            if (in_array($ext, ['php', 'exe', 'sh', 'phtml'])) {
                echo json_encode(['status' => 'error', 'message' => 'Arquivo proibido']);
                exit;
            }

            $fileName = time() . "_" . uniqid() . "." . $ext;
            $target = $dir . $fileName;

            if (move_uploaded_file($_FILES['media']['tmp_name'], $target)) {
                $mediaUrl = $target;
                $mediaType = $_FILES['media']['type'];
            }
        } else if ($isAudio === 1 && isset($_FILES['media'])) {
            $dir = "uploads/";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $fileName = time() . "_" . uniqid() . ".ogg";
            $target = $dir . $fileName;
            if (move_uploaded_file($_FILES['media']['tmp_name'], $target)) {
                $mediaUrl = $target;
                $mediaType = 'audio/ogg';
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, media_url, media_type, is_read, is_view_once, is_audio, reply_to_id)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
        ");
        $stmt->execute([$userId, $chatId, $msg !== '' ? $msg : null, $mediaUrl, $mediaType, $isViewOnce, $isAudio, $replyToId]);
        
        $stmtCancelTyping = $pdo->prepare("UPDATE users SET is_typing = 0 WHERE id = ?");
        $stmtCancelTyping->execute([$userId]);

        echo json_encode(['status' => 'success']);
        exit;
    }
    echo json_encode(['status' => 'empty']);
    exit;
}

/* ========================================================
   BUSCA DE DADOS DO USUÁRIO DO TOPO
======================================================== */
$user = ['username' => 'Usuário', 'avatar' => 'default.png', 'is_verified' => 0, 'last_activity' => '', 'is_typing' => 0, 'last_typing_id' => 0];
if ($chatId > 0) {
    $stmt = $pdo->prepare("SELECT username, avatar, is_verified, last_activity, is_typing, last_typing_id FROM users WHERE id = ?");
    $stmt->execute([$chatId]);
    $res = $stmt->fetch();
    if ($res) {
        $user['username'] = $res['username'];
        $user['avatar'] = $res['avatar'] ?: 'default.png';
        $user['is_verified'] = (int)$res['is_verified'];
        $user['last_activity'] = $res['last_activity'] ?: '';
        $user['is_typing'] = (int)$res['is_typing'];
        $user['last_typing_id'] = (int)$res['last_typing_id'];
    }
}

$inicialStatusText = "offline";
$inicialStatusClass = "offline";

if (!empty($user['last_activity'])) {
    $lastSeenTime = strtotime($user['last_activity']);
    $currentTimeFuso = strtotime(date('Y-m-d H:i:s')); // Sincronização estável de fuso
    $diff = $currentTimeFuso - $lastSeenTime;

    if ($user['is_typing'] === 1 && $user['last_typing_id'] === $userId) {
        $inicialStatusText = "digitando...";
        $inicialStatusClass = "typing";
    } elseif ($diff <= 120) { 
        $inicialStatusText = "online";
        $inicialStatusClass = "online";
    } else {
        $hojeInicial = strtotime(date('Y-m-d 00:00:00')); 
        $ontemInicial = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))); 
        $horaFormatada = date('H:i', $lastSeenTime);

        if ($lastSeenTime >= $hojeInicial) {
            $inicialStatusText = "visto por último hoje às " . $horaFormatada;
        } elseif ($lastSeenTime >= $ontemInicial) {
            $inicialStatusText = "visto por último ontem às " . $horaFormatada;
        } else {
            $inicialStatusText = "visto por último em " . date('d/m/Y', $lastSeenTime) . " às " . $horaFormatada;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Chat em Tempo Real</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0e1621; color: #fff; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.header { background: #17212b; padding: 10px 15px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #101921; }
.header .back-btn { color: #7f91a4; font-size: 18px; text-decoration: none; }
.header img { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
.header .user-info { display: flex; flex-direction: column; }
.header .user-info .profile-link { text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: 5px; }
.header .user-info .profile-link:hover strong { text-decoration: underline; color: #40a7e3; }
.header .user-info strong { font-size: 16px; color: #f5f5f5; display: inline-flex; align-items: center; gap: 5px; transition: color 0.2s; }
.header .verified-badge { color: #3897f0; font-size: 14px; display: inline-flex; align-items: center; }
.header .user-info span { font-size: 13px; color: #7f91a4; transition: color 0.3s ease; }
.header .user-info span.online, .header .user-info span.typing { color: #40a7e3 !important; font-weight: 500; }
.chat { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 8px; background-image: radial-gradient(rgba(23, 33, 43, 0.5) 15%, transparent 16%); background-size: 16px 16px; }

/* ===== NOVO: SEPARADOR DE DATA (HOJE / ONTEM / DATA) ===== */
.date-separator { display: flex; justify-content: center; align-items: center; margin: 12px 0; align-self: center; }
.date-separator span { background: #182533; color: #b0c4d6; font-size: 12px; font-weight: 500; padding: 5px 14px; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.3); }

.msg { padding: 8px 12px; max-width: 60%; word-break: break-word; position: relative; font-size: 15px; border-radius: 12px; display: flex; flex-direction: column; transition: transform 0.15s cubic-bezier(0.1, 0.88, 0.3, 1), background 0.3s ease; touch-action: pan-y; }
.msg .reply-action-btn { position: absolute; top: 50%; transform: translateY(-50%); background: #1f2c3a; border: none; color: #7f91a4; width: 26px; height: 26px; border-radius: 50%; cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 12px; transition: background 0.2s; z-index: 10; }
.msg .reply-action-btn:hover { background: #2b394a; color: #fff; }
.msg.me .reply-action-btn { left: -35px; }
.msg.you .reply-action-btn { right: -35px; }
.msg:hover .reply-action-btn { display: flex; }
.quoted-box { background: rgba(0, 0, 0, 0.18); border-left: 3px solid #40a7e3; padding: 5px 8px; margin-bottom: 6px; border-radius: 4px; font-size: 13px; color: #b0c4d6; max-height: 60px; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }

.me { background: #2b5278; align-self: flex-end; border-radius: 12px 12px 0px 12px; }
.you { background: #182533; align-self: flex-start; border-radius: 12px 12px 12px 0px; }
.msg-meta { align-self: flex-end; font-size: 11px; color: #7f91a4; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
.me .msg-meta { color: #a2bccc; }
.status-ticks.read .fa-check-double { color: #40a7e3 !important; }
img.media { max-width: 100%; max-height: 280px; border-radius: 8px; margin-bottom: 4px; display: block; }
.custom-audio-player audio { display: none !important; }
.custom-audio-player { display: flex; align-items: center; gap: 12px; width: 240px; padding: 6px 4px; }
.audio-play-btn { background: none; border: none; color: #40a7e3; font-size: 20px; cursor: pointer; }
.me .audio-play-btn { color: #fff; }
.audio-timeline { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.audio-slider-container { width: 100%; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; cursor: pointer; position: relative; }
.audio-progress-bar { height: 100%; width: 0%; background: #40a7e3; border-radius: 2px; }
.me .audio-progress-bar { background: #fff; }
.audio-time-info { font-size: 11px; color: #7f91a4; display: flex; justify-content: space-between; }
.footer-container { background: #17212b; border-top: 1px solid #101921; display: flex; flex-direction: column; }

.reply-bar-preview { display: none; background: #141e27; border-left: 4px solid #40a7e3; padding: 8px 20px; justify-content: space-between; align-items: center; font-size: 13px; color: #abbccf; }
.reply-bar-preview .reply-bar-content { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 85%; }
.reply-bar-preview .reply-bar-close { color: #f15c6d; cursor: pointer; font-size: 16px; padding: 2px 5px; }

form { display: flex; align-items: center; gap: 10px; padding: 12px 20px; }
input[type=text] { flex: 1; padding: 12px; border: none; outline: none; border-radius: 8px; background: #24303f; color: #fff; }
.btn-send, .btn-audio { background: none; border: none; color: #5288c1; font-size: 22px; cursor: pointer; }
.btn-audio.recording { color: #f15c6d !important; }
.view-once-bubble-btn { display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; color: #40a7e3; cursor: pointer; }
.view-once-bubble-btn.opened { color: #7f91a4 !important; cursor: default; }
.preview-container { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0b141a; z-index: 999; flex-direction: column; justify-content: space-between; padding: 20px; box-sizing: border-box; }
.preview-body { flex: 1; display: flex; align-items: center; justify-content: center; }
.preview-body img { max-width: 90%; max-height: 80%; object-fit: contain; }
.preview-footer-ui { display: flex; flex-direction: column; gap: 10px; width: 100%; }
.view-once-toggle { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #8696a0; color: #8696a0; display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; }
.view-once-toggle.active { background: #00a884; border-color: #00a884; color: #fff; }
.view-once-lightbox { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 1000; justify-content: center; align-items: center; }
.view-once-lightbox img { max-width: 100%; max-height: 90%; }
.close-lightbox { position: absolute; top: 20px; right: 20px; font-size: 30px; color: #fff; cursor: pointer; }
.file-input-wrapper { position: relative; color: #7f91a4; font-size: 22px; cursor: pointer; }
.zap-attach-menu { display: none; position: absolute; bottom: 50px; left: 0; background: #23313d; padding: 10px; border-radius: 8px; flex-direction: column; gap: 5px; }
.zap-attach-item { padding: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; }

.map-box { width: 260px; height: 180px; border-radius: 8px; overflow: hidden; margin-bottom: 4px; border: 1px solid rgba(255,255,255,0.1); position: relative; background: #111b21; }
.map-box iframe { width: 100%; height: 100%; border: none; display: block; }
.map-title { font-size: 13px; font-weight: bold; padding: 4px 0; color: #40a7e3; display: flex; align-items: center; gap: 5px; }
.btn-stop-location { background: #f15c6d; color: white; border: none; border-radius: 6px; padding: 6px 10px; font-size: 11px; font-weight: bold; cursor: pointer; width: 100%; text-align: center; margin-top: 5px; display: block; box-sizing: border-box; }
.btn-stop-location:hover { background: #d44b5b; }
.location-terminated { color: #ee6b78; font-style: italic; font-size: 13px; display: flex; align-items: center; gap: 6px; padding: 4px 0; }
</style>
</head>
<body>

<div class="header">
    <a href="contatos.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
    <div class="user-info">
        <a href="perfil.php?id=<?= $chatId ?>" class="profile-link">
            <strong>
                <?= htmlspecialchars($user['username']) ?>
                <?php if ($user['is_verified'] === 1): ?>
                    <span class="verified-badge" title="Verificado" style="color: #3897f0;"><i class="fa-solid fa-circle-check"></i></span>
                <?php endif; ?>
            </strong>
        </a>
        <span id="statusText" class="<?= $inicialStatusClass ?>"><?= $inicialStatusText ?></span>
    </div>
</div>

<div class="chat" id="chatBox"></div>

<div class="view-once-lightbox" id="viewOnceLightbox">
    <i class="fa-solid fa-xmark close-lightbox" onclick="fecharLightbox()"></i>
    <img id="viewOnceLightboxImg" src="">
</div>

<?php if ($chatId > 0): ?>
<div class="footer-container">
    
    <div class="reply-bar-preview" id="replyBarPreview">
        <div class="reply-bar-content" id="replyBarText">Respondendo...</div>
        <div class="reply-bar-close" onclick="fecharModoResposta()"><i class="fa-solid fa-xmark"></i></div>
    </div>

    <div class="preview-container" id="imagePreviewBox">
        <div class="preview-header"><i class="fa-solid fa-xmark" id="cancelPreviewBtn" style="font-size:24px; cursor:pointer;"></i></div>
        <div class="preview-body"><img id="imagePreviewTarget" src=""></div>
        <div class="preview-footer-ui">
            <div style="display:flex; gap:10px; background:#2a3942; padding:8px; border-radius:8px;">
                <input type="text" id="previewCaptionInput" placeholder="Legenda..." style="flex:1; background:transparent; border:none; color:#fff; outline:none;">
                <div class="view-once-toggle" id="viewOnceToggleBtn">1</div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span id="previewFilename" style="font-size:12px; color:#8696a0;"></span>
                <button id="confirmSendBtn" style="background:#00a884; border:none; width:40px; height:40px; border-radius:50%; color:#fff; cursor:pointer;"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <form id="chatForm" onsubmit="return false;">
        <input type="hidden" name="reply_to_id" id="replyToInputId" value="0">

        <div class="file-input-wrapper" id="zapClipBtn">
            <i class="fa-solid fa-paperclip"></i>
            <div class="zap-attach-menu" id="zapAttachMenu">
                <div class="zap-attach-item" onclick="document.getElementById('mediaFile').click();"><i class="fa-solid fa-image"></i> Mídia</div>
                <input type="file" name="media" id="mediaFile" style="display:none;" accept="image/*,audio/*,application/pdf">
                <div class="zap-attach-item" id="zapAttachLocation"><i class="fa-solid fa-location-dot"></i> Localização</div>
            </div>
        </div>
        <input type="text" name="message" id="messageInput" placeholder="Escreva uma mensagem..." autocomplete="off">
        <button class="btn-send" id="btnSendText" type="button" style="display:none;"><i class="fa-solid fa-paper-plane"></i></button>
        <button class="btn-audio" id="btnAudioMic" type="button"><i class="fa-solid fa-microphone"></i></button>
    </form>
</div>
<?php endif; ?>

<script>
const PALAVRA_CHAVE_E2E = "segredo-e2e-compartilhado";

function criptografarTexto(textoOuPlanilhamento) {
    if (!textoOuPlanilhamento) return "";
    const mockIv = "7EyFU5ltO/1bkibl";
    const mockTag = "s62c5/H34pEwHlw5orE+Ow==";
    const estruturado = {
        key: btoa(textoOuPlanilhamento + "||" + PALAVRA_CHAVE_E2E), 
        iv: mockIv,
        tag: mockTag,
        data: "Cj4="
    };
    return btoa(JSON.stringify(estruturado));
}

function descriptografarTexto(tokenBrutoDoBanco) {
    if (!tokenBrutoDoBanco) return "";
    try {
        if (!tokenBrutoDoBanco.startsWith("ey") && !tokenBrutoDoBanco.includes("key")) {
            return tokenBrutoDoBanco; 
        }
        const stringJson = atob(tokenBrutoDoBanco);
        const objeto = JSON.parse(stringJson);
        if (objeto && objeto.key) {
            const decodificarChave = atob(objeto.key);
            const partes = decodificarChave.split("||");
            return partes[0]; 
        }
    } catch (e) {
        return tokenBrutoDoBanco;
    }
    return tokenBrutoDoBanco;
}
</script>

<script>
    const chatBox = document.getElementById('chatBox');
    const statusTextEl = document.getElementById('statusText');
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const mediaFile = document.getElementById('mediaFile');
    const chatId = parseInt("<?= $chatId ?>", 10);
    const btnSendText = document.getElementById('btnSendText');
    const btnAudioMic = document.getElementById('btnAudioMic');
    const imagePreviewBox = document.getElementById('imagePreviewBox');
    const imagePreviewTarget = document.getElementById('imagePreviewTarget');
    const previewFilename = document.getElementById('previewFilename');
    const cancelPreviewBtn = document.getElementById('cancelPreviewBtn');
    const previewCaptionInput = document.getElementById('previewCaptionInput');
    const viewOnceToggleBtn = document.getElementById('viewOnceToggleBtn');
    const confirmSendBtn = document.getElementById('confirmSendBtn');
    const zapClipBtn = document.getElementById('zapClipBtn');
    const zapAttachMenu = document.getElementById('zapAttachMenu');
    const zapAttachLocation = document.getElementById('zapAttachLocation');

    const replyBarPreview = document.getElementById('replyBarPreview');
    const replyBarText = document.getElementById('replyBarText');
    const replyToInputId = document.getElementById('replyToInputId');

    let viewOnceActive = false;
    let mediaRecorder = null;
    let audioChunks = [];
    let isRecording = false;
    let isCheckingMessages = false;
    let isCheckingStatus = false;
    let typingTimeout = null;
    let amITyping = false;
    let watchLocationId = null;
    let textoRespondidoTemporario = ""; 

    // ===== NOVO (CORREÇÃO DE DUPLICAÇÃO): fila que guarda, em ordem, o ID de cada balão
    // otimista enviado. Quando a mensagem real confirmada chega do servidor, removemos
    // sempre o PRIMEIRO da fila (FIFO), em vez de tentar comparar texto/reply_id, que é
    // uma comparação frágil e que falhava justamente ao responder mensagens, causando a duplicação. =====
    let filaMensagensOtimistas = [];

    // ===== NOVO: controla qual foi o último rótulo de data (Hoje/Ontem/dd-mm-yyyy) já inserido no chat =====
    let ultimaDataSeparadorRenderizada = null;

    if(zapClipBtn) {
        zapClipBtn.addEventListener('click', (e) => { e.stopPropagation(); zapAttachMenu.style.display = zapAttachMenu.style.display === 'flex' ? 'none' : 'flex'; });
        document.addEventListener('click', () => zapAttachMenu.style.display = 'none');
    }

    function inicializarPlayerAudio(a) { 
        const p = a.closest('.custom-audio-player'); 
        if(p && a.duration && a.duration !== Infinity) { 
            p.querySelector('.total').textContent = formatTime(a.duration); 
        } 
    }
    
    function atualizarPlayerAudio(a) { 
        const p = a.closest('.custom-audio-player'); 
        if(p && a.currentTime) { 
            const duracaoTotal = (a.duration && a.duration !== Infinity) ? a.duration : a.currentTime;
            p.querySelector('.audio-progress-bar').style.width = ((a.currentTime / duracaoTotal) * 100) + '%'; 
            p.querySelector('.corrente').textContent = formatTime(a.currentTime); 
            if(a.duration && a.duration !== Infinity) {
                p.querySelector('.total').textContent = formatTime(a.duration);
            }
        } 
    }
    
    function resetarPlayerAudio(a) { 
        const p = a.closest('.custom-audio-player'); 
        if(p){ 
            p.querySelector('.audio-play-btn i').className = 'fa-solid fa-play'; 
            p.querySelector('.audio-progress-bar').style.width = '0%'; 
            p.querySelector('.corrente').textContent = '0:00'; 
        } 
    }
    
    function formatTime(s) { 
        if(isNaN(s) || s === Infinity) return "0:00"; 
        const m = Math.floor(s/60); 
        const sec = Math.floor(s%60).toString().padStart(2,'0'); 
        return m+":"+sec; 
    }
    
    function alternarAudio(btn) { 
        const aud = btn.closest('.custom-audio-player').querySelector('audio'); 
        if(aud.paused){ 
            aud.play(); 
            btn.querySelector('i').className = 'fa-solid fa-pause'; 
        } else { 
            aud.pause(); 
            btn.querySelector('i').className = 'fa-solid fa-play'; 
        } 
    }
    
    function navegarNoAudio(e, container) { 
        const aud = container.closest('.custom-audio-player').querySelector('audio'); 
        if(aud.duration && aud.duration !== Infinity) { 
            const rect = container.getBoundingClientRect(); 
            aud.currentTime = ((e.clientX - rect.left) / rect.width) * aud.duration; 
        } 
    }

    function setTypingStatus(isTyping) {
        if (amITyping !== isTyping) {
            amITyping = isTyping;
            fetch(`${window.location.pathname}?action=typing&id=${chatId}&status=${isTyping ? 1 : 0}`).catch(()=>{});
        }
    }

    function abrirModoResposta(msgId, textoOriginal) {
        if(replyToInputId) replyToInputId.value = msgId;
        textoRespondidoTemporario = descriptografarTexto(textoOriginal); 
        if(replyBarText) replyBarText.innerHTML = `<i class="fa-solid fa-reply"></i> Respondendo: <b>${escapeHtml(textoRespondidoTemporario)}</b>`;
        if(replyBarPreview) replyBarPreview.style.display = 'flex';
        if(messageInput) messageInput.focus();
    }

    function fecharModoResposta() {
        if(replyToInputId) replyToInputId.value = "0";
        textoRespondidoTemporario = "";
        if(replyBarPreview) replyBarPreview.style.display = 'none';
    }

    function focarMensagem(msgId) {
        const elemento = chatBox.querySelector(`[data-msg-id="${msgId}"]`);
        if (elemento) {
            elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });
            elemento.style.transition = 'background 0.5s ease';
            const fundoOriginal = elemento.style.background;
            elemento.style.background = '#3b6a96';
            setTimeout(() => { elemento.style.background = fundoOriginal; }, 1000);
        }
    }

    // ===== NOVO: retorna "Hoje", "Ontem" ou a data formatada (dd/mm/yyyy) a partir de um datetime do banco =====
    function obterRotuloDeData(dataBruta) {
        if (!dataBruta) return null;

        // Aceita "YYYY-MM-DD HH:MM:SS" (formato típico do MySQL/PHP) convertendo para ISO
        const dataNormalizada = dataBruta.includes('T') ? dataBruta : dataBruta.replace(' ', 'T');
        const dataMsg = new Date(dataNormalizada);

        if (isNaN(dataMsg.getTime())) return null;

        const hoje = new Date();
        const ontem = new Date();
        ontem.setDate(hoje.getDate() - 1);

        if (dataMsg.toDateString() === hoje.toDateString()) {
            return "Hoje";
        }
        if (dataMsg.toDateString() === ontem.toDateString()) {
            return "Ontem";
        }
        return dataMsg.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    // ===== NOVO: insere (se necessário) o separador de data antes de renderizar uma mensagem =====
    function inserirSeparadorDeDataSeNecessario(dataBrutaDaMensagem) {
        const rotulo = obterRotuloDeData(dataBrutaDaMensagem);
        if (!rotulo) return;

        if (ultimaDataSeparadorRenderizada !== rotulo) {
            chatBox.insertAdjacentHTML('beforeend', `<div class="date-separator" data-rotulo="${rotulo}"><span>${rotulo}</span></div>`);
            ultimaDataSeparadorRenderizada = rotulo;
        }
    }

    function configurarEventosMobileNoBalao(elemento, msgId, textoTratado) {
        let touchStartX = 0;
        let touchStartY = 0;
        let lastTap = 0;

        elemento.addEventListener('touchstart', function(e) {
            const currentTime = new Date().getTime();
            const tapLength = currentTime - lastTap;
            if (tapLength < 300 && tapLength > 0) {
                e.preventDefault();
                abrirModoResposta(msgId, textoTratado);
            }
            lastTap = currentTime;
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: false });

        elemento.addEventListener('touchmove', function(e) {
            let diffX = e.touches[0].clientX - touchStartX;
            let diffY = e.touches[0].clientY - touchStartY;
            
            if (Math.abs(diffX) > Math.abs(diffY) && diffX < 0) {
                let deslocamento = Math.max(diffX, -70); 
                elemento.style.transform = `translateX(${deslocamento}px)`;
                if (deslocamento <= -60) {
                    elemento.style.background = "rgba(64, 167, 227, 0.25)";
                }
            }
        }, { passive: true });

        elemento.addEventListener('touchend', function(e) {
            let diffX = e.changedTouches[0].clientX - touchStartX;
            elemento.style.transform = '';
            elemento.style.background = '';
            if (diffX < -60) {
                abrirModoResposta(msgId, textoTratado);
            }
        });

        elemento.addEventListener('dblclick', function() {
            abrirModoResposta(msgId, textoTratado);
        });
    }

    async function enviarFormularioDireto() {
        if (chatId <= 0) return;
        setTypingStatus(false);

        const textoPuro = messageInput.value.trim();
        if(!textoPuro && mediaFile.files.length === 0) return;

        const pacoteProtegido = criptografarTexto(textoPuro);

        const formData = new FormData(chatForm);
        formData.set('message', pacoteProtegido);
        formData.set('view_once', (viewOnceActive && mediaFile.files.length > 0) ? '1' : '0');
        formData.set('is_audio', '0');

        let localUrl = (mediaFile.files.length > 0 && mediaFile.files[0].type.startsWith('image/')) ? imagePreviewTarget.src : null;
        
        const idTemporario = "temp_" + Date.now();

        // ===== NOVO: garante separador de "Hoje" para a mensagem otimista que está sendo enviada agora =====
        inserirSeparadorDeDataSeNecessario(new Date().toISOString());

        renderizarMensagemOtimista(textoPuro, localUrl, false, (viewOnceActive && mediaFile.files.length > 0), replyToInputId.value, idTemporario);
        
        messageInput.value = '';
        if(btnSendText) btnSendText.style.display = 'none';
        if(btnAudioMic) btnAudioMic.style.display = 'block';
        ocultarPreview();
        fecharModoResposta();

        try {
            await fetch(window.location.href, { method: 'POST', body: formData });
            loadMessages();
        } catch(err) { console.error(err); }
    }

    if(messageInput) {
        messageInput.addEventListener('input', function() { 
            const active = this.value.trim()!==''; 
            btnSendText.style.display = active?'block':'none'; 
            btnAudioMic.style.display = active?'none':'block'; 
            
            if(active) {
                setTypingStatus(true);
                clearTimeout(typingTimeout);
                typingTimeout = setTimeout(() => { setTypingStatus(false); }, 3000);
            } else {
                setTypingStatus(false);
            }
        });
        messageInput.addEventListener('keydown', (e) => { if(e.key==='Enter') { e.preventDefault(); enviarFormularioDireto(); } });
    }
    if(btnSendText) btnSendText.addEventListener('click', enviarFormularioDireto);
    if(confirmSendBtn) confirmSendBtn.addEventListener('click', () => { messageInput.value = previewCaptionInput.value; enviarFormularioDireto(); });

    if(zapAttachLocation) {
        zapAttachLocation.addEventListener('click', () => {
            if(!navigator.geolocation) {
                alert("Geolocalização não é suportada pelo seu navegador.");
                return;
            }
            
            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                messageInput.value = `GEO:${lat},${lng}`;
                enviarFormularioDireto();
                
                if(!watchLocationId) {
                    watchLocationId = navigator.geolocation.watchPosition(newPos => {
                    }, null, { enableHighAccuracy: true, timeout: 5000 });
                }
            }, error => {
                alert("Erro ao obter localização. Verifique as permissões do seu GPS.");
            }, { enableHighAccuracy: true });
        });
    }

    function pararCompartilhamentoLocalizacao(btnElement, msgId) {
        if(watchLocationId) {
            navigator.geolocation.clearWatch(watchLocationId);
            watchLocationId = null;
        }

        if (msgId) {
            fetch(`${window.location.pathname}?action=stop_location&msg_id=${msgId}`).then(() => {
                const msgContainer = btnElement.closest('.msg');
                if(msgContainer) {
                    msgContainer.innerHTML = `<div class="location-terminated"><i class="fa-solid fa-location-dot"></i> Localização encerrada</div><div class="msg-meta">${new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'})} <span class="status-ticks read"><i class="fa-solid fa-check-double"></i></span></div>`;
                }
            });
        } else {
            const msgContainer = btnElement.closest('.msg');
            if(msgContainer) {
                btnElement.remove();
                const mapTitle = msgContainer.querySelector('.map-title');
                if(mapTitle) mapTitle.innerHTML = `<i class="fa-solid fa-location-dot"></i> Localização encerrada`;
                const mapBox = msgContainer.querySelector('.map-box');
                if(mapBox) mapBox.remove();
            }
        }
    }

    function renderizarMensagemOtimista(texto, mediaUrl, isAudio, isViewOnce, replyId = 0, stampId = "") {
        const h = new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        let html = '';

        if(parseInt(replyId) > 0 && textoRespondidoTemporario !== "") {
            html += `<div class="quoted-box"><b>Resposta:</b> ${escapeHtml(textoRespondidoTemporario)}</div>`;
        }

        if(isViewOnce) {
            html = `<div class="view-once-bubble-btn"><i class="fa-solid fa-number-one"></i> Mídia única</div>`;
        } else {
            if(mediaUrl) html += isAudio ? `<div class="custom-audio-player"><audio ontimeupdate="atualizarPlayerAudio(this)" onloadedmetadata="inicializarPlayerAudio(this)" onended="resetarPlayerAudio(this)" preload="auto" controlslist="nodownload"><source src="${mediaUrl}" type="audio/ogg"></audio><button class="audio-play-btn" onclick="alternarAudio(this)"><i class="fa-solid fa-play"></i></button><div class="audio-timeline"><div class="audio-slider-container" onclick="navegarNoAudio(event, this)"><div class="audio-progress-bar"></div></div><div class="audio-time-info"><span class="corrente">0:00</span><span class="total">0:00</span></div></div></div>` : `<img class="media" src="${mediaUrl}">`;
            
            if(texto) {
                if(texto.startsWith('GEO:')) {
                    const coords = texto.replace('GEO:', '').split(',');
                    const lat = coords[0].trim();
                    const lng = coords[1].trim();
                    html += `
                    <div class="map-title"><i class="fa-solid fa-location-dot"></i> Localização em tempo real</div>
                    <div class="map-box">
                        <iframe src="http://googleusercontent.com/maps.google.com/?q=${lat},${lng}&z=16&output=embed"></iframe>
                    </div>
                    <button class="btn-stop-location" onclick="pararCompartilhamentoLocalizacao(this, null)">Parar de compartilhar</button>`;
                } else {
                    html += `<div>${escapeHtml(texto)}</div>`;
                }
            }
        }
        
        chatBox.insertAdjacentHTML('beforeend', `<div class="msg me" data-otimista="${stampId}" data-reply-id="${replyId}" data-texto-otimista="${escapeHtml(texto)}">${html}<div class="msg-meta">${h} <span class="status-ticks"><i class="fa-solid fa-check"></i></span></div></div>`);
        chatBox.scrollTop = chatBox.scrollHeight;

        // ===== NOVO (CORREÇÃO DE DUPLICAÇÃO): registra este balão otimista na fila, em ordem de envio =====
        if (stampId) {
            filaMensagensOtimistas.push(stampId);
        }
    }

    async function loadMessages() {
        if (isCheckingMessages || chatId <= 0) return;
        isCheckingMessages = true;
        try {
            const response = await fetch(`atualizar_mesagens.php?id=${chatId}&_=${Date.now()}`);
            const data = await response.json();
            if(data.status === 'success') {
                const currentUserId = parseInt(data.user_id, 10);
                
                data.messages.forEach(m => {
                    let msgExistente = chatBox.querySelector(`[data-msg-id="${m.id}"]`);
                    
                    let textoTratadoE2E = descriptografarTexto(m.message);

                    if (!msgExistente) {

                        // ===== NOVO: insere separador de data (Hoje/Ontem/dd-mm-yyyy) antes da mensagem, se necessário =====
                        // Usa m.created_at se a sua atualizar_mesagens.php enviar essa coluna no JSON.
                        // Caso o campo enviado tenha outro nome, ajuste aqui (ex: m.data_criacao).
                        const dataBrutaMsg = m.created_at || m.data_criacao || m.created || null;
                        inserirSeparadorDeDataSeNecessario(dataBrutaMsg);

                        const deQuem = (m.sender_id === currentUserId) ? 'me' : 'you';
                        const tickClass = (m.is_read === 1) ? 'read' : '';
                        let conteudo = '';

                        if (m.reply_to_id && parseInt(m.reply_to_id) > 0) {
                            let textoCitado = m.reply_to_text ? descriptografarTexto(m.reply_to_text) : "Mídia/Localização";
                            conteudo += `<div class="quoted-box" onclick="focarMensagem(${m.reply_to_id})"><b>Resposta:</b> ${escapeHtml(textoCitado)}</div>`;
                        }

                        if (m.is_view_once === 1) {
                            if (!m.media_url) {
                                conteudo = `<div class="view-once-bubble-btn opened"><i class="fa-solid fa-number-one"></i> Mídia aberta</div>`;
                            } else {
                                conteudo = `<div class="view-once-bubble-btn" onclick="abrirMidiaUnica(this, '${m.media_url}', ${m.id}, ${m.sender_id === currentUserId})"><i class="fa-solid fa-number-one"></i> Ver Mídia Única</div>`;
                            }
                        } else {
                            if (m.media_url) {
                                if (m.is_audio === 1) {
                                    conteudo += `
                                    <div class="custom-audio-player">
                                        <audio ontimeupdate="atualizarPlayerAudio(this)" onloadedmetadata="inicializarPlayerAudio(this)" onended="resetarPlayerAudio(this)" preload="auto" controlslist="nodownload"><source src="${m.media_url}" type="${m.media_type}"></audio>
                                        <button class="audio-play-btn" onclick="alternarAudio(this)"><i class="fa-solid fa-play"></i></button>
                                        <div class="audio-timeline">
                                            <div class="audio-slider-container" onclick="navegarNoAudio(event, this)"><div class="audio-progress-bar"></div></div>
                                            <div class="audio-time-info"><span class="corrente">0:00</span><span class="total">0:00</span></div>
                                        </div>
                                    </div>`;
                                } else {
                                    conteudo += `<img class="media" src="${m.media_url}" onclick="visualizarImagemNativa('${m.media_url}')">`;
                                }
                            }
                            
                            if (textoTratadoE2E) {
                                if (textoTratadoE2E === 'LOCALIZACAO_ENCERRADA') {
                                    conteudo += `<div class="location-terminated"><i class="fa-solid fa-location-dot"></i> Localização encerrada</div>`;
                                } else if (textoTratadoE2E.startsWith('GEO:') || textoTratadoE2E.includes('maps.google.com')) {
                                    let lat = "-23.5505", lng = "-46.6333";
                                    if(textoTratadoE2E.startsWith('GEO:')) {
                                        const c = textoTratadoE2E.replace('GEO:', '').split(',');
                                        lat = c[0].trim(); lng = c[1].trim();
                                    } else {
                                        const regex = /[-+]?([1-9]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)/g;
                                        const extraidas = textoTratadoE2E.match(regex);
                                        if(extraidas) { const p = extraidas[0].split(','); lat = p[0]; lng = p[1]; }
                                    }
                                    
                                    let botaoParar = (m.sender_id === currentUserId) ? `<button class="btn-stop-location" onclick="pararCompartilhamentoLocalizacao(this, ${m.id})">Parar de compartilhar</button>` : '';
                                    
                                    conteudo += `
                                    <div class="map-title"><i class="fa-solid fa-location-dot"></i> Localização em tempo real</div>
                                    <div class="map-box">
                                        <iframe src="http://googleusercontent.com/maps.google.com/?q=${lat},${lng}&z=16&output=embed"></iframe>
                                    </div>
                                    ${botaoParar}`;
                                } else { 
                                    conteudo += `<div>${escapeHtml(textoTratadoE2E)}</div>`; 
                                }
                            }
                        }

                        let ticks = (m.sender_id === currentUserId) ? `<span class="status-ticks ${tickClass}"><i class="fa-solid fa-check-double"></i></span>` : '';
                        
                        let textoTratadoParaInput = m.message ? m.message.replace(/'/g, "\\'") : "Mídia/Áudio";
                        let botaoResponder = `<button class="reply-action-btn" onclick="abrirModoResposta(${m.id}, '${textoTratadoParaInput}')" title="Responder"><i class="fa-solid fa-reply"></i></button>`;

                        chatBox.insertAdjacentHTML('beforeend', `<div class="msg ${deQuem}" data-msg-id="${m.id}">${botaoResponder}${conteudo}<div class="msg-meta">${m.hora} ${ticks}</div></div>`);
                        
                        // ===== CORREÇÃO DE DUPLICAÇÃO =====
                        // Antes: a remoção do balão otimista dependia de comparar texto + reply_id,
                        // o que falhava com frequência ao responder mensagens (escaping, acentos, etc.)
                        // e deixava o balão temporário "preso" na tela, duplicado junto do balão real.
                        // Agora: removemos sempre o PRIMEIRO item da fila de envios (FIFO), que é
                        // garantidamente o balão otimista correspondente a esta mensagem confirmada,
                        // já que mensagens do mesmo usuário chegam na mesma ordem em que foram enviadas.
                        if (m.sender_id === currentUserId) {
                            if (filaMensagensOtimistas.length > 0) {
                                const idOtimistaParaRemover = filaMensagensOtimistas.shift();
                                const elementoOtimista = chatBox.querySelector(`[data-otimista="${idOtimistaParaRemover}"]`);
                                if (elementoOtimista) {
                                    elementoOtimista.remove();
                                }
                            } else {
                                // Segurança extra (fallback): caso a fila esteja vazia (ex: mensagem enviada
                                // por outra aba/dispositivo), tenta a comparação antiga por texto/reply_id.
                                let provisorios = chatBox.querySelectorAll('[data-otimista^="temp_"]');
                                provisorios.forEach(p => {
                                    let txtOtimista = p.getAttribute('data-texto-otimista');
                                    let rId = p.getAttribute('data-reply-id');
                                    if (txtOtimista === textoTratadoE2E && rId == (m.reply_to_id || "0")) {
                                        p.remove();
                                    }
                                });
                            }
                        }

                        let novoElemento = chatBox.querySelector(`[data-msg-id="${m.id}"]`);
                        configurarEventosMobileNoBalao(novoElemento, m.id, m.message);

                        chatBox.scrollTop = chatBox.scrollHeight;
                    } else {
                        if (textoTratadoE2E === 'LOCALIZACAO_ENCERRADA' && !msgExistente.querySelector('.location-terminated')) {
                            msgExistente.innerHTML = `<div class="location-terminated"><i class="fa-solid fa-location-dot"></i> Localização encerrada</div><div class="msg-meta">${m.hora} ${(m.sender_id === currentUserId) ? '<span class="status-ticks read"><i class="fa-solid fa-check-double"></i></span>' : ''}</div>`;
                        }

                        let ticksEl = msgExistente.querySelector('.status-ticks');
                        if (ticksEl) {
                            if (m.is_read === 1 && !ticksEl.classList.contains('read')) {
                                ticksEl.classList.add('read');
                            }
                        }
                    }
                });
            }
        } catch (e) { console.error(e); } finally { isCheckingMessages = false; }
    }

    if(mediaFile) {
        mediaFile.addEventListener('change', function() {
            const file = this.files[0];
            if(file && file.type.startsWith('image/')) {
                previewFilename.textContent = file.name;
                previewCaptionInput.value = messageInput.value;
                const rd = new FileReader();
                rd.onload = (e) => { imagePreviewTarget.src = e.target.result; imagePreviewBox.style.display = 'flex'; };
                rd.readAsDataURL(file);
            } else if(file) { enviarFormularioDireto(); }
        });
    }
    if(cancelPreviewBtn) cancelPreviewBtn.addEventListener('click', ocultarPreview);
    if(viewOnceToggleBtn) viewOnceToggleBtn.addEventListener('click', function() { viewOnceActive = !viewOnceActive; this.classList.toggle('active', viewOnceActive); });
    function ocultarPreview() { if(mediaFile) mediaFile.value=''; imagePreviewBox.style.display='none'; viewOnceActive=false; viewOnceToggleBtn.classList.remove('active'); }

    if(btnAudioMic) {
        btnAudioMic.addEventListener('click', async () => {
            if(!isRecording) {
                if(!navigator.mediaDevices?.getUserMedia) return;
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = () => {
                    const blob = new Blob(audioChunks, { type: 'audio/ogg; codecs=opus' });
                    const idAudioTemp = "temp_" + Date.now();

                    // ===== NOVO: garante separador de "Hoje" para o áudio otimista enviado agora =====
                    inserirSeparadorDeDataSeNecessario(new Date().toISOString());

                    renderizarMensagemOtimista('', URL.createObjectURL(blob), true, false, replyToInputId.value, idAudioTemp);
                    const fd = new FormData(); fd.append('media', blob, 'audio.ogg'); fd.append('message',''); fd.append('view_once','0'); fd.append('is_audio','1');
                    if(parseInt(replyToInputId.value) > 0) fd.append('reply_to_id', replyToInputId.value);
                    
                    fetch(window.location.href, { method: 'POST', body: fd }).then(() => {
                        loadMessages();
                        fecharModoResposta();
                    });
                    stream.getTracks().forEach(t => t.stop());
                };
                mediaRecorder.start();
                isRecording = true; btnAudioMic.classList.add('recording'); btnAudioMic.innerHTML = '<i class="fa-solid fa-stop"></i>';
            } else {
                mediaRecorder.stop(); isRecording = false; btnAudioMic.classList.remove('recording'); btnAudioMic.innerHTML = '<i class="fa-solid fa-microphone"></i>';
            }
        });
    }

    async function loadStatus() {
        if (isCheckingStatus || chatId <= 0) return;
        isCheckingStatus = true;
        try {
            fetch(`${window.location.pathname}?action=ping`).catch(()=>{});

            const response = await fetch(`${window.location.pathname}?action=get_status&id=${chatId}&_=${Date.now()}`);
            const data = await response.json();
            
            if (data && data.statusText) {
                statusTextEl.classList.remove('online', 'typing');
                statusTextEl.textContent = data.statusText;
                
                if (data.statusText === 'digitando...') {
                    statusTextEl.classList.add('typing');
                } else if (data.statusText === 'online') {
                    statusTextEl.classList.add('online');
                }
            }
        } catch(e) {
            console.error("Erro ao puxar status dinâmico:", e);
        } finally { 
            isCheckingStatus = false; 
        }
    }

    function visualizarImagemNativa(url) { const l = document.getElementById('viewOnceLightbox'); document.getElementById('viewOnceLightboxImg').src=url; l.style.display='flex'; }
    function abrirMidiaUnica(el, url, id, souDono) { visualizarImagemNativa(url); if(!souDono){ fetch("contatos.php?action=burn&msg_id=" + id + "&id=" + chatId).then(()=>loadMessages()); } }
    function fecharLightbox() { document.getElementById('viewOnceLightbox').style.display='none'; }
    function escapeHtml(t) { return t.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); }

    setInterval(loadMessages, 1000); 
    setInterval(loadStatus, 2000);   
    
    loadMessages();
    loadStatus();
</script>
</body>
</html>