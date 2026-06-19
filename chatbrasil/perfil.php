<?php
// Define o fuso horário padrão corrigido
date_default_timezone_set('America/Sao_Paulo');

require_once 'confi.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Verifica se o usuário atual está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$myId = $_SESSION['user_id'];

// CORREÇÃO: Alinhado com o conversas.php usando last_activity e fuso horário manual
$nowString = date('Y-m-d H:i:s');
$updateSeen = $pdo->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
$updateSeen->execute([$nowString, $myId]);

// Define qual perfil será visualizado (Se não passar ID na URL, mostra o próprio perfil)
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : $myId;

// Busca os dados do usuário do perfil que está sendo visitado
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?"); 
$stmt->execute([$profileId]);
$user = $stmt->fetch();

// Se o usuário não existir no banco, redireciona para os chats
if (!$user) {
    header("Location: conversas.php");
    exit;
}

// Lógica de cálculo do Status alterada para o formato exato de horários fixos
$statusText = "Visto por último há muito tempo";
$statusClass = "offline";

// CORREÇÃO: Utilizando a coluna unificada last_activity
if (!empty($user['last_activity'])) {
    $lastSeenTime = strtotime($user['last_activity']);
    $currentTime = strtotime(date('Y-m-d H:i:s')); // CORREÇÃO: Força o tempo atual a respeitar o fuso de SP
    $diff = $currentTime - $lastSeenTime;

    // Se o perfil visitado for o seu próprio, você está obviamente online. 
    // Se for outro usuário, valida se a última atividade dele foi dentro de 120 segundos para alinhar com conversas.php
    if ($profileId === $myId || $diff <= 120) { 
        $statusText = "Online";
        $statusClass = "online";
    } else {
        $hojeInicial = strtotime(date('Y-m-d 00:00:00')); // CORREÇÃO: Limite de hoje localizado
        $ontemInicial = strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))); // CORREÇÃO: Limite de ontem localizado
        $horaFormatada = date('H:i', $lastSeenTime);

        if ($lastSeenTime >= $hojeInicial) {
            $statusText = "Visto por último hoje às " . $horaFormatada;
        } elseif ($lastSeenTime >= $ontemInicial) {
            $statusText = "Visto por último ontem às " . $horaFormatada;
        } else {
            $statusText = "Visto por último em " . date('d/m/Y', $lastSeenTime) . " às " . $horaFormatada;
        }
    }
}

// CONFIGURAÇÃO DO VERIFICADO CORRIGIDA:
// Agora valida estritamente a coluna 'is_verified' vinda da sua tabela do banco de dados
$exibirVerificado = (!empty($user['is_verified']) && $user['is_verified'] == 1);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil de <?= htmlspecialchars($user['username']) ?></title>
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
            padding-bottom: 80px; /* Espaço para a barra inferior não cobrir o conteúdo */
        }

        .header {
            padding: 15px 20px;
            background: var(--bg-secondary);
            font-size: 18px;
            font-weight: bold;
            border-bottom: 1px solid #101921;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header a {
            color: var(--tg-blue);
            text-decoration: none;
            font-size: 18px;
            display: flex;
            align-items: center;
        }

        /* Perfil Superior */
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

        .profile-card h3 {
            margin: 5px 0 2px 0;
            font-size: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .verified-badge {
            color: var(--tg-online);
            font-size: 16px;
        }

        /* Classes de Status Estilo Telegram */
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

        /* Lista de informações */
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
            width: 100%;
            box-sizing: border-box;
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

        /* Ações do Perfil (Iniciar Conversa) */
        .profile-actions {
            margin-top: 25px;
            padding: 0 20px;
            text-align: center;
        }

        .btn-message {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--tg-blue);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background 0.2s;
        }

        .btn-message:hover {
            background: #4373a6;
        }

        /* Modal da Foto Expandida */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 999;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            max-width: 90%;
            max-height: 80%;
            border-radius: 8px;
            object-fit: contain;
        }

        /* Menu de Navegação Inferior - Estilo Mobile Telegram */
        .bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background: var(--bg-secondary);
            border-top: 1px solid #101921;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 990;
            box-sizing: border-box;
        }

        .bottom a {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 11px;
            width: 100%;
            height: 100%;
            gap: 4px;
            transition: color 0.2s;
        }

        .bottom a i {
            font-size: 20px;
        }

        /* Estado ativo do menu */
        .bottom a.active {
            color: var(--tg-online);
        }
    </style>
</head>
<body>

<div class="header">
    <a href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i></a>
    <span>Perfil</span>
</div>

<div class="profile-card">
    <div class="avatar-container" onclick="openModal()">
        <div class="avatar">
            <img src="uploads/<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'default.png' ?>" id="avatar-img-view">
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
        <i class="fa-solid fa-at"></i>
        <div class="info-data">
            <span class="info-value">@<?= htmlspecialchars($user['username']) ?></span>
            <span class="info-label">Nome de usuário</span>
        </div>
    </div>

    <div class="info-item">
        <i class="fa-solid fa-circle-info"></i>
        <div class="info-data">
            <span class="info-value">
                <?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Nenhuma biografia disponível.' ?>
            </span>
            <span class="info-label">Biografia</span>
        </div>
    </div>

</div>

<?php if ($profileId !== $myId): ?>
<div class="profile-actions">
    <a href="conversas.php?id=<?= $profileId ?>" class="btn-message">
        <i class="fa-solid fa-comment"></i> Enviar Mensagem
    </a>
</div>
<?php endif; ?>

<div id="photoModal" class="modal" onclick="closeModal()">
    <img class="modal-content" src="uploads/<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'default.png' ?>">
</div>

<div class="bottom">
    <a href="home.php">
        <i class="fa-solid fa-comment"></i>
        <span>Home</span>
    </a>
    <a href="contatos.php" class="active">
        <i class="fa-solid fa-user-group"></i>
        <span>Contatos</span>
    </a>
    <a href="config.php">
        <i class="fa-solid fa-gear"></i>
        <span>Configurações</span>
    </a>
</div>

<script>
function openModal() {
    document.getElementById('photoModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('photoModal').style.display = 'none';
}
</script>

</body>
</html>