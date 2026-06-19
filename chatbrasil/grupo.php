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
$agoraLocal = date('Y-m-d H:i:s'); 

// Garante que as colunas e tabelas necessárias existam no ambiente
try { $pdo->exec("ALTER TABLE users ADD COLUMN is_verified INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN is_typing INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN last_typing_id INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN media_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN media_type VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN is_audio INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN is_view_once INT DEFAULT 0"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN reply_to_id INT DEFAULT NULL"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE grupo_mensagens ADD COLUMN visualizado_por TEXT DEFAULT NULL"); } catch (Exception $e) {}

/* ========================================================
   VERIFICAÇÃO DINÂMICA DE PROPRIEDADE DO GRUPO
======================================================== */
$souDonoDoGrupo = false;
if ($chatId > 0) {
    try {
        $stmtDono = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE id = ? AND criador_id = ?");
        $stmtDono->execute([$chatId, $userId]);
        if($stmtDono->fetchColumn() > 0) {
            $souDonoDoGrupo = true;
        }
    } catch(Exception $e) {
        $souDonoDoGrupo = true; 
    }
}

/* ========================================================
   AÇÕES DO JAVASCRIPT / AJAX ASSÍNCRONO
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
        date_default_timezone_set('America/Sao_Paulo');
        
        $stmt = $pdo->prepare("SELECT last_activity, is_typing, last_typing_id FROM users WHERE id = ?");
        $stmt->execute([$chatId]);
        $res = $stmt->fetch();
        
        $statusText = "offline";
        if ($res) {
            $lastSeenTime = strtotime($res['last_activity']);
            $tempoAtualFuso = strtotime(date('Y-m-d H:i:s')); 
            $diff = $tempoAtualFuso - $lastSeenTime;
            
            if ((int)$res['is_typing'] === 1 && (int)$res['last_typing_id'] === $userId) {
                $statusText = "digitando...";
            } elseif ($diff <= 120) { 
                $statusText = "online";
            } else {
                $horaFormatada = date('H:i', $lastSeenTime);
                $hojeFuso = strtotime(date('Y-m-d 00:00:00')); 
                $ontemFuso = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))); 
                
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
            $stmt = $pdo->prepare("UPDATE grupo_mensagens SET mensagem = 'LOCALIZACAO_ENCERRADA' WHERE id = ? AND user_id = ?");
            $stmt->execute([$msgId, $userId]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error']);
        }
        exit;
    }

    if ($_GET['action'] === 'leave_group') {
        header('Content-Type: application/json');
        try {
            $stmtLeave = $pdo->prepare("DELETE FROM grupo_membros WHERE grupo_id = ? AND user_id = ?");
            $stmtLeave->execute([$chatId, $userId]);
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'remove_member') {
        header('Content-Type: application/json');
        $targetMemberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
        
        if (!$souDonoDoGrupo) {
            echo json_encode(['status' => 'error', 'message' => 'Apenas o dono pode remover membros']);
            exit;
        }

        try {
            $stmtRemove = $pdo->prepare("DELETE FROM grupo_membros WHERE grupo_id = ? AND user_id = ?");
            $stmtRemove->execute([$chatId, $targetMemberId]);

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['action'] === 'add_member') {
        header('Content-Type: application/json');
        $novoMembroNome = trim($_GET['username'] ?? '');
        try {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtUser->execute([$novoMembroNome]);
            $uIdFound = $stmtUser->fetchColumn();

            if($uIdFound) {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM grupo_membros WHERE grupo_id = ? AND user_id = ?");
                $stmtCheck->execute([$chatId, $uIdFound]);
                if($stmtCheck->fetchColumn() == 0) {
                    $stmtAdd = $pdo->prepare("INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?, ?)");
                    $stmtAdd->execute([$chatId, $uIdFound]);
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Usuário já está no grupo']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Usuário não encontrado']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // LISTA AS MENSAGENS E ATUALIZA CONFIRMAÇÃO DE VISUALIZAÇÃO CORRETAMENTE
    if ($_GET['action'] === 'get_group_messages') {
        header('Content-Type: application/json');
        try {
            $pdo->exec("SET time_zone = '-03:00'");
            
            $stmt = $pdo->prepare("
                SELECT id, grupo_id, user_id, mensagem, data_envio, media_url, media_type, is_audio, is_view_once, reply_to_id, visualizado_por,
                       DATE_FORMAT(data_envio, '%H:%i') as hora 
                FROM grupo_mensagens
                WHERE grupo_id = ?
                ORDER BY data_envio ASC
            ");
            $stmt->execute([$chatId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as &$m) {
                $m['id'] = (int)$m['id'];
                $m['grupo_id'] = (int)$m['grupo_id'];
                $m['user_id'] = (int)$m['user_id'];
                $m['is_audio'] = (int)($m['is_audio'] ?? 0);
                $m['is_view_once'] = (int)($m['is_view_once'] ?? 0);
                if ($m['mensagem'] === null) {
                    $m['mensagem'] = '';
                }

                $visualizadores = [];
                if (!empty($m['visualizado_por'])) {
                    $dadosDecodificados = json_decode($m['visualizado_por'], true);
                    if (is_array($dadosDecodificados)) {
                        $visualizadores = $dadosDecodificados;
                    }
                }
                
                if (!in_array($userId, $visualizadores)) {
                    $visualizadores[] = $userId;
                    $novoJsonVisualizado = json_encode($visualizadores);
                    
                    $stmtUpdateViews = $pdo->prepare("UPDATE grupo_mensagens SET visualizado_por = ? WHERE id = ?");
                    $stmtUpdateViews->execute([$novoJsonVisualizado, $m['id']]);
                    
                    $m['visualizado_por'] = $novoJsonVisualizado;
                }
            }
            echo json_encode(['status' => 'success', 'messages' => $messages]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

/* ========================================================
   ENVIAR MENSAGEM DO GRUPO (POST ASSÍNCRONO VIA FETCH API)
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
            INSERT INTO grupo_mensagens (grupo_id, user_id, mensagem, data_envio, media_url, media_type, is_audio, is_view_once, reply_to_id, visualizado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$chatId, $userId, $msg !== '' ? $msg : null, $agoraLocal, $mediaUrl, $mediaType, $isAudio, $isViewOnce, $replyToId, json_encode([$userId])]);
        $novoIdInserido = $pdo->lastInsertId();

        $stmtCancelTyping = $pdo->prepare("UPDATE users SET is_typing = 0 WHERE id = ?");
        $stmtCancelTyping->execute([$userId]);

        echo json_encode([
            'status' => 'success', 
            'id' => $novoIdInserido, 
            'hora' => date('H:i', strtotime($agoraLocal)),
            'media_url' => $mediaUrl
        ]);
        exit;
    }
    echo json_encode(['status' => 'empty']);
    exit;
}

/* ========================================================
   BUSCA DE DADOS DO GRUPO / MEMBROS
======================================================== */
$user = ['username' => 'Grupo', 'avatar' => 'default.png', 'is_verified' => 0, 'last_activity' => '', 'is_typing' => 0, 'last_typing_id' => 0];
$listaMembrosStr = '';
$membrosCompletos = []; 

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

    try {
        $stmtMembros = $pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.is_verified 
            FROM users u 
            INNER JOIN grupo_membros gm ON u.id = gm.user_id 
            WHERE gm.grupo_id = ? 
            LIMIT 50
        ");
        $stmtMembros->execute([$chatId]);
        $membrosCompletos = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($membrosCompletos)) {
            $stmtMembros = $pdo->prepare("
                SELECT u.id, u.username, u.avatar, u.is_verified 
                FROM users u 
                INNER JOIN grupo_membros gm ON u.id = gm.user_id 
                WHERE gm.grupo_id = ? 
                LIMIT 50
            ");
            $stmtMembros->execute([$chatId]);
            $membrosCompletos = $stmtMembros->fetchAll(PDO::FETCH_ASSOC);
        }

        if(!empty($membrosCompletos)) {
            $nomesArr = array_column($membrosCompletos, 'username');
            $listaMembrosStr = implode(', ', $nomesArr);
        }
    } catch(Exception $e) {
        $listaMembrosStr = '';
        $membrosCompletos = [];
    }
}

$inicialStatusText = "offline";
$inicialStatusClass = "offline";

if(!empty($listaMembrosStr)) {
    $inicialStatusText = htmlspecialchars($listaMembrosStr);
    $inicialStatusClass = "membros-zap";
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Chat em Tempo Real</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0e1621; color: #fff; display: flex; flex-direction: column; height: 100vh; height: -webkit-fill-available; overflow: hidden; position: fixed; width: 100vw; }
html { height: -webkit-fill-available; }

.header { background: #17212b; padding: 12px 15px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #101921; height: 60px; flex-shrink: 0; position: relative; z-index: 100; }
.header .back-btn { color: #7f91a4; font-size: 20px; text-decoration: none; padding: 5px; display: flex; align-items: center; }
.header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; cursor: pointer; }
.header .user-info { display: flex; flex-direction: column; max-width: 65%; cursor: pointer; }
.header .user-info .profile-link-trigger { text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 1px; }
.header .user-info strong { font-size: 16px; color: #f5f5f5; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.header .verified-badge { color: #40a7e3; font-size: 13px; display: inline-flex; align-items: center; }
.header .user-info span { font-size: 12px; color: #7f91a4; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
.header .user-info span.online, .header .user-info span.typing { color: #40a7e3 !important; font-weight: 500; }

.chat { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 15px; display: flex; flex-direction: column; gap: 10px; background-image: radial-gradient(rgba(23, 33, 43, 0.5) 15%, transparent 16%); background-size: 16px 16px; -webkit-overflow-scrolling: touch; }

.msg { padding: 8px 12px; max-width: 75%; word-break: break-word; position: relative; font-size: 15px; border-radius: 12px; display: flex; flex-direction: column; transition: transform 0.15s cubic-bezier(0.1, 0.88, 0.3, 1), background 0.3s ease; touch-action: pan-y; }
.msg .reply-action-btn { position: absolute; top: 50%; transform: translateY(-50%); background: #1f2c3a; border: none; color: #7f91a4; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; display: none; align-items: center; justify-content: center; font-size: 13px; z-index: 10; }
.msg.me .reply-action-btn { left: -38px; }
.msg.you .reply-action-btn { right: -38px; }

@media (hover: hover) {
    .msg:hover .reply-action-btn { display: flex; }
}

.quoted-box { background: rgba(0, 0, 0, 0.18); border-left: 3px solid #40a7e3; padding: 5px 8px; margin-bottom: 6px; border-radius: 4px; font-size: 13px; color: #b0c4d6; max-height: 60px; overflow: hidden; text-overflow: ellipsis; cursor: pointer; }
.me { background: #2b5278; align-self: flex-end; border-radius: 12px 12px 0px 12px; }
.you { background: #182533; align-self: flex-start; border-radius: 12px 12px 12px 0px; }
.msg-meta { align-self: flex-end; font-size: 10px; color: #7f91a4; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
.me .msg-meta { color: #a2bccc; }

.status-ticks { font-size: 11px; display: inline-flex; align-items: center; }
.status-ticks.enviado { color: #8696a0 !important; } 
.status-ticks.recebido { color: #8696a0 !important; } 
.status-ticks.visualizado i { color: #53bdeb !important; } 

img.media { max-width: 100%; max-height: 250px; border-radius: 8px; margin-bottom: 4px; display: block; object-fit: cover; }
.custom-audio-player audio { display: none !important; }
.custom-audio-player { display: flex; align-items: center; gap: 10px; width: 220px; padding: 4px 0; }
.audio-play-btn { background: none; border: none; color: #40a7e3; font-size: 22px; cursor: pointer; padding: 0; }
.me .audio-play-btn { color: #fff; }
.audio-timeline { flex: 1; display: flex; flex-direction: column; gap: 4px; }
.audio-slider-container { width: 100%; height: 4px; background: rgba(255,255,255,0.2); border-radius: 2px; cursor: pointer; position: relative; }
.audio-progress-bar { height: 100%; width: 0%; background: #40a7e3; border-radius: 2px; }
.me .audio-progress-bar { background: #fff; }
.audio-time-info { font-size: 11px; color: #7f91a4; display: flex; justify-content: space-between; }

.footer-container { background: #17212b; border-top: 1px solid #101921; display: flex; flex-direction: column; padding-bottom: max(10px, env(safe-area-inset-bottom)); flex-shrink: 0; z-index: 100; }
.reply-bar-preview { display: none; background: #141e27; border-left: 4px solid #40a7e3; padding: 8px 15px; justify-content: space-between; align-items: center; font-size: 13px; color: #abbccf; }
.reply-bar-preview .reply-bar-content { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 85%; }
.reply-bar-preview .reply-bar-close { color: #f15c6d; cursor: pointer; font-size: 16px; padding: 2px 5px; }

form { display: flex; align-items: center; gap: 8px; padding: 8px 12px; width: 100%; }
input[type=text] { flex: 1; padding: 12px; border: none; outline: none; border-radius: 20px; background: #24303f; color: #fff; font-size: 15px; height: 40px; }
.btn-send, .btn-audio { background: none; border: none; color: #5288c1; font-size: 24px; cursor: pointer; padding: 4px 8px; display: flex; align-items: center; justify-content: center; }
.btn-audio.recording { color: #f15c6d !important; }

.view-once-bubble-btn { display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 8px; color: #40a7e3; cursor: pointer; }
.view-once-bubble-btn.opened { color: #7f91a4 !important; cursor: default; }

.preview-container { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0b141a; z-index: 999; flex-direction: column; justify-content: space-between; padding: 20px; box-sizing: border-box; }
.preview-body { flex: 1; display: flex; align-items: center; justify-content: center; }
.preview-body img { max-width: 95%; max-height: 75%; object-fit: contain; }
.preview-footer-ui { display: flex; flex-direction: column; gap: 12px; width: 100%; }
.view-once-toggle { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #8696a0; color: #8696a0; display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; }
.view-once-toggle.active { background: #40a7e3; border-color: #40a7e3; color: #fff; }

.view-once-lightbox { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 1000; justify-content: center; align-items: center; }
.view-once-lightbox img { max-width: 100%; max-height: 90%; }
.close-lightbox { position: absolute; top: 20px; right: 20px; font-size: 30px; color: #fff; cursor: pointer; padding: 5px; }

.file-input-wrapper { position: relative; color: #7f91a4; font-size: 24px; cursor: pointer; padding: 4px; display: flex; align-items: center; }
.zap-attach-menu { display: none; position: absolute; bottom: 48px; left: 0; background: #23313d; padding: 8px; border-radius: 12px; flex-direction: column; gap: 4px; box-shadow: 0 -4px 15px rgba(0,0,0,0.4); min-width: 130px; }
.zap-attach-item { padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; font-size: 15px; }

.zap-group-modal { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0b141a; z-index: 2000; flex-direction: column; animation: slideInUp 0.23s cubic-bezier(0.1, 0.88, 0.3, 1); overflow-y: auto; -webkit-overflow-scrolling: touch; }
@keyframes slideInUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.zap-modal-header { display: flex; align-items: center; gap: 20px; padding: 15px; background: #111b21; border-bottom: 1px solid #222e35; position: sticky; top: 0; z-index: 10; height: 55px; }
.zap-modal-header .close-modal-btn { color: #8696a0; font-size: 22px; cursor: pointer; padding: 4px; }
.zap-modal-header h2 { margin: 0; font-size: 18px; font-weight: 500; color: #e9edef; }
.zap-modal-hero { display: flex; flex-direction: column; align-items: center; background: #111b21; padding: 25px 15px; border-bottom: 10px solid #0c1317; }
.zap-modal-hero img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
.zap-modal-hero h1 { margin: 0 0 5px 0; font-size: 20px; font-weight: 500; color: #e9edef; text-align: center; display: flex; align-items: center; gap: 6px; }
.zap-modal-hero p { margin: 0; font-size: 13px; color: #8696a0; text-align: center; }
.zap-modal-section { background: #111b21; border-bottom: 10px solid #0c1317; padding: 15px; }
.zap-modal-section-title { font-size: 13px; color: #40a7e3; font-weight: 500; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
.zap-add-member-btn { display: flex; align-items: center; gap: 15px; padding: 12px 5px; color: #40a7e3; font-size: 16px; font-weight: 500; cursor: pointer; border-bottom: 1px solid #222e35; text-decoration: none; }
.zap-add-member-btn .icon-box { width: 38px; height: 38px; border-radius: 50%; background: #40a7e3; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.zap-member-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #222e35; }
.zap-member-item:last-child { border-bottom: none; }
.zap-member-item img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
.zap-member-info { display: flex; align-items: center; justify-content: space-between; flex: 1; }
.zap-member-name { font-size: 15px; color: #e9edef; font-weight: 500; display: flex; align-items: center; gap: 5px; }
.zap-remove-btn { background: transparent; border: none; color: #ea0038; font-size: 16px; padding: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.zap-modal-action-section { background: #111b21; padding: 10px 15px; display: flex; flex-direction: column; margin-bottom: 20px; }
.zap-btn-danger { display: flex; align-items: center; justify-content: center; gap: 12px; background: transparent; border: none; color: #ea0038; font-size: 16px; font-weight: 500; padding: 15px; width: 100%; cursor: pointer; }
</style>
</head>
<body>

<div class="header">
    <a href="contatos.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" onclick="abrirModalGrupo()">
    <div class="user-info" onclick="abrirModalGrupo()">
        <div class="profile-link-trigger">
            <strong>
                <?= htmlspecialchars($user['username']) ?>
                <?php if ($user['is_verified'] === 1): ?>
                    <span class="verified-badge" title="Verificado"><i class="fa-solid fa-circle-check"></i></span>
                <?php endif; ?>
            </strong>
            <span id="statusText" class="<?= $inicialStatusClass ?>"><?= $inicialStatusText ?></span>
        </div>
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
                <button id="confirmSendBtn" style="background:#40a7e3; border:none; width:40px; height:40px; border-radius:50%; color:#fff; cursor:pointer;"><i class="fa-solid fa-paper-plane"></i></button>
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
            </div>
        </div>
        <input type="text" name="message" id="messageInput" placeholder="Escreva uma mensagem..." autocomplete="off">
        <button class="btn-send" id="btnSendText" type="button" style="display:none;"><i class="fa-solid fa-paper-plane"></i></button>
        <button class="btn-audio" id="btnAudioMic" type="button"><i class="fa-solid fa-microphone"></i></button>
    </form>
</div>
<?php endif; ?>

<div class="zap-group-modal" id="zapGroupModal">
    <div class="zap-modal-header">
        <i class="fa-solid fa-xmark close-modal-btn" onclick="fecharModalGrupo()"></i>
        <h2>Dados do Grupo</h2>
    </div>
    
    <div class="zap-modal-hero">
        <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" alt="Grupo Avatar">
        <h1>
            <?= htmlspecialchars($user['username']) ?>
            <?php if ($user['is_verified'] === 1): ?>
                <span class="verified-badge"><i class="fa-solid fa-circle-check"></i></span>
            <?php endif; ?>
        </h1>
        <p>Grupo · <span id="modalParticipantCount"><?= count($membrosCompletos) ?></span> participantes</p>
    </div>

    <div class="zap-modal-section">
        <div class="zap-modal-section-title">Participantes</div>
        
        <?php if($souDonoDoGrupo): ?>
            <a href="add_membro.php?id=<?= $chatId ?>" class="zap-add-member-btn">
                <div class="icon-box"><i class="fa-solid fa-user-plus"></i></div>
                <span>Adicionar Participante</span>
            </a>
        <?php endif; ?>

        <div id="modalMembersList" style="margin-top: 5px;">
            <?php if(!empty($membrosCompletos)): ?>
                <?php foreach($membrosCompletos as $membro): ?>
                    <div class="zap-member-item" id="member-row-<?= (int)$membro['id'] ?>">
                        <img src="uploads/<?= htmlspecialchars($membro['avatar'] ?: 'default.png') ?>" alt="Membro Avatar">
                        <div class="zap-member-info">
                            <div class="zap-member-name">
                                <?= htmlspecialchars($membro['username']) ?>
                                <?php if ((int)$membro['is_verified'] === 1): ?>
                                    <span class="verified-badge" title="Verificado"><i class="fa-solid fa-circle-check"></i></span>
                                <?php endif; ?>
                                <?php if((int)$membro['id'] === $userId): ?>
                                    <span style="color:#8696a0; font-size:12px; margin-left:8px; font-weight:normal;">Você</span>
                                <?php endif; ?>
                            </div>

                            <?php if($souDonoDoGrupo && (int)$membro['id'] !== $userId): ?>
                                <button class="zap-remove-btn" onclick="removerMembroDoGrupo(<?= (int)$membro['id'] ?>, '<?= htmlspecialchars($membro['username']) ?>')" title="Remover participante">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: #8696a0; font-size: 14px; text-align: center; padding: 10px 0;">Nenhum membro encontrado.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="zap-modal-action-section">
        <button class="zap-btn-danger" onclick="sairDoGrupo()"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sair do grupo</button>
    </div>
</div>

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
    const userIdLogado = parseInt("<?= $userId ?>", 10);
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

    const replyBarPreview = document.getElementById('replyBarPreview');
    const replyBarText = document.getElementById('replyBarText');
    const replyToInputId = document.getElementById('replyToInputId');
    const zapGroupModal = document.getElementById('zapGroupModal');

    let viewOnceActive = false;
    let isCheckingMessages = false;
    let isCheckingStatus = false;
    let typingTimeout = null;
    let amITyping = false;
    let textoRespondidoTemporario = ""; 

    function abrirModalGrupo() {
        if(zapGroupModal) zapGroupModal.style.display = 'flex';
    }

    function fecharModalGrupo() {
        if(zapGroupModal) zapGroupModal.style.display = 'none';
    }

    function sairDoGrupo() {
        if(confirm("Tem certeza que deseja sair deste grupo?")) {
            fetch(`${window.location.pathname}?action=leave_group&id=${chatId}`)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    alert("Você saiu do grupo!");
                    window.location.href = "contatos.php";
                } else {
                    alert("Erro ao tentar sair.");
                }
            });
        }
    }

    function removerMembroDoGrupo(memberId, username) {
        if(confirm(`Tem certeza que deseja remover ${username} do grupo?`)) {
            fetch(`${window.location.pathname}?action=remove_member&id=${chatId}&member_id=${memberId}`)
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    const row = document.getElementById(`member-row-${memberId}`);
                    if(row) row.remove();
                    
                    const countEl = document.getElementById('modalParticipantCount');
                    if(countEl) {
                        let atual = parseInt(countEl.textContent, 10) || 1;
                        countEl.textContent = atual - 1;
                    }
                } else {
                    alert(data.message || "Erro ao remover membro.");
                }
            }).catch(() => {
                alert("Erro de conexão ao remover membro.");
            });
        }
    }

    if(zapClipBtn) {
        zapClipBtn.addEventListener('click', (e) => { e.stopPropagation(); zapAttachMenu.style.display = zapAttachMenu.style.display === 'flex' ? 'none' : 'flex'; });
        document.addEventListener('click', () => zapAttachMenu.style.display = 'none');
    }

    function formatTime(s) { 
        if(isNaN(s) || s === Infinity) return "0:00"; 
        const m = Math.floor(s/60); 
        const sec = Math.floor(s%60).toString().padStart(2,'0'); 
        return m+":"+sec; 
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

    function configurarEventosMobileNoBalao(elemento, msgId, textoTratado) {
        if(!elemento) return;
        let touchStartX = 0;
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
        }, { passive: false });

        elemento.addEventListener('touchmove', function(e) {
            let diffX = e.touches[0].clientX - touchStartX;
            if (diffX < 0) {
                let deslocamento = Math.max(diffX, -70); 
                elemento.style.transform = `translateX(${deslocamento}px)`;
            }
        }, { passive: true });

        elemento.addEventListener('touchend', function(e) {
            let diffX = e.changedTouches[0].clientX - touchStartX;
            elemento.style.transform = '';
            if (diffX < -60) {
                abrirModoResposta(msgId, textoTratado);
            }
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
        renderizarMensagemOtimista(textoPuro, localUrl, false, (viewOnceActive && mediaFile.files.length > 0), replyToInputId.value, idTemporario);
        
        messageInput.value = '';
        if(btnSendText) btnSendText.style.display = 'none';
        if(btnAudioMic) btnAudioMic.style.display = 'block';
        ocultarPreview();
        fecharModoResposta();

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const data = await response.json();
            
            if(data.status === 'success') {
                const balaoProvisorio = chatBox.querySelector(`[data-otimista="${idTemporario}"]`);
                if(balaoProvisorio) {
                    balaoProvisorio.removeAttribute('data-otimista');
                    balaoProvisorio.setAttribute('data-msg-id', data.id);
                    balaoProvisorio.setAttribute('id', `msg-real-${data.id}`);
                    
                    const ticksEl = balaoProvisorio.querySelector('.status-ticks');
                    if(ticksEl) {
                        ticksEl.className = 'status-ticks recebido';
                        ticksEl.innerHTML = '<i class="fa-solid fa-check-double"></i>';
                    }
                    
                    let textoTratadoParaInput = textoPuro.replace(/'/g, "\\'");
                    let botaoRep = balaoProvisorio.querySelector('.reply-action-btn');
                    if(botaoRep) {
                        botaoRep.setAttribute('onclick', `abrirModoResposta(${data.id}, '${textoTratadoParaInput}')`);
                    }
                }
            }
            setTimeout(loadMessages, 300);
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
            } else { setTypingStatus(false); }
        });
        messageInput.addEventListener('keydown', (e) => { if(e.key==='Enter') { e.preventDefault(); enviarFormularioDireto(); } });
    }
    if(btnSendText) btnSendText.addEventListener('click', enviarFormularioDireto);
    if(confirmSendBtn) confirmSendBtn.addEventListener('click', () => { messageInput.value = previewCaptionInput.value; enviarFormularioDireto(); });

    function renderizarMensagemOtimista(texto, mediaUrl, isAudio, isViewOnce, replyId = 0, stampId = "") {
        const h = new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        let html = '';

        if(parseInt(replyId) > 0 && textoRespondidoTemporario !== "") {
            html += `<div class="quoted-box"><b>Resposta:</b> ${escapeHtml(textoRespondidoTemporario)}</div>`;
        }

        if(isViewOnce) {
            html = `<div class="view-once-bubble-btn"><i class="fa-solid fa-number-one"></i> Mídia única</div>`;
        } else {
            if(mediaUrl) html += `<img class="media" src="${mediaUrl}">`;
            if(texto) html += `<div>${escapeHtml(texto)}</div>`;
        }
        
        let textoTratadoParaInput = texto.replace(/'/g, "\\'");
        let botaoResponder = `<button class="reply-action-btn" onclick="abrirModoResposta(0, '${textoTratadoParaInput}')"><i class="fa-solid fa-reply"></i></button>`;

        let novoBalao = `<div class="msg me" data-otimista="${stampId}">${botaoResponder}<div class="msg-content-wrapper">${html}</div><div class="msg-meta">${h} <span class="status-ticks enviado"><i class="fa-solid fa-check"></i></span></div></div>`;
        
        chatBox.insertAdjacentHTML('beforeend', novoBalao);
        
        const elementoAlvo = chatBox.querySelector(`[data-otimista="${stampId}"]`);
        configurarEventosMobileNoBalao(elementoAlvo, 0, texto);
        
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    async function loadMessages() {
        if (isCheckingMessages || chatId <= 0) return;
        isCheckingMessages = true;
        try {
            const response = await fetch(`${window.location.pathname}?action=get_group_messages&id=${chatId}&_=${Date.now()}`);
            const data = await response.json();
            if(data.status === 'success') {
                
                data.messages.forEach(m => {
                    let msgExistente = chatBox.querySelector(`[data-msg-id="${m.id}"]`) || chatBox.querySelector(`#msg-real-${m.id}`);
                    let textoTratadoE2E = descriptografarTexto(m.mensagem);
                    let deQuem = (parseInt(m.user_id) === userIdLogado) ? 'me' : 'you';

                    let tickIcon = '<i class="fa-solid fa-check"></i>'; 
                    let tickClass = 'enviado';

                    let visualizadores = [];
                    if (m.visualizado_por) {
                        try {
                            let parsed = JSON.parse(m.visualizado_por);
                            if (Array.isArray(parsed)) {
                                visualizadores = parsed;
                            }
                        } catch(e){}
                    }
                    
                    if(visualizadores.length > 1) {
                        tickIcon = '<i class="fa-solid fa-check-double"></i>';
                        tickClass = 'visualizado'; 
                    } else {
                        tickIcon = '<i class="fa-solid fa-check-double"></i>';
                        tickClass = 'recebido'; 
                    }

                    if (!msgExistente) {
                        let conteudo = '';

                        if (m.reply_to_id) {
                            conteudo += `<div class="quoted-box" onclick="focarMensagem(${m.reply_to_id})"><b>Resposta</b></div>`;
                        }

                        if (m.media_url) {
                            if (m.is_audio === 1) {
                                conteudo += `<div class="custom-audio-player"><audio src="${m.media_url}" controls></audio></div>`;
                            } else {
                                conteudo += `<img class="media" src="${m.media_url}" onclick="visualizarImagemNativa('${m.media_url}')">`;
                            }
                        }
                        
                        if (textoTratadoE2E) {
                            conteudo += `<div>${escapeHtml(textoTratadoE2E)}</div>`;
                        }

                        let ticks = (deQuem === 'me') ? `<span class="status-ticks ${tickClass}">${tickIcon}</span>` : '';
                        let textoTratadoParaInput = (m.mensagem || "").replace(/'/g, "\\'");
                        let botaoResponder = `<button class="reply-action-btn" onclick="abrirModoResposta(${m.id}, '${textoTratadoParaInput}')"><i class="fa-solid fa-reply"></i></button>`;

                        chatBox.insertAdjacentHTML('beforeend', `<div class="msg ${deQuem}" data-msg-id="${m.id}" id="msg-real-${m.id}">${botaoResponder}${conteudo}<div class="msg-meta">${m.hora} ${ticks}</div></div>`);
                        
                        let novoElemento = chatBox.querySelector(`[data-msg-id="${m.id}"]`);
                        configurarEventosMobileNoBalao(novoElemento, m.id, m.mensagem);
                        chatBox.scrollTop = chatBox.scrollHeight;
                    } else {
                        if (msgExistente.getAttribute('data-msg-id') !== String(m.id)) {
                            msgExistente.setAttribute('data-msg-id', m.id);
                        }
                        if(deQuem === 'me') {
                            let ticksEl = msgExistente.querySelector('.status-ticks');
                            if(ticksEl) {
                                ticksEl.className = `status-ticks ${tickClass}`;
                                ticksEl.innerHTML = tickIcon;
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

    async function loadStatus() {
        if (isCheckingStatus || chatId <= 0) return;
        isCheckingStatus = true;
        try {
            fetch(`${window.location.pathname}?action=ping`).catch(()=>{});
            const response = await fetch(`${window.location.pathname}?action=get_status&id=${chatId}&_=${Date.now()}`);
            const data = await response.json();
            
            if (data && data.statusText) {
                if(statusTextEl.classList.contains('membros-zap') && data.statusText !== 'digitando...') {
                    return;
                }
                statusTextEl.classList.remove('online', 'typing', 'membros-zap');
                statusTextEl.textContent = data.statusText;
                if (data.statusText === 'digitando...') { statusTextEl.classList.add('typing'); }
            }
        } catch(e) {} finally { isCheckingStatus = false; }
    }

    function visualizarImagemNativa(url) { const l = document.getElementById('viewOnceLightbox'); document.getElementById('viewOnceLightboxImg').src=url; l.style.display='flex'; }
    function fecharLightbox() { document.getElementById('viewOnceLightbox').style.display='none'; }
    function escapeHtml(t) { return t.replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">"); }

    setInterval(loadMessages, 1000); 
    setInterval(loadStatus, 2000);   
    
    loadMessages();
    loadStatus();
</script>
</body>
</html>