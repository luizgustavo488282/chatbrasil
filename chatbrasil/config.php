<?php
// Define o fuso horário padrão
date_default_timezone_set('America/Sao_Paulo');

require_once 'confi.php';

// Garante que a sessão está ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// TRAVA DE SEGURANÇA MÁXIMA: Se não houver sessão ativa, para tudo aqui
// e joga o usuário estritamente na tela de login, impedindo a leitura do resto.
// =========================================================================
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Limpa resquícios se houver
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    // Redireciona de forma absoluta e encerra a execução do script instantaneamente
    header("Location: login.php");
    exit;
}

// Se passou da trava acima, temos um ID seguro para trabalhar
$userId = (int)$_SESSION['user_id'];

// 1. Atualiza o status de visto por último
$nowString = date('Y-m-d H:i:s');
$updateSeen = $pdo->prepare("UPDATE users SET last_seen = ? WHERE id = ?");
$updateSeen->execute([$nowString, $userId]);

// 2. Lógica para atualizar a foto de avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_avatar'])) {
    $ext = pathinfo($_FILES['new_avatar']['name'], PATHINFO_EXTENSION);
    $newName = "avatar_" . $userId . "_" . time() . "." . $ext;
    $target = "uploads/" . $newName;

    if (move_uploaded_file($_FILES['new_avatar']['tmp_name'], $target)) {
        $update = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $update->execute([newName, $userId]);
        header("Location: config.php");
        exit;
    }
}

// 3. Lógica para atualizar a Biografia via POST convencional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bio'])) {
    $newBio = trim($_POST['bio']);
    $updateBio = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
    $updateBio->execute([$newBio, $userId]);
    header("Location: config.php");
    exit;
}

// Busca os dados atualizados do usuário
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Se o usuário foi deletado do banco mas a sessão ficou ativa por engano
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 4. Lógica de cálculo do Status "Online" ou "Visto por último há..." (Estilo Telegram)
$statusText = "Visto por último há muito tempo";
$statusClass = "offline";

if (!empty($user['last_seen'])) {
    $lastSeenTime = strtotime($user['last_seen']);
    $currentTime = time();
    $diff = $currentTime - $lastSeenTime;

    if ($diff <= 60) { 
        $statusText = "Online";
        $statusClass = "online";
    } else {
        if ($diff < 3600) {
            $minutos = round($diff / 60);
            $statusText = "Visto por último há " . $minutos . " " . ($minutos == 1 ? "minuto" : "minutos");
        } elseif ($diff < 86400) {
            $horas = round($diff / 3600);
            $statusText = "Visto por último há " . $horas . " " . ($horas == 1 ? "hora" : "horas");
        } elseif ($diff < 172800) {
            $statusText = "Visto por último ontem";
        } else {
            $statusText = "Visto por último em " . date('d/m/Y', $lastSeenTime);
        }
    }
}

// =========================================================================
// CONTROLE DO SELO VERIFICADO AZUL (FORÇADO POR ID OU PELO BANCO)
// =========================================================================
// Modifique os números abaixo. Coloque os IDs das contas que devem ganhar o selo.
// Exemplo: se sua conta for o ID 1, deixe [1]. Se quiser liberar para o ID 1, 5 e 10, mude para [1, 5, 10].
$usuariosComSelo = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]; 

// Sistema híbrido: mostra o selo se estiver na lista acima OU se a coluna 'verified' no banco for igual a 1
$exibirVerificado = in_array($userId, $usuariosComSelo) || (isset($user['verified']) && $user['verified'] == 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurações</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
     
    <style>
        :root {
            --bg-main: #0e1621;
            --bg-secondary: #17212b;
            --bg-active: #202b36;
            --text-main: #f5f5f5;
            --text-muted: #7f91a4;
            --tg-blue: #5288c1;
            --tg-online: #4f9fe3;
        }

        body {
            margin: 0;
            background: var(--bg-main);
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding-bottom: 70px;
        }

        .header {
            padding: 15px 20px;
            background: var(--bg-secondary);
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #101921;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header a {
            color: #ec3d3d;
            text-decoration: none;
            font-size: 15px;
        }

        .profile-card {
            background: var(--bg-secondary);
            padding: 25px 15px;
            text-align: center;
            border-bottom: 1px solid #101921;
        }

        .avatar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 15px auto;
            cursor: pointer;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            background: #2b394a;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            transition: transform 0.2s;
        }

        .avatar-container:hover .avatar {
            transform: scale(1.03);
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .camera-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--tg-blue);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--bg-secondary);
            font-size: 12px;
        }

        /* AJUSTADO: Alinhamento flexbox para manter o selo perfeitamente colado ao lado direito do nome */
        .profile-card h3 {
            margin: 5px 0 2px 0;
            font-size: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* Cor azul oficial do selo do Telegram/WhatsApp */
        .verified-badge {
            color: var(--tg-online);
            font-size: 16px;
        }

        .profile-card p {
            margin: 0;
            font-size: 14px;
        }
        .profile-card p.online {
            color: var(--tg-online);
        }
        .profile-card p.offline {
            color: var(--text-muted);
        }

        .info-section {
            margin-top: 15px;
            background: var(--bg-secondary);
        }

        .info-item {
            display: flex;
            padding: 14px 20px;
            border-bottom: 1px solid #101921;
            align-items: center;
            background: transparent;
            border-left: none; border-right: none;
            width: 100%;
            text-align: left;
            box-sizing: border-box;
        }
         
        .info-item-clickable {
            cursor: pointer;
            transition: background 0.2s;
        }
        .info-item-clickable:hover {
            background: var(--bg-active);
        }

        .info-item i {
            color: var(--tg-blue);
            font-size: 18px;
            width: 35px;
        }

        .info-data {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .info-value {
            font-size: 15px;
            color: var(--text-main);
            word-break: break-word;
        }

        .info-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            display: flex;
            background: var(--bg-secondary);
            border-top: 1px solid #101921;
            box-sizing: border-box;
            z-index: 10;
        }

        .bottom-nav a, .bottom-nav .item {
            flex: 1;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            text-align: center;
            padding: 10px 0 8px 0;
            transition: background 0.2s;
            cursor: pointer;
        }

        .bottom-nav a i, .bottom-nav .item i {
            display: block;
            font-size: 20px;
            margin-bottom: 4px;
        }

        .bottom-nav a.active, .bottom-nav .item.active {
            color: var(--tg-blue);
        }

        .modal {
            display: none;
            position: fixed;
            top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.85);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .modal-content {
            max-width: 90%;
            max-height: 70%;
            border-radius: 8px;
            object-fit: contain;
        }

        .modal-buttons {
            margin-top: 20px;
            display: flex;
            gap: 15px;
        }

        .btn {
            background: var(--tg-blue);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        .btn-secondary {
            background: #2b394a;
        }

        .bio-input-box {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            width: 85%;
            max-width: 400px;
            box-sizing: border-box;
        }
        .bio-input-box h4 {
            margin: 0 0 10px 0;
            font-weight: 500;
        }
        .bio-input-box textarea {
            width: 100%;
            background: var(--bg-main);
            border: 1px solid var(--bg-active);
            color: var(--text-main);
            padding: 10px;
            border-radius: 6px;
            resize: none;
            font-family: inherit;
            box-sizing: border-box;
        }
        .bio-input-box textarea:focus {
            outline: 1px solid var(--tg-blue);
        }

        #file-input { display: none; }
    </style>
</head>
<body>

<div class="header">
    <span>Configurações</span>
    <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
</div>

<div class="profile-card">
    <div class="avatar-container" onclick="openModal()">
        <div class="avatar">
            <img src="uploads/<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'default.png' ?>" id="avatar-img-view">
        </div>
        <div class="camera-overlay">
            <i class="fa fa-camera"></i>
        </div>
    </div>

    <h3>
        <?= htmlspecialchars($user['username']) ?>
        <?php if ($exibirVerificado): ?>
            <i class="fa-solid fa-circle-check verified-badge" title="Verificado"></i>
        <?php endif; ?>
    </h3>
    <p class="<?= $statusClass ?>"><?= $statusText ?></p>
</div>

<div class="info-section">
     
    <div class="info-item">
        <i class="fa-solid fa-envelope"></i>
        <div class="info-data">
            <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
            <span class="info-label">E-mail</span>
        </div>
    </div>

    <div class="info-item">
        <i class="fa-solid fa-at"></i>
        <div class="info-data">
            <span class="info-value">@<?= htmlspecialchars($user['username']) ?></span>
            <span class="info-label">Nome de usuário</span>
        </div>
    </div>

    <div class="info-item info-item-clickable" onclick="openBioModal()">
        <i class="fa-solid fa-circle-info"></i>
        <div class="info-data">
            <span class="info-value" id="bio-display-text">
                <?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Toque para adicionar uma biografia...' ?>
            </span>
            <span class="info-label">Biografia (Toque para editar)</span>
        </div>
    </div>

</div>

<form id="avatar-form" method="POST" enctype="multipart/form-data">
    <input type="file" name="new_avatar" id="file-input" accept="image/*" onchange="submitForm()">
</form>

<div id="photoModal" class="modal" onclick="closeModal(event)">
    <img class="modal-content" src="uploads/<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'default.png' ?>" id="modal-expanded-img">
    <div class="modal-buttons">
        <button class="btn" onclick="triggerUpload()">Alterar Foto</button>
        <button class="btn btn-secondary" onclick="hideModal()">Fechar</button>
    </div>
</div>

<div id="bioModal" class="modal" onclick="closeBioModal(event)">
    <div class="bio-input-box" onclick="event.stopPropagation()">
        <h4>Editar Biografia</h4>
        <form method="POST" action="config.php">
            <textarea name="bio" rows="3" maxlength="70" placeholder="Escreva algo sobre você..."><?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : '' ?></textarea>
            <div class="modal-buttons" style="justify-content: flex-end; margin-top: 10px;">
                <button type="button" class="btn btn-secondary" onclick="hideBioModal()">Cancelar</button>
                <button type="submit" class="btn">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div class="bottom-nav">
    <a href="home.php">
        <i class="fa-regular fa-comment-dots"></i>
        <span>home</span>
    </a>
    <a href="contatos.php">
        <i class="fa-regular fa-user"></i>
        <span>Contatos</span>
    </a>
    <div class="item" id="nav-add-contacts" onclick="goToPage('add_contatos.php')">
        <i class="fa-solid fa-user-plus"></i>
        <span>Add Contato</span>
    </div>
    <a class="active" href="config.php">
        <i class="fa-solid fa-gear"></i>
        <span>Config</span>
    </a>
</div>

<script>
// Modais da Foto
function openModal() {
    document.getElementById('photoModal').style.display = 'flex';
}
function closeModal(e) {
    if (e.target.id === 'photoModal') hideModal();
}
function hideModal() {
    document.getElementById('photoModal').style.display = 'none';
}
function triggerUpload() {
    document.getElementById('file-input').click();
}
function submitForm() {
    document.getElementById('avatar-form').submit();
}

// Modais da Bio
function openBioModal() {
    document.getElementById('bioModal').style.display = 'flex';
}
function closeBioModal(e) {
    if (e.target.id === 'bioModal') hideBioModal();
}
function hideBioModal() {
    document.getElementById('bioModal').style.display = 'none';
}

// Lógica de navegação e classes ativas
const currentFilename = window.location.pathname.split('/').pop();
if (currentFilename === 'add_contatos.php') {
    document.getElementById('page-add-contacts').classList.add('active');
    document.getElementById('nav-add-contacts').classList.add('active');
}

function goToPage(page) {
    window.location.href = page;
}
</script>

</body>
</html>