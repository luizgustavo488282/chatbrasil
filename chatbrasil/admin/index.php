<?php
// admin/index.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_clone');
define('DB_USER', 'root');
define('DB_PASS', '');

$totalUsuarios = 0;
$verificados   = 0;
$admins        = 0;
$totalMensagens = 0;
$totalBanidos   = 0;
$usuarios      = [];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $action = $_POST['action'];

        if ($userId > 0) {
            switch ($action) {
                case 'toggle_cargo':
                    $novoCargo = $_POST['novo_cargo'];
                    $stmt = $pdo->prepare("UPDATE users SET cargo = ? WHERE id = ?");
                    $stmt->execute([$novoCargo, $userId]);
                    break;

                case 'toggle_verify':
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;

                case 'toggle_ban':
                    $tempo = $_POST['tempo_banimiento'] ?? 'perma';
                    if ($tempo === 'perma') {
                        $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = NULL WHERE id = ?");
                        $stmt->execute([$userId]);
                    } else {
                        $dias = intval($tempo);
                        $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
                        $stmt->execute([$dias, $userId]);
                    }
                    break;

                case 'revogar_ban':
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_expires_at = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;

                case 'delete_user':
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;

                case 'edit_user':
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $avatar = $_POST['avatar'];
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, avatar = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $avatar, $userId]);
                    break;

                case 'dar_aviso':
                    $stmt = $pdo->prepare("UPDATE users SET warns = warns + 1 WHERE id = ?");
                    $stmt->execute([$userId]);

                    $stmtCheck = $pdo->prepare("SELECT warns FROM users WHERE id = ?");
                    $stmtCheck->execute([$userId]);
                    $resWarn = $stmtCheck->fetch();
                    if ($resWarn && $resWarn['warns'] >= 3) {
                        $stmtBan = $pdo->prepare("UPDATE users SET is_banned = 1, ban_expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY), warns = 0 WHERE id = ?");
                        $stmtBan->execute([$userId]);
                    }
                    break;

                case 'zerar_mensagens':
                    $stmt = $pdo->prepare("UPDATE users SET total_messages = 0 WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;

                case 'toggle_mute':
                    $tempoMute = $_POST['tempo_mute'] ?? '0';
                    if ($tempoMute === '0') {
                        $stmt = $pdo->prepare("UPDATE users SET muted_until = NULL WHERE id = ?");
                        $stmt->execute([$userId]);
                    } else {
                        $horas = intval($tempoMute);
                        $stmt = $pdo->prepare("UPDATE users SET muted_until = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?");
                        $stmt->execute([$horas, $userId]);
                    }
                    break;

                case 'forcar_logout':
                    $stmt = $pdo->prepare("UPDATE users SET session_token = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;

                case 'toggle_freeze':
                    $stmt = $pdo->prepare("UPDATE users SET is_frozen = NOT is_frozen WHERE id = ?");
                    $stmt->execute([$userId]);
                    break;
            }
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $totalUsuarios  = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
    $totalMensagens = $pdo->query("SELECT SUM(total_messages) FROM users")->fetchColumn() ?? 0;
    $totalBanidos   = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1 OR is_frozen = 1")->fetchColumn() ?? 0;

    $stmtUsers = $pdo->query("SELECT id, username, email, cargo, is_verified, is_banned, ban_expires_at, avatar, total_messages, warns, created_at, last_online, registration_ip, muted_until, is_frozen FROM users ORDER BY id ASC");
    $usuarios = $stmtUsers->fetchAll();

} catch (PDOException $e) {
    $erroBancoDeDados = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - Ultimate Core</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .smooth-transition { transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
        .fade-in-up { animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .scale-hover:hover { transform: translateY(-2px) scale(1.005); }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .theme-sky { --main-brand: 14, 165, 233; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans theme-sky antialiased selection:bg-sky-500/10 selection:text-sky-600">

    <?php if (isset($erroBancoDeDados)): ?>
        <div class="bg-rose-50 border-b border-rose-200 text-rose-800 p-4 text-xs font-semibold shadow-inner flex items-center justify-between animate-pulse">
            <div class="flex items-center gap-2">
                <i data-lucide="database" class="w-4 h-4 text-rose-600 animate-spin"></i>
                <span>Falha Operacional Crítica: <?php echo htmlspecialchars($erroBancoDeDados); ?></span>
            </div>
            <span class="bg-rose-600 text-white px-2.5 py-1 rounded-md text-[10px] uppercase font-bold tracking-wider shadow-sm">Verificar Banco</span>
        </div>
    <?php endif; ?>

    <nav class="bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-50 px-6 py-3.5 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 group">
                <div class="w-10 h-10 bg-sky-500 text-white rounded-xl flex items-center justify-center font-black text-lg shadow-md shadow-sky-500/20 smooth-transition group-hover:rotate-6">TG</div>
                <div>
                    <h2 class="text-sm font-black text-slate-900 leading-tight tracking-tight">Terminal de Controle</h2>
                    <span class="text-[11px] text-emerald-600 font-bold flex items-center gap-1.5 mt-0.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 relative flex">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span> 
                        Servidor Operacional
                    </span>
                </div>
            </div>
            
            <div class="flex bg-slate-100 p-1.5 rounded-xl border border-slate-200/50 shadow-inner">
                <a href="index.php" class="px-4 py-2 text-xs font-bold rounded-lg smooth-transition bg-white text-slate-900 shadow-sm flex items-center gap-2 border border-slate-200/20">
                    <i data-lucide="users" class="w-3.5 h-3.5 text-sky-500"></i> Comunidade
                </a>
                <a href="telemetria.php" class="px-4 py-2 text-xs font-bold rounded-lg smooth-transition text-slate-500 hover:text-slate-800 flex items-center gap-2 hover:bg-white/50">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i> Telemetria
                </a>
                <a href="configuracoes.php" class="px-4 py-2 text-xs font-bold rounded-lg smooth-transition text-slate-500 hover:text-slate-800 flex items-center gap-2 hover:bg-white/50">
                    <i data-lucide="settings" class="w-3.5 h-3.5"></i> Ajustes Globais
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-4 md:p-8 space-y-8 fade-in-up">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-slate-200/60 pb-5">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Gerenciamento de Comunidade</h1>
                <p class="text-xs font-medium text-slate-500 mt-1">Monitore atividades, audite privilégios e controle acessos à plataforma.</p>
            </div>
            <div class="flex items-center gap-2 bg-slate-100 border border-slate-200 rounded-xl p-1.5 px-3">
                <span class="text-[11px] font-bold text-slate-500">Registros Atuais:</span>
                <span class="text-xs font-extrabold text-slate-900 bg-white shadow-sm border border-slate-200/60 px-2 py-0.5 rounded-lg"><?php echo count($usuarios); ?></span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between smooth-transition scale-hover">
                <div class="space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Total de Usuários</span>
                    <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($totalUsuarios, 0, ',', '.'); ?></h3>
                    <span class="text-[10px] text-emerald-600 font-bold flex items-center gap-1"><i data-lucide="trending-up" class="w-3 h-3"></i> Sincronizado</span>
                </div>
                <div class="p-3.5 bg-slate-50 text-slate-600 rounded-2xl border border-slate-100 shadow-sm"><i data-lucide="users" class="w-5 h-5"></i></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between smooth-transition scale-hover">
                <div class="space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Mensagens Trocadas</span>
                    <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($totalMensagens, 0, ',', '.'); ?></h3>
                    <span class="text-[10px] text-sky-600 font-bold flex items-center gap-1"><i data-lucide="message-circle" class="w-3 h-3"></i> Engajamento Global</span>
                </div>
                <div class="p-3.5 bg-sky-50 text-sky-600 rounded-2xl border border-sky-100/40 shadow-sm"><i data-lucide="activity" class="w-5 h-5"></i></div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center justify-between smooth-transition scale-hover">
                <div class="space-y-1">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest block">Contas Fora de Combate</span>
                    <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?php echo number_format($totalBanidos, 0, ',', '.'); ?></h3>
                    <span class="text-[10px] text-rose-600 font-bold flex items-center gap-1"><i data-lucide="shield-alert" class="w-3 h-3"></i> Restrições Ativas</span>
                </div>
                <div class="p-3.5 bg-rose-50 text-rose-600 rounded-2xl border border-rose-100/40 shadow-sm"><i data-lucide="shield" class="w-5 h-5 fill-rose-50"></i></div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden smooth-transition">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-700">Listagem de Contas Existentes (Ordenação Crescente)</h3>
                </div>
                <span class="text-[10px] bg-slate-200 text-slate-600 px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">Ações em Tempo Real</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 text-slate-400 text-[10px] font-black uppercase tracking-widest border-b border-slate-200/60">
                            <th class="px-6 py-4 w-20">ID</th>
                            <th class="px-6 py-4">Usuário</th>
                            <th class="px-6 py-4">E-mail</th>
                            <th class="px-6 py-4 w-32">Nível</th>
                            <th class="px-6 py-4 w-24 text-center">Selo</th>
                            <th class="px-6 py-4 text-right w-[540px]">Controles de Segurança</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-600 text-xs divide-y divide-slate-100">
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center text-slate-400 font-medium border-dashed">
                                    <div class="flex flex-col items-center justify-center gap-2 opacity-60">
                                        <i data-lucide="folder-open" class="w-8 h-8 text-slate-300"></i>
                                        <span class="text-xs font-bold">Nenhum registro localizado na base corrente.</span>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $user): ?>
                                <?php 
                                    $estaBanido = isset($user['is_banned']) && $user['is_banned'] == 1;
                                    $estaCongelado = isset($user['is_frozen']) && $user['is_frozen'] == 1;
                                    
                                    // Verificação de Mute Ativo
                                    $estaMutado = false;
                                    if (!empty($user['muted_until'])) {
                                        $dataMute = new DateTime($user['muted_until']);
                                        $agoraMute = new DateTime();
                                        if ($dataMute > $agoraMute) {
                                            $estaMutado = true;
                                        }
                                    }

                                    $cargo = !empty($user['cargo']) ? htmlspecialchars($user['cargo']) : 'Usuário';
                                    $iniciais = strtoupper(substr($user['username'], 0, 2));
                                    $warnsCount = $user['warns'] ?? 0;

                                    // Cálculo do Contador Regressivo de Banimento
                                    $textoStatus = "";
                                    if ($estaCongelado) {
                                        $textoStatus = "Congelado";
                                    } elseif ($estaBanido && !empty($user['ban_expires_at'])) {
                                        $dataExpiracao = new DateTime($user['ban_expires_at']);
                                        $agora = new DateTime();
                                        if ($dataExpiracao > $agora) {
                                            $intervalo = $agora->diff($dataExpiracao);
                                            if ($intervalo->days > 0) {
                                                $textoStatus = "Restrito (Faltam " . $intervalo->days . " dias)";
                                            } else {
                                                $textoStatus = "Restrito (Faltam " . $intervalo->h . "h)";
                                            }
                                        }
                                    } elseif ($estaBanido) {
                                        $textoStatus = "Restrito (Perma)";
                                    }
                                ?>
                                <tr class="hover:bg-slate-50/70 smooth-transition <?php echo ($estaBanido || $estaCongelado) ? 'bg-rose-50/20' : ''; ?>">
                                    <td class="px-6 py-4.5 font-mono font-bold text-sky-600 bg-slate-50/30">#<?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4.5">
                                        <div class="flex items-center gap-3.5">
                                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-slate-800 to-slate-950 text-white flex items-center justify-center font-black text-[11px] tracking-wider shadow-sm ring-2 ring-slate-100 relative">
                                                <?php if(!empty($user['avatar'])): ?>
                                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" class="w-full h-full object-cover rounded-xl" alt="">
                                                <?php else: ?>
                                                    <?php echo $iniciais; ?>
                                                <?php endif; ?>
                                                <?php if($estaMutado): ?>
                                                    <span class="absolute -top-1 -right-1 bg-amber-500 text-white p-0.5 rounded-md shadow-sm ring-1 ring-white"><i data-lucide="volume-X" class="w-2.5 h-2.5"></i></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex flex-col min-w-0">
                                                <span class="font-extrabold text-slate-900 truncate flex items-center gap-2">
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                    <?php if($estaBanido || $estaCongelado): ?>
                                                        <span class="text-[8px] <?php echo $estaCongelado ? 'bg-blue-600' : 'bg-rose-600'; ?> text-white font-black px-1.5 py-0.5 rounded-md uppercase tracking-wider shadow-sm animate-pulse"><?php echo $textoStatus; ?></span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-medium mt-0.5 flex items-center gap-1">
                                                    <i data-lucide="message-square" class="w-3 h-3 text-slate-300"></i> <?php echo $user['total_messages'] ?? 0; ?> interações
                                                    <span class="text-amber-600 font-bold ml-1 flex items-center gap-0.5"><i data-lucide="alert-triangle" class="w-2.5 h-2.5"></i> <?php echo $warnsCount; ?>/3</span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4.5 text-slate-500 font-bold tracking-tight"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4.5">
                                        <span class="px-2.5 py-1 text-[9px] font-black rounded-lg border uppercase tracking-widest shadow-sm inline-block <?php 
                                            if (strtolower($cargo) == 'admin') echo 'bg-rose-50 text-rose-700 border-rose-200/70';
                                            elseif (strtolower($cargo) == 'vip') echo 'bg-amber-50 text-amber-700 border-amber-200/70';
                                            else echo 'bg-slate-50 text-slate-600 border-slate-200/70';
                                        ?>">
                                            <?php echo $cargo; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4.5 text-center">
                                        <?php if(isset($user['is_verified']) && $user['is_verified'] == 1): ?>
                                            <span class="text-sky-500 inline-block filter drop-shadow-sm transition-transform hover:scale-110 duration-200"><i data-lucide="badge-check" class="w-4 h-4 fill-sky-100"></i></span>
                                        <?php else: ?>
                                            <span class="text-slate-300 inline-block opacity-40"><i data-lucide="badge-check" class="w-4 h-4"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            
                                            <button type="button" onclick="abrirPerfilModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Ver Perfil Detalhado" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-sky-600 hover:bg-sky-50 hover:border-sky-200 shadow-sm smooth-transition">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            </button>

                                            <button type="button" onclick="abrirEdicaoModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" title="Editar Dados Diretos" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-emerald-600 hover:bg-emerald-50 hover:border-emerald-200 shadow-sm smooth-transition">
                                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                            </button>

                                            <form method="POST" class="inline m-0" title="Aplicar Advertência (Warn)">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="dar_aviso">
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 shadow-sm smooth-transition">
                                                    <i data-lucide="alert-octagon" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="inline m-0" title="Zerar Contador de Mensagens (Flood)" onsubmit="return confirm('Deseja zerar as interações deste usuário?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="zerar_mensagens">
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 shadow-sm smooth-transition">
                                                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="inline-flex items-center gap-1 m-0" title="Silenciar Chat (Mute)">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_mute">
                                                <?php if($estaMutated = !empty($user['muted_until']) && new DateTime($user['muted_until']) > new DateTime()): ?>
                                                    <input type="hidden" name="tempo_mute" value="0">
                                                    <button type="submit" class="px-2 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 font-bold text-[10px] uppercase shadow-sm smooth-transition">Desmutar</button>
                                                <?php else: ?>
                                                    <select name="tempo_mute" class="text-[10px] bg-white border border-slate-200 rounded-md p-1 font-bold text-slate-500 cursor-pointer focus:outline-none">
                                                        <option value="2">2h</option>
                                                        <option value="24">24h</option>
                                                    </select>
                                                    <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-amber-500 hover:bg-amber-50 shadow-sm">
                                                        <i data-lucide="volume-X" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <form method="POST" class="inline m-0" title="Forçar Logout (Derrubar Sessões)" onsubmit="return confirm('Deseja invalidar o token de sessão ativa deste usuário?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="forcar_logout">
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-orange-600 hover:bg-orange-50 hover:border-orange-200 shadow-sm smooth-transition">
                                                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="inline m-0" title="<?php echo $estaCongelado ? 'Descongelar Conta' : 'Congelar Conta (Investigação)'; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_freeze">
                                                <button type="submit" class="p-1.5 rounded-lg border <?php echo $estaCongelado ? 'border-blue-200 bg-blue-50 text-blue-600' : 'border-slate-200 bg-white text-slate-400 hover:text-blue-600 hover:bg-blue-50'; ?> shadow-sm smooth-transition">
                                                    <i data-lucide="snowflake" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <a href="alterar_senhas.php?id=<?php echo $user['id']; ?>" title="Disparar Redefinição de Senha" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 shadow-sm smooth-transition">
                                                <i data-lucide="key-round" class="w-3.5 h-3.5"></i>
                                            </a>

                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_cargo">
                                                <select name="novo_cargo" onchange="this.form.submit()" class="text-[10px] font-black bg-white text-slate-600 border border-slate-200 shadow-sm rounded-lg pl-2 pr-6 py-1.5 focus:outline-none focus:ring-1 focus:ring-sky-500/30 smooth-transition cursor-pointer appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%20fill%3D%22none%22%20stroke%3D%22%2364748b%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-[length:12px] bg-[right_6px_center] bg-no-repeat">
                                                    <option value="Usuário" <?php if(strtolower($cargo) == 'usuário' || strtolower($cargo) == 'usuario') echo 'selected'; ?>>Usuário</option>
                                                    <option value="VIP" <?php if(strtolower($cargo) == 'vip') echo 'selected'; ?>>VIP</option>
                                                    <option value="Admin" <?php if(strtolower($cargo) == 'admin') echo 'selected'; ?>>Admin</option>
                                                </select>
                                            </form>

                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_verify">
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-sky-600 hover:bg-sky-50 hover:border-sky-200 shadow-sm smooth-transition">
                                                    <i data-lucide="award" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                            <form method="POST" class="inline-flex items-center gap-1.5 m-0">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <?php if($estaBanido): ?>
                                                    <input type="hidden" name="action" value="revogar_ban">
                                                    <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 font-bold text-[10px] uppercase tracking-wider shadow-sm smooth-transition hover:bg-emerald-100 flex items-center gap-1">
                                                        <i data-lucide="unlock" class="w-3 h-3"></i> Desbanir
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="toggle_ban">
                                                    <select name="tempo_banimiento" class="text-[10px] bg-white border border-slate-200 rounded-md px-1.5 py-1 font-bold text-slate-500 shadow-sm focus:outline-none cursor-pointer">
                                                        <option value="perma">Perma</option>
                                                        <option value="7">7 dias</option>
                                                        <option value="30">30 dias</option>
                                                    </select>
                                                    <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-amber-500 hover:bg-amber-50 hover:border-amber-200 shadow-sm smooth-transition">
                                                        <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </form>

                                            <form method="POST" class="inline m-0" onsubmit="return confirm('ATENÇÃO: Deseja apagar permanentemente e sem retorno esta conta do ecossistema?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-400 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 shadow-sm smooth-transition">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="modalEdicao" class="hidden fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden fade-in-up">
            <div class="p-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2"><i data-lucide="edit-2" class="w-4 h-4 text-sky-500"></i> Editar Cadastro Core</h3>
                <button type="button" onclick="fecharEdicaoModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form method="POST" class="p-5 space-y-4 m-0">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_id">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-wider mb-1">Username</label>
                    <input type="text" name="username" id="edit_username" required class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500/20 text-slate-800">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-wider mb-1">Endereço de E-mail</label>
                    <input type="email" name="email" id="edit_email" required class="w-full text-xs font-bold bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500/20 text-slate-800">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 tracking-wider mb-1">URL do Avatar</label>
                    <input type="text" name="avatar" id="edit_avatar" class="w-full text-xs font-mono bg-white border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-sky-500/20 text-slate-600">
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                    <button type="button" onclick="fecharEdicaoModal()" class="px-4 py-2 text-xs font-bold rounded-lg text-slate-500 hover:bg-slate-100 smooth-transition">Cancelar</button>
                    <button type="submit" class="px-4 py-2 text-xs font-bold bg-sky-500 text-white rounded-lg shadow-md shadow-sky-500/10 hover:bg-sky-600 smooth-transition">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalPerfil" class="hidden fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden fade-in-up">
            <div class="p-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4 text-sky-500"></i> Auditoria de Perfil Completo</h3>
                <button type="button" onclick="fecharPerfilModal()" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/40">
                    <div id="view_avatar_box" class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-sm shadow-sm"></div>
                    <div>
                        <h4 id="view_username" class="text-sm font-black text-slate-900"></h4>
                        <p id="view_email" class="text-xs font-bold text-slate-400 mt-0.5"></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50/50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black uppercase text-slate-400 tracking-wider">Cadastro IP</span>
                        <span id="view_ip" class="text-xs font-mono font-bold text-slate-700 mt-1 block">N/D</span>
                    </div>
                    <div class="bg-slate-50/50 p-3 rounded-xl border border-slate-100">
                        <span class="block text-[9px] font-black uppercase text-slate-400 tracking-wider">Advertências</span>
                        <span id="view_warns" class="text-xs font-bold text-amber-600 mt-1 block">0</span>
                    </div>
                    <div class="bg-slate-50/50 p-3 rounded-xl border border-slate-100 col-span-2">
                        <span class="block text-[9px] font-black uppercase text-slate-400 tracking-wider">Data de Criação da Conta</span>
                        <span id="view_created" class="text-xs font-bold text-slate-700 mt-1 block">N/D</span>
                    </div>
                    <div class="bg-slate-50/50 p-3 rounded-xl border border-slate-100 col-span-2">
                        <span class="block text-[9px] font-black uppercase text-slate-400 tracking-wider">Última Atividade Online</span>
                        <span id="view_online" class="text-xs font-bold text-slate-700 mt-1 block">N/D</span>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="button" onclick="fecharPerfilModal()" class="px-4 py-2 text-xs font-bold bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300 smooth-transition">Fechar Auditoria</button>
            </div>
        </div>
    </div>

    <script>
        function mudarTemaUI(classeTema) {
            document.body.className = "bg-[#f8fafc] text-slate-800 font-sans antialiased selection:bg-sky-500/10 selection:text-sky-600 " + classeTema;
            localStorage.setItem('painel-ui-theme', classeTema);
        }
        const temaSalvo = localStorage.getItem('painel-ui-theme');
        if(temaSalvo) mudarTemaUI(temaSalvo);
        lucide.createIcons();

        // Controladores do Modal de Edição Direta
        function abrirEdicaoModal(user) {
            document.getElementById('edit_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_avatar').value = user.avatar || '';
            document.getElementById('modalEdicao').classList.remove('hidden');
        }
        function fecharEdicaoModal() {
            document.getElementById('modalEdicao').classList.add('hidden');
        }

        // Controladores do Modal de Perfil Detalhado
        function abrirPerfilModal(user) {
            document.getElementById('view_username').innerText = user.username;
            document.getElementById('view_email').innerText = user.email;
            document.getElementById('view_ip').innerText = user.registration_ip || '127.0.0.1';
            document.getElementById('view_warns').innerText = (user.warns || 0) + " / 3";
            document.getElementById('view_created').innerText = user.created_at || 'Não registrada';
            document.getElementById('view_online').innerText = user.last_online || 'Não registrada';
            
            const avatarBox = document.getElementById('view_avatar_box');
            if (user.avatar) {
                avatarBox.innerHTML = `<img src="${user.avatar}" class="w-full h-full object-cover rounded-xl" />`;
            } else {
                avatarBox.innerHTML = user.username.substring(0,2).toUpperCase();
            }
            
            document.getElementById('modalPerfil').classList.remove('hidden');
        }
        function fecharPerfilModal() {
            document.getElementById('modalPerfil').classList.add('hidden');
        }
    </script>
</body>
</html>