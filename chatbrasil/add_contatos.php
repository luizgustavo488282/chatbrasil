<?php
require_once 'confi.php'; 
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$userId = $_SESSION['user_id'];
$error_message = "";
$searched_user = null;

// LISTA MANUAL DE IDs COM SELO VERIFICADO (Caso queira forçar por ID além do banco de dados)
// Adicione aqui os IDs que ganham o selo automaticamente. Exemplo: [1, 2, 5]
$usuariosComSelo = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

// PROCESSAMENTO DAS AÇÕES (Adicionar / Aceitar / Recusar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $friendId = filter_input(INPUT_POST, 'friend_id', FILTER_VALIDATE_INT);

    if ($friendId && $friendId !== $userId) {
        if ($action === 'add_friend') {
            // Verifica se já existe um pedido ou amizade
            $check = $pdo->prepare("SELECT id FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $check->execute([$userId, $friendId, $friendId, $userId]);
            
            $checkFriends = $pdo->prepare("SELECT id FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $checkFriends->execute([$userId, $friendId, $friendId, $userId]);

            if (!$check->fetch() && !$checkFriends->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$userId, $friendId]);
            }
        } 
        elseif ($action === 'reject_friend') {
            // Remove qualquer solicitação pendente
            $delRequest = $pdo->prepare("DELETE FROM friend_requests WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
            $delRequest->execute([$userId, $friendId, $friendId, $userId]);
        }
        
        // Mantém o parâmetro de busca na URL ao redirecionar para não sumir com o resultado da tela
        $redirectUrl = $_SERVER['PHP_SELF'];
        if (isset($_POST['search_query'])) {
            $redirectUrl .= '?search=' . urlencode($_POST['search_query']);
        }
        header("Location: " . $redirectUrl);
        exit;
    } elseif ($friendId === $userId) {
        $error_message = "Você não pode adicionar a si mesmo como amigo.";
    }
}

// LOGICA DE BUSCA EXCLUSIVA POR ID OU USERNAME (Permitindo self-search)
$search_input = '';
if (isset($_GET['search'])) {
    $search_input = trim($_GET['search']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_input'])) {
    $search_input = trim($_POST['search_input']);
}

if (!empty($search_input)) {
    if (is_numeric($search_input)) {
        // Busca por ID (Corrigido para u.is_verified)
        $stmtSearch = $pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.is_verified,
            (SELECT COUNT(*) FROM friends f WHERE f.user_id = ? AND f.friend_id = u.id) as is_friend,
            (SELECT status FROM friend_requests fr WHERE fr.sender_id = ? AND fr.receiver_id = u.id) as sent_request,
            (SELECT status FROM friend_requests fr WHERE fr.receiver_id = ? AND fr.sender_id = u.id) as received_request
            FROM users u WHERE u.id = ? LIMIT 1
        ");
        $stmtSearch->execute([$userId, $userId, $userId, $search_input]);
    } else {
        // Busca por Username exato (Corrigido para u.is_verified)
        $stmtSearch = $pdo->prepare("
            SELECT u.id, u.username, u.avatar, u.is_verified,
            (SELECT COUNT(*) FROM friends f WHERE f.user_id = ? AND f.friend_id = u.id) as is_friend,
            (SELECT status FROM friend_requests fr WHERE fr.sender_id = ? AND fr.receiver_id = u.id) as sent_request,
            (SELECT status FROM friend_requests fr WHERE fr.receiver_id = ? AND fr.sender_id = u.id) as received_request
            FROM users u WHERE u.username = ? LIMIT 1
        ");
        $stmtSearch->execute([$userId, $userId, $userId, $search_input]);
    }
    
    $searched_user = $stmtSearch->fetch();
    if (!$searched_user && !isset($_GET['action'])) {
        $error_message = "Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Buscar Usuários - Telegram Style</title>
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
        
        .search-container { 
            padding: 16px; 
            background: #0e1621; 
        }
        .search-form {
            display: flex;
            align-items: center;
            background: #17212b;
            border-radius: 22px;
            padding: 6px 6px 6px 16px;
            border: 2px solid transparent;
            transition: border 0.2s;
        }
        .search-form:focus-within { border-color: #5288c1; }
        .search-form i { color: #7f91a4; margin-right: 12px; font-size: 14px; }
        .search-form input {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 15px;
            width: 100%;
            outline: none;
        }
        .search-form input::placeholder { color: #7f91a4; }
        
        .btn-search {
            background: #5288c1;
            border: none;
            color: white;
            padding: 8px 18px;
            border-radius: 18px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
        }
        .btn-search:hover { background: #6597cc; }

        .alert-error {
            background: #3a1f24;
            color: #ec3d3d;
            padding: 8px 16px;
            margin: 5px 16px;
            border-radius: 8px;
            font-size: 13px;
            border: 1px solid #522329;
        }

        /* Resultado da Busca */
        .section-title {
            font-size: 14px;
            color: #5288c1;
            font-weight: 500;
            padding: 10px 16px 5px 16px;
            text-transform: uppercase;
        }
        .contacts-list { margin-top: 4px; }
        .item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 16px;
            background: #17212b;
            border-top: 1px solid #101921;
            border-bottom: 1px solid #101921;
        }

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
            pointer-events: none;
        }
        /* Permite que o link de si mesmo funcione */
        .user-link.self-user {
            cursor: pointer;
            pointer-events: auto;
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
        .avatar.saved-messages-avatar { background: #5288c1; color: #fff; font-size: 20px; }
        .user-info { flex: 1; display: flex; flex-direction: column; gap: 2px; }
        
        /* Alinhamento flexbox para embutir e alinhar horizontalmente o selo azul ao lado do nome */
        .username { 
            font-weight: 500; 
            font-size: 16px; 
            color: #f5f5f5; 
            display: flex; 
            align-items: center; 
            gap: 5px; 
        }
        
        /* Estilização do selo verificado */
        .verified-badge {
            color: #4f9fe3;
            font-size: 14px;
        }

        .status { font-size: 13px; color: #7f91a4; }
        .action-buttons { display: flex; gap: 8px; align-items: center; z-index: 5; }
        
        .btn-action {
            border: none;
            color: #fff;
            padding: 8px 16px;
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
        <i class="fa-solid fa-bars"></i>
        <span>Buscar Usuários</span>
    </div>

    <div class="search-container">
        <form method="POST" action="?action=search" class="search-form">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search_input" value="<?= htmlspecialchars($search_input) ?>" placeholder="Buscar por ID ou Nome de Usuário..." required>
            <button type="submit" class="btn-search">Buscar</button>
        </form>
    </div>

    <?php if(!empty($error_message)): ?>
        <div class="alert-error">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= $error_message ?>
        </div>
    <?php endif; ?>

    <?php if ($searched_user): ?>
        <div class="section-title">Resultado Global</div>
        <div class="contacts-list">
            <div class="item">
                <?php 
                // Verifica se o usuário buscado é ele mesmo para aplicar o visual "Mensagens Salvas"
                $isMe = ($searched_user['id'] == $userId); 
                
                // Condicional híbrida corrigida para verificar a coluna 'is_verified' do banco ou a lista manual
                $isVerified = in_array($searched_user['id'], $usuariosComSelo) || (isset($searched_user['is_verified']) && $searched_user['is_verified'] == 1);
                ?>
                <a href="chats.php?id=<?= $searched_user['id'] ?>" class="user-link <?= $isMe ? 'self-user' : ($searched_user['is_friend'] > 0 ? '' : 'not-friend') ?>">
                    
                    <?php if ($isMe): ?>
                        <div class="avatar saved-messages-avatar">
                            <i class="fa-solid fa-bookmark"></i>
                        </div>
                    <?php else: ?>
                        <div class="avatar">
                            <img src="uploads/<?= !empty($searched_user['avatar']) ? $searched_user['avatar'] : 'default.png' ?>" alt="Avatar">
                        </div>
                    <?php endif; ?>

                    <div class="user-info">
                        <?php if ($isMe): ?>
                            <span class="username">
                                Mensagens Salvas 
                                <?php if ($isVerified): ?>
                                    <i class="fa-solid fa-circle-check verified-badge"></i>
                                <?php endif; ?>
                                <small style="color: #5288c1; font-size: 11px;">#<?= $searched_user['id'] ?></small>
                            </span>
                            <span class="status">seu espaço de armazenamento em nuvem</span>
                        <?php else: ?>
                            <span class="username">
                                <?= htmlspecialchars($searched_user['username']) ?> 
                                <?php if ($isVerified): ?>
                                    <i class="fa-solid fa-circle-check verified-badge"></i>
                                <?php endif; ?>
                                <small style="color: #5288c1; font-size: 11px;">#<?= $searched_user['id'] ?></small>
                            </span>
                            <span class="status">visto recentemente</span>
                        <?php endif; ?>
                    </div>
                </a>

                <div class="action-buttons">
                    <?php if ($isMe): ?>
                        <span style="color: #5288c1; font-size: 13px; margin-right: 5px;"><i class="fa-solid fa-cloud"></i> Nuvem</span>
                    
                    <?php elseif ($searched_user['is_friend'] > 0): ?>
                        <span style="color: #7f91a4; font-size: 13px; margin-right: 5px;"><i class="fa-solid fa-user-check"></i> Amigo</span>
                    
                    <?php elseif ($searched_user['sent_request'] === 'pending'): ?>
                        <button class="btn-action btn-pending" disabled>Pendente</button>
                        <form method="POST" action="?action=reject_friend">
                            <input type="hidden" name="friend_id" value="<?= $searched_user['id'] ?>">
                            <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_input) ?>">
                            <button type="submit" class="btn-action btn-reject" title="Cancelar Pedido"><i class="fa-solid fa-xmark"></i></button>
                        </form>

                    <?php elseif ($searched_user['received_request'] === 'pending'): ?>
                        <span style="color: #7f91a4; font-size: 13px;">Te enviou convite</span>
                        <form method="POST" action="?action=reject_friend">
                            <input type="hidden" name="friend_id" value="<?= $searched_user['id'] ?>">
                            <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_input) ?>">
                            <button type="submit" class="btn-action btn-reject">Recusar</button>
                        </form>

                    <?php else: ?>
                        <form method="POST" action="?action=add_friend">
                            <input type="hidden" name="friend_id" value="<?= $searched_user['id'] ?>">
                            <input type="hidden" name="search_query" value="<?= htmlspecialchars($search_input) ?>">
                            <button type="submit" class="btn-action btn-add">Adicionar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

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

</body>
</html>