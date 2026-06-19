<?php
require_once 'confi.php'; // Certifique-se de que o nome está correto (confi.php ou config.php)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$userId = $_SESSION['user_id'];

// PROCESSAMENTO DAS AÇÕES (Adicionar / Aceitar / Recusar / Remover)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $friendId = filter_input(INPUT_POST, 'friend_id', FILTER_VALIDATE_INT);
    $action = $_GET['action'];
    
    if ($friendId && $friendId !== $userId) {
        if ($action === 'add_friend') {
            // Verifica se já não existe um pedido ou amizade para evitar duplicados
            $check = $pdo->prepare("SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $check->execute([$userId, $friendId, $friendId, $userId]);
            
            $checkFriends = $pdo->prepare("SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $checkFriends->execute([$userId, $friendId, $friendId, $userId]);

            if (!$check->fetch() && !$checkFriends->fetch()) {
                // Insere a solicitação de amizade como pendente
                $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$userId, $friendId]);
            }
        } 
        elseif ($action === 'accept_friend') {
            // Aceita o pedido: deleta da tabela de requests e adiciona na tabela friends (mútuo)
            $del = $pdo->prepare("DELETE FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $del->execute([$userId, $friendId, $friendId, $userId]);

            $ins1 = $pdo->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
            $ins1->execute([$userId, $friendId]);
            $ins2 = $pdo->prepare("INSERT INTO friends (user_id, friend_id) VALUES (?, ?)");
            $ins2->execute([$friendId, $userId]);
        }
        elseif ($action === 'reject_friend' || $action === 'remove_friend') {
            // Remove qualquer solicitação pendente
            $delRequest = $pdo->prepare("DELETE FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $delRequest->execute([$userId, $friendId, $friendId, $userId]);

            // Remove da lista de amigos se já forem amigos
            $delFriend = $pdo->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $delFriend->execute([$userId, $friendId, $friendId, $userId]);
        }
        
        // Recarrega a página para limpar o POST (Evita reenvio ao dar F5)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// BUSCAR APENAS OS USUÁRIOS QUE SÃO AMIGOS DO USUÁRIO LOGADO (Incluindo a coluna u.is_verified)
$query = "
    SELECT u.id, u.username, u.avatar, u.is_verified,
    1 as is_friend,
    (SELECT status FROM friend_requests fr WHERE fr.sender_id = ? AND fr.receiver_id = u.id) as sent_request,
    (SELECT status FROM friend_requests fr WHERE fr.receiver_id = ? AND fr.sender_id = u.id) as received_request
    FROM users u 
    INNER JOIN friends f ON f.friend_id = u.id
    WHERE f.user_id = ? AND u.id != ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$userId, $userId, $userId, $userId]);
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Contatos - chat brasil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #0e1621;
            color: #f5f5f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .header {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 20px;
            background: #17212b;
            font-size: 20px;
            font-weight: 500;
            border-bottom: 1px solid #101921;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .header i { color: #7f91a4; cursor: pointer; }
        .search-container { padding: 12px 16px; background: #0e1621; }
        .search-box {
            display: flex;
            align-items: center;
            background: #17212b;
            border-radius: 22px;
            padding: 8px 16px;
            border: 2px solid transparent;
            transition: border 0.2s;
        }
        .search-box:focus-within { border-color: #5288c1; }
        .search-box i { color: #7f91a4; margin-right: 12px; font-size: 14px; }
        .search-box input {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 15px;
            width: 100%;
            outline: none;
        }
        .search-box input::placeholder { color: #7f91a4; }
        
        /* Lista de Contatos */
        .contacts-list { margin-top: 4px; }
        .item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 16px;
            transition: background 0.15s ease;
        }
        .item:hover { background: #17212b; }

        /* Estilo específico para itens clicáveis de ação superior, como o Telegram */
        #nav-add-contacts {
            cursor: pointer;
            color: #5288c1;
            font-weight: 500;
        }
        #nav-add-contacts i {
            background: #24313f;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #5288c1;
        }

        /* Link de redirecionamento interno */
        .user-link {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            text-decoration: none;
            color: inherit;
        }
        .user-link.not-friend {
            cursor: default;
            pointer-events: none; /* Desativa o clique se não for amigo */
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            overflow: hidden;
            background: #2b5278;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
        
        /* Ajuste inline com flexbox para alinhar o selo perfeitamente ao lado do nome */
        .username { 
            font-weight: 500; 
            font-size: 16px; 
            color: #f5f5f5; 
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Estilização visual do selo de verificado */
        .verified-badge {
            color: #4f9fe3;
            font-size: 14px;
        }
        
        .status { font-size: 13px; color: #7f91a4; }
        .action-buttons { display: flex; gap: 8px; align-items: center; z-index: 5; }
        .btn-action {
            border: none;
            color: #fff;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 16px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
        }
        .btn-action:active { transform: scale(0.96); }
        .btn-add { background: #5288c1; }
        .btn-add:hover { background: #6597cc; }
        .btn-reject { background: #24313f; color: #ec3d3d; }
        .btn-reject:hover { background: #2f3f51; }
        .btn-pending { background: #24313f; color: #7f91a4; cursor: default; }
        .bottom {
            position: fixed;
            bottom: 0;
            width: 100%;
            display: flex;
            justify-content: space-around;
            background: #17212b;
            padding: 8px 0;
            border-top: 1px solid #101921;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.3);
            z-index: 10;
        }
        .bottom a {
            color: #7f91a4;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            transition: color 0.2s ease;
        }
        .bottom i { display: block; font-size: 22px; }
        .bottom a.active { color: #5288c1; }
        .bottom a:hover { color: #b1c3d4; }
        .spacer { height: 80px; }
    </style>
</head>
<body>

    <div class="header">
        <span>Contatos</span>
    </div>

    <div class="search-container">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" placeholder="Pesquisar">
        </div>
    </div>

    <div class="contacts-list">
        <div class="item" id="nav-add-contacts" onclick="goToPage('add_contatos.php')">
            <i class="fa-solid fa-user-plus"></i>
            <span>Add Contato</span>
        </div>

        <?php foreach($users as $u): ?>
            <div class="item">
                <a href="conversas.php?id=<?= $u['id'] ?>" class="user-link <?= $u['is_friend'] > 0 ? '' : 'not-friend' ?>">
                    <div class="avatar">
                        <img src="uploads/<?= !empty($u['avatar']) ? $u['avatar'] : 'default.png' ?>" alt="Avatar">
                    </div>

                    <div class="user-info">
                        <span class="username">
                            <?= htmlspecialchars($u['username']) ?>
                            <?php if (isset($u['is_verified']) && $u['is_verified'] == 1): ?>
                                <i class="fa-solid fa-circle-check verified-badge"></i>
                            <?php endif; ?>
                        </span>
                        <span class="status">visto recentemente</span>
                    </div>
                </a>

                <div class="action-buttons">
                    <?php if ($u['is_friend'] > 0): ?>
                        <form method="POST" action="?action=remove_friend">
                            <input type="hidden" name="friend_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-action btn-reject">Remover</button>
                        </form>

                    <?php elseif ($u['sent_request'] === 'pending'): ?>
                        <button class="btn-action btn-pending" disabled>Pendente</button>
                        <form method="POST" action="?action=reject_friend">
                            <input type="hidden" name="friend_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-action btn-reject"><i class="fa-solid fa-xmark"></i></button>
                        </form>

                    <?php elseif ($u['received_request'] === 'pending'): ?>
                        <form method="POST" action="?action=accept_friend">
                            <input type="hidden" name="friend_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-action btn-add">Aceitar</button>
                        </form>
                        <form method="POST" action="?action=reject_friend">
                            <input type="hidden" name="friend_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-action btn-reject">Recusar</button>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="spacer"></div>

    <div class="bottom">
        <a href="home.php">
            <i class="fa-solid fa-comment"></i>
            <span>Chats</span>
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
        function goToPage(url) {
            window.location.href = url;
        }

        // Lógica dinâmica para capturar a página atual e adicionar a classe active
        const currentFilename = window.location.pathname.split('/').pop();
        if (currentFilename === 'add_contatos.php') {
            if(document.getElementById('page-add-contacts')) {
                document.getElementById('page-add-contacts').classList.add('active');
            }
            if(document.getElementById('nav-add-contacts')) {
                document.getElementById('nav-add-contacts').classList.add('active');
            }
        }
    </script>
</body>
</html>