<?php
// Certifique-se de que o nome deste arquivo de configuração está correto (ex: confi.php ou config.php)
require_once 'confi.php'; 

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Identifica a página atual pelo nome do arquivo para manter o botão ativo
$currentPage = basename($_SERVER['PHP_SELF']);

// =========================
// SESSION
// =========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Usuário';
$userId = $_SESSION['user_id'];

// =========================
// BACKEND API
// =========================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];

    switch ($action) {
        case 'search_users':
            $q = "%".($_POST['q'] ?? '')."%";
            $stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE username LIKE ? AND id != ?");
            $stmt->execute([$q, $userId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;

        case 'add_friend':
            $fid = (int)($_POST['friend_id'] ?? 0);
            $stmt = $pdo->prepare("
                INSERT INTO friend_requests (sender_id, receiver_id, status, created_at)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$userId, $fid]);
            echo json_encode(['status'=>'ok']);
            exit;

        case 'get_friends':
            // 1. Puxa os amigos e contatos diretos
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.avatar,
                (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                FROM friends f
                JOIN users u 
                    ON u.id = f.friend_id OR u.id = f.user_id
                WHERE (f.user_id = ? OR f.friend_id = ?) AND u.id != ?
                GROUP BY u.id
            ");
            $stmt->execute([$userId, $userId, $userId, $userId]);
            $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. Puxa os grupos que o usuário pertence para listar junto
            $stmt_grupos = $pdo->prepare("
                SELECT g.id, g.nome AS username, NULL AS avatar, 0 AS unread_count, 1 AS is_group
                FROM grupos g 
                JOIN grupo_membros gm ON g.id = gm.grupo_id 
                WHERE gm.user_id = ? 
                ORDER BY g.nome ASC
            ");
            $stmt_grupos->execute([$userId]);
            $groups = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);

            // Junta os dois arrays (Amigos + Grupos) para enviar ao Front-end
            $response = array_merge($friends ? $friends : [], $groups ? $groups : []);

            echo json_encode($response);
            exit;

        case 'logout':
            session_destroy();
            echo json_encode(['status'=>'ok']);
            exit;
    }

    echo json_encode(['error'=>'invalid']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Chatbrasil</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --bg: #0e1621;
    --panel: #17212b;
    --panel-hover: #202b36;
    --text: #f5f5f5;
    --muted: #7f91a4;
    --blue: #5288c1;
    --blue-hover: #6096d1;
    --input-bg: #24303f;
    --border: #101924;
    --green-badge: #4caf50;
    --whatsapp-green: #00a884;
    --filter-chip-bg: #202c33;
    --filter-chip-active-bg: #5288c1; /* Alterado de verde para azul igual ao tema */
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

body {
    background: var(--bg);
    color: var(--text);
    overflow: hidden;
    height: 100vh;
}

/* PAGES CONTROLLER (Multi-page) */
.page {
    display: none;
    height: 100vh;
    flex-direction: column;
    padding-bottom: 65px;
}
.page.active {
    display: flex;
}

/* HEADER */
.header {
    height: 56px;
    background: var(--panel);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    font-size: 18px;
    font-weight: 500;
    border-bottom: 1px solid var(--border);
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

/* ESTILO DA ABA DE FILTROS IGUAL DO WHATSAPP */
.whatsapp-filters {
    display: flex;
    gap: 8px;
    padding: 12px 16px;
    background: var(--bg);
    overflow-x: auto;
    border-bottom: 1px solid var(--border);
}
.whatsapp-filters::-webkit-scrollbar {
    display: none;
}
.filter-chip {
    background: var(--filter-chip-bg);
    color: var(--muted);
    padding: 6px 14px;
    border-radius: 16px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    user-select: none;
    border: none;
}
.filter-chip:hover {
    background: var(--panel-hover);
    color: var(--text);
}
.filter-chip.active {
    background: var(--filter-chip-active-bg);
    color: #fff;
}

/* LISTS & CONTAINERS */
.list {
    flex: 1;
    overflow-y: auto;
}

/* CHAT ITEM PATTERN */
.chat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 16px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid var(--border);
}
.chat-item:hover {
    background: var(--panel-hover);
}

.avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #2b394a;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
}
.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.chat-info {
    flex: 1;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}
.chat-text-wrapper {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
    flex: 1;
}

.chat-name {
    font-weight: 500;
    font-size: 15px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.chat-meta {
    font-size: 13px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.badge {
    background: var(--green-badge);
    color: #fff;
    font-size: 11px;
    font-weight: bold;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* SEARCH BOX */
.search-box {
    padding: 12px 16px;
    background: var(--panel);
}
.search-container {
    position: relative;
    display: flex;
    align-items: center;
}
.search-container i {
    position: absolute;
    left: 12px;
    color: var(--muted);
}
.search-box input {
    width: 100%;
    padding: 10px 12px 10px 38px;
    border: 2px solid transparent;
    border-radius: 8px;
    background: var(--input-bg);
    color: #fff;
    outline: none;
    font-size: 14px;
    transition: border 0.2s;
}
.search-box input:focus {
    border-color: var(--blue);
}

/* BUTTONS */
.btn {
    background: var(--blue);
    border: none;
    padding: 8px 14px;
    border-radius: 6px;
    color: #fff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: background 0.2s;
}
.btn:hover {
    background: var(--blue-hover);
}
.btn-danger {
    background: #e53935;
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    margin-top: 20px;
}
.btn-danger:hover {
    background: #d32f2f;
}

/* TELEGRAM PROFILE VIEW */
.profile-card {
    background: var(--panel);
    padding: 24px 16px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
}
.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: #fff;
}

/* BOTTOM NAVIGATION BAR */
.bottom-bar {
    height: 60px;
    background: var(--panel);
    display: flex;
    justify-content: space-around;
    align-items: center;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    border-top: 1px solid var(--border);
    z-index: 100;
}
.bottom-bar .item {
    flex: 1;
    text-align: center;
    font-size: 11px;
    color: var(--muted);
    cursor: pointer;
    padding: 8px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    transition: color 0.2s;
}
.bottom-bar .item i {
    font-size: 20px;
}
.bottom-bar .item.active {
    color: var(--blue);
}
</style>
</head>
<body>

<div class="page active" id="page-chats">
    <div class="header">
        <div>Chat Brasil</div>
        <i class="fa fa-search" style="color: var(--muted); cursor: pointer;" onclick="goToPage('add_contatos.php')"></i>
    </div>
    
    <div class="whatsapp-filters">
        <button class="filter-chip active" id="filter-all" onclick="changeChatFilter('all')">Todos</button>
        <button class="filter-chip" id="filter-unread" onclick="changeChatFilter('unread')">Não lidas</button>
        <button class="filter-chip" id="filter-read" onclick="changeChatFilter('read')">Lidas</button>
        <button class="filter-chip" id="filter-groups" onclick="changeChatFilter('groups')">Grupos</button>
    </div>
    
    <div class="list" id="friendsList"></div>
</div>

<div class="page" id="page-contacts">
    <div class="header">Contatos</div>
    <div class="list" style="text-align:center; padding-top: 40px; color: var(--muted);">
        Use a aba "Add Contato" para buscar novos usuários.
    </div>
</div>

<div class="page" id="page-add-contacts">
    <div class="header">Adicionar Contato</div>
    <div class="search-box">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" oninput="searchUsers(this.value)" placeholder="Buscar novos usuários...">
        </div>
    </div>
    <div class="list" id="searchList"></div>
</div>

<div class="page" id="page-config">
    <div class="header">Configurações</div>
    <div class="list">
        <div class="profile-card">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div style="font-size: 18px; font-weight: 500;"><?php echo sanitize($username); ?></div>
            <div style="font-size: 13px; color: var(--muted);">@<?php echo sanitize($username); ?></div>
        </div>
        
        <div style="padding: 0 16px;">
            <button class="btn btn-danger" onclick="logout()">
                <i class="fa fa-sign-out-alt"></i> Sair do Telegram
            </button>
        </div>
    </div>
</div>

<div class="bottom-bar">
    <div class="item active" id="nav-chats" onclick="goToPage('home.php')">
        <i class="fa-regular fa-comment-dots"></i>
        <span>home</span>
    </div>
    <div class="item" id="nav-contacts" onclick="goToPage('contatos.php')">
        <i class="fa-regular fa-user"></i>
        <span>Contatos</span>
    </div>
    <div class="item" id="nav-add-contacts" onclick="goToPage('add_contatos.php')">
        <i class="fa-solid fa-user-plus"></i>
        <span>Add Contato</span>
    </div>
    <div class="item" id="nav-create-group" onclick="goToPage('criar_grupo.php')">
        <i class="fa-solid fa-users-gear"></i>
        <span>Criar Grupo</span>
    </div>
    <div class="item" id="nav-groups" onclick="goToPage('grupo.php')">
        <i class="fa-solid fa-users"></i>
        <span>Grupos</span>
    </div>
    <div class="item" id="nav-config" onclick="goToPage('config.php')">
        <i class="fa fa-cog"></i>
        <span>Configurações</span>
    </div>
</div>

<script>
const currentFilename = "<?php echo $currentPage; ?>";
let cacheFriends = []; 
let currentFilter = 'all';

// Inicializador ao carregar a página
document.addEventListener("DOMContentLoaded", function() {
    loadFriendsAndGroups();
});

function loadFriendsAndGroups() {
    fetch('home.php?action=get_friends')
        .then(res => res.json())
        .then(data => {
            cacheFriends = data;
            renderChats();
        })
        .catch(err => console.error("Erro ao carregar lista de chats:", err));
}

function renderChats() {
    const listContainer = document.getElementById('friendsList');
    listContainer.innerHTML = '';

    if (cacheFriends.length === 0) {
        listContainer.innerHTML = `<div style="text-align:center; padding-top:40px; color:var(--muted);">Nenhuma conversa encontrada.</div>`;
        return;
    }

    // Filtragem baseada na aba ativa do topo
    let filteredList = cacheFriends.filter(item => {
        // Força a conversão para número para evitar conflito de String vs Number vindos do PHP/PDO
        const isGroup = parseInt(item.is_group) === 1;

        if (currentFilter === 'groups') {
            return isGroup;
        } else if (currentFilter === 'unread') {
            return item.unread_count > 0 && !isGroup;
        } else if (currentFilter === 'read') {
            return item.unread_count == 0 && !isGroup;
        } else if (currentFilter === 'all') {
            return !isGroup; // No filtro "Todos", apenas pessoas aparecem.
        }
        return true;
    });

    if (filteredList.length === 0) {
        listContainer.innerHTML = `<div style="text-align:center; padding-top:40px; color:var(--muted);">Nada para mostrar neste filtro.</div>`;
        return;
    }

    filteredList.forEach(item => {
        const chatItem = document.createElement('div');
        chatItem.className = 'chat-item';
        
        const isGroup = parseInt(item.is_group) === 1;

        // Define para onde o clique levará (chat privado ou chat de grupo)
        if (isGroup) {
            chatItem.onclick = () => window.location.href = `grupo.php?id=${item.id}`;
        } else {
            chatItem.onclick = () => window.location.href = `conversas.php?id=${item.id}`;
        }

        // Renderização do avatar ou da inicial
        let avatarContent = '';
        if (isGroup) {
            avatarContent = `<i class="fa-solid fa-users" style="color:#fff; font-size:18px;"></i>`;
        } else if (item.avatar) {
            avatarContent = `<img src="uploads/${item.avatar}" alt="avatar">`;
        } else {
            const firstLetter = item.username ? item.username.substr(0,1).toUpperCase() : '?';
            avatarContent = `<span style="color:#fff; font-weight:bold;">${firstLetter}</span>`;
        }

        // Renderização do badge de não lidas (opcional para grupos no momento)
        let badgeHtml = '';
        if (item.unread_count > 0) {
            badgeHtml = `<div class="badge">${item.unread_count}</div>`;
        }

        const safeUsername = item.username ? escapeHtml(item.username) : 'Sem nome';

        chatItem.innerHTML = `
            <div class="avatar" style="${isGroup ? 'background: #2f6ea5;' : ''}">
                ${avatarContent}
            </div>
            <div class="chat-info">
                <div class="chat-text-wrapper">
                    <div class="chat-name">${safeUsername} ${isGroup ? '<span style="font-size:11px; color:var(--blue); font-weight:normal;">(Grupo)</span>' : ''}</div>
                    <div class="chat-meta">${isGroup ? 'Toque para abrir o grupo' : 'Toque para conversar'}</div>
                </div>
                ${badgeHtml}
            </div>
        `;
        listContainer.appendChild(chatItem);
    });
}

function changeChatFilter(filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-chip').forEach(btn => btn.classList.remove('active'));
    
    if(filter === 'all') document.getElementById('filter-all').classList.add('active');
    if(filter === 'unread') document.getElementById('filter-unread').classList.add('active');
    if(filter === 'read') document.getElementById('filter-read').classList.add('active');
    if(filter === 'groups') document.getElementById('filter-groups').classList.add('active');
    
    renderChats();
}

// ... Resto das funções mantidas intactas conforme solicitado
function searchUsers(query) {
    const searchContainer = document.getElementById('searchList');
    if(!query.trim()) {
        searchContainer.innerHTML = '';
        return;
    }
    
    let formData = new FormData();
    formData.append('q', query);

    fetch('home.php?action=search_users', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(users => {
        searchContainer.innerHTML = '';
        if(users.length === 0) {
            searchContainer.innerHTML = '<div style="padding:16px; color:var(--muted);">Nenhum usuário encontrado.</div>';
            return;
        }
        users.forEach(u => {
            let item = document.createElement('div');
            item.className = 'chat-item';
            const firstLetter = u.username ? u.username.substr(0,1).toUpperCase() : '?';
            item.innerHTML = `
                <div class="avatar">
                    ${u.avatar ? `<img src="uploads/${u.avatar}">` : `<span style="color:#fff;">${firstLetter}</span>`}
                </div>
                <div class="chat-info">
                    <div class="chat-text-wrapper">
                        <div class="chat-name">${escapeHtml(u.username)}</div>
                    </div>
                    <button class="btn" onclick="addFriend(${u.id}, this)">Adicionar</button>
                </div>
            `;
            searchContainer.appendChild(item);
        });
    });
}

function addFriend(friendId, btnElement) {
    let formData = new FormData();
    formData.append('friend_id', friendId);

    fetch('home.php?action=add_friend', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'ok') {
            btnElement.innerText = "Enviado";
            btnElement.disabled = true;
            btnElement.style.background = "#4caf50";
        }
    });
}

function goToPage(url) {
    window.location.href = url;
}

function logout() {
    fetch('home.php?action=logout')
    .then(() => window.location.href = 'login.php');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
</body>
</html>