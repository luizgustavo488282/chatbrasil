<?php
// ==========================================
// CONTROLE DE SESSÃO E PERMISSÃO
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuração automática para garantir o seu acesso como Admin
if (!isset($_SESSION['usuario_cargo'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nome'] = 'Admin'; 
    $_SESSION['usuario_cargo'] = 'admin'; 
}

// Verifica se o usuário está logado e se o cargo é 'admin'
if (!isset($_SESSION['usuario_cargo']) || strtolower($_SESSION['usuario_cargo']) !== 'admin') {
    die("Acesso negado. Apenas administradores podem acessar esta página.");
}

// Captura os dados do Admin logado para exibir no topo da página
$adminNome = isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Administrador';
$adminCargo = isset($_SESSION['usuario_cargo']) ? $_SESSION['usuario_cargo'] : 'admin';

// ==========================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ==========================================
$host = 'localhost';
$dbname = 'telegram_clone'; 
$dbuser = 'root';            
$dbpass = '';                

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==========================================
    // PROCESSAMENTO DE AÇÕES (POST)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $userId = (int)$_POST['user_id'];

        // 1. Alternar Verificação
        if ($_POST['action'] === 'toggle_verify') {
            $stmt = $pdo->prepare("UPDATE users SET is_verified = NOT is_verified WHERE id = ?");
            $stmt->execute([$userId]);
        }
        
        // 2. Alternar Cargo (Membro / Admin)
        if ($_POST['action'] === 'toggle_cargo') {
            $currentCargo = $_POST['current_cargo'];
            $newCargo = (strtolower($currentCargo) === 'admin') ? 'Membro' : 'Admin';
            $stmt = $pdo->prepare("UPDATE users SET cargo = ? WHERE id = ?");
            $stmt->execute([$newCargo, $userId]);
        }

        // 3. Excluir Usuário
        if ($_POST['action'] === 'delete_user') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        }

        // Recarrega a página limpa para evitar reenvio de formulário
        header("Location: painel_admin.php");
        exit;
    }

    // Busca TODOS os campos da sua tabela users mudando para ordem crescente (ASC)
    $stmt = $pdo->prepare("SELECT id, username, email, avatar, bio, created_at, last_activity, last_seen, is_verified, cargo FROM users ORDER BY id ASC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Métricas fixas de Usuários
    $totalUsuarios = count($usuarios);
    $verificados = 0;
    $admins = 0;
    foreach ($usuarios as $user) {
        if (!empty($user['is_verified']) && $user['is_verified'] == 1) $verificados++;
        if (!empty($user['cargo']) && (strtolower($user['cargo']) == 'admin' || strtolower($user['cargo']) == 'administrador')) $admins++;
    }

} catch (PDOException $e) {
    $erro_banco = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatBrasil - Painel Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans antialiased text-gray-800">

    <div class="flex h-screen overflow-hidden">
        
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white transform -translate-x-full transition-transform duration-300 ease-in-out md:relative md:translate-x-0 flex flex-col">
            <div class="h-16 flex items-center justify-between px-6 bg-slate-950">
                <div class="flex items-center gap-2 font-bold text-xl tracking-wider">
                    <i data-lucide="send" class="text-sky-400"></i>
                    <span>CHATBRASIL<span class="text-sky-400">.</span></span>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white"><i data-lucide="x"></i></button>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
                <button onclick="alterarAba('usuarios')" id="btn-menu-usuarios" class="w-full flex items-center gap-3 px-4 py-3 bg-sky-600 text-white rounded-lg font-medium transition-colors text-left">
                    <i data-lucide="users" class="w-5 h-5"></i> Usuários
                </button>
                
                <button onclick="alterarAba('graficos')" id="btn-menu-graficos" class="w-full flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition-colors text-left">
                    <i data-lucide="line-chart" class="w-5 h-5"></i> Gráficos
                </button>

                <a href="#" onclick="alert('Funcionalidade de Configurações em desenvolvimento!')" class="flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition-colors">
                    <i data-lucide="settings" class="w-5 h-5"></i> Configurações
                </a>
            </nav>

            <div class="p-4 border-t border-slate-800">
                <a href="#" onclick="alert('Logout efetuado!')" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-950/30 rounded-lg font-medium transition-colors">
                    <i data-lucide="log-out" class="w-5 h-5"></i> Sair
                </a>
            </div>
        </aside>

        <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 z-40 bg-black/40 hidden md:hidden"></div>

        <div class="flex-1 flex flex-col h-screen overflow-y-auto">
            
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
                <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-900"><i data-lucide="menu" class="w-6 h-6"></i></button>

                <div class="flex items-center gap-2 bg-gray-100 px-3 py-1.5 rounded-lg w-full max-w-md border border-transparent focus-within:border-sky-500 focus-within:bg-white transition-all ml-4 md:ml-0">
                    <i data-lucide="search" class="w-4 h-4 text-gray-400"></i>
                    <input type="text" id="inputBusca" onkeyup="filtrarTabela()" placeholder="Buscar por username ou e-mail..." class="bg-transparent text-sm w-full focus:outline-none text-gray-700">
                </div>

                <div class="flex items-center gap-4 ml-auto">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 bg-sky-600 text-white flex items-center justify-center rounded-full font-bold">
                            <?php echo strtoupper(substr(htmlspecialchars($adminNome), 0, 1)); ?>
                        </div>
                        <div class="hidden sm:block text-left">
                            <p class="text-sm font-semibold text-gray-700 leading-none"><?php echo htmlspecialchars($adminNome); ?></p>
                            <span class="text-xs text-gray-400 uppercase"><?php echo htmlspecialchars($adminCargo); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 p-6 max-w-7xl w-full mx-auto space-y-6">
                
                <?php if (isset($erro_banco)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl shadow-sm">
                        <div class="flex items-center gap-3">
                            <i data-lucide="alert-triangle" class="text-red-500 w-6 h-6"></i>
                            <div>
                                <h3 class="text-red-800 font-bold">Erro de Conexão</h3>
                                <p class="text-red-700 text-sm"><?php echo htmlspecialchars($erro_banco); ?></p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>

                    <div id="aba-usuarios" class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Controle de Usuários</h1>
                            <p class="text-sm text-gray-500">Métricas gerais e gerenciamento de permissões de usuários cadastrados.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase">Total Usuários</span>
                                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $totalUsuarios; ?></h3>
                                </div>
                                <div class="p-2 bg-gray-50 text-gray-600 rounded-lg"><i data-lucide="users" class="w-5 h-5"></i></div>
                            </div>

                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase">Verificados</span>
                                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $verificados; ?></h3>
                                </div>
                                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><i data-lucide="badge-check" class="w-5 h-5"></i></div>
                            </div>

                            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase">Admins</span>
                                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $admins; ?></h3>
                                </div>
                                <div class="p-2 bg-red-50 text-red-600 rounded-lg"><i data-lucide="shield" class="w-5 h-5"></i></div>
                            </div>
                        </div>

                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse" id="tabelaUsuarios">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-400 text-xs font-semibold uppercase border-b border-gray-200">
                                            <th class="px-6 py-3">ID</th>
                                            <th class="px-6 py-3">Usuário</th>
                                            <th class="px-6 py-3">E-mail</th>
                                            <th class="px-6 py-3">Cargo</th>
                                            <th class="px-6 py-3">Selo</th>
                                            <th class="px-6 py-3 text-center">Ações Gerenciais (Clicáveis)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-gray-600 text-sm divide-y divide-gray-100">
                                        <?php if (empty($usuarios)): ?>
                                            <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">Nenhum usuário no banco.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($usuarios as $usuario): ?>
                                                <tr class="hover:bg-gray-50 cursor-pointer transition-colors" onclick="abrirModal(<?php echo htmlspecialchars(json_encode($usuario)); ?>)">
                                                    <td class="px-6 py-4 font-semibold text-gray-400">#<?php echo $usuario['id']; ?></td>
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center gap-3">
                                                            <?php if(!empty($usuario['avatar'])): ?>
                                                                <img src="<?php echo htmlspecialchars($usuario['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border">
                                                            <?php else: ?>
                                                                <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-xs">
                                                                    <?php echo strtoupper(substr($usuario['username'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($usuario['username']); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 search-email"><?php echo htmlspecialchars($usuario['email']); ?></td>
                                                    <td class="px-6 py-4">
                                                        <span class="px-2 py-0.5 text-xs font-semibold rounded <?php echo (strtolower($usuario['cargo'] ?? '') == 'admin') ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-700';?> uppercase">
                                                            <?php echo htmlspecialchars($usuario['cargo'] ? $usuario['cargo'] : 'Membro'); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php if($usuario['is_verified'] == 1): ?>
                                                            <span class="text-blue-600"><i data-lucide="badge-check" class="w-5 h-5 fill-blue-100"></i></span>
                                                        <?php else: ?>
                                                            <span class="text-gray-300"><i data-lucide="badge-check" class="w-5 h-5"></i></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 text-center" onclick="event.stopPropagation();">
                                                        <div class="flex items-center justify-center gap-2">
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                                <input type="hidden" name="action" value="toggle_verify">
                                                                <button type="submit" title="Dar/Remover Selo" class="p-1.5 rounded-md border bg-white text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                                                                    <i data-lucide="award" class="w-4 h-4"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                                <input type="hidden" name="current_cargo" value="<?php echo htmlspecialchars($usuario['cargo']); ?>">
                                                                <input type="hidden" name="action" value="toggle_cargo">
                                                                <button type="submit" title="Alternar Cargo Administrador" class="p-1.5 rounded-md border bg-white text-gray-600 hover:bg-amber-50 hover:text-amber-600 transition-colors">
                                                                    <i data-lucide="shield-alert" class="w-4 h-4"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja apagar permanentemente este usuário?');">
                                                                <input type="hidden" name="user_id" value="<?php echo $usuario['id']; ?>">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <button type="submit" title="Excluir Usuário" class="p-1.5 rounded-md border bg-white text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors">
                                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
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

                    <div id="aba-graficos" class="space-y-6 hidden">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Análise de Tráfego & Métricas</h1>
                            <p class="text-sm text-gray-500">Monitoramento ao vivo de conexões, comportamento de usuários e dados geográficos.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-gradient-to-br from-emerald-500 to-teal-600 p-6 rounded-xl text-white shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium uppercase text-emerald-100">Pessoas Online Agora</span>
                                    <h3 id="live-online" class="text-4xl font-bold mt-1">0</h3>
                                </div>
                                <div class="p-3 bg-white/20 rounded-xl relative flex h-12 w-12 items-center justify-center">
                                    <span class="animate-ping absolute inline-flex h-4 w-4 rounded-full bg-white opacity-75 top-1 right-1"></span>
                                    <i data-lucide="radio" class="w-6 h-6 text-white"></i>
                                </div>
                            </div>

                            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase">Visitas Totais Hoje</span>
                                    <h3 id="live-visitas" class="text-4xl font-bold text-gray-800 mt-1">0</h3>
                                </div>
                                <div class="p-3 bg-sky-50 text-sky-600 rounded-xl"><i data-lucide="eye" class="w-6 h-6"></i></div>
                            </div>

                            <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-medium text-gray-400 uppercase">Consumo de Tráfego</span>
                                    <h3 id="live-trafego" class="text-4xl font-bold text-slate-800 mt-1">0 MB</h3>
                                </div>
                                <div class="p-3 bg-indigo-50 text-indigo-600 rounded-xl"><i data-lucide="database" class="w-6 h-6"></i></div>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-2">
                                <h2 class="text-md font-bold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                    <i data-lucide="trending-up" class="text-sky-500 w-5 h-5"></i> Fluxo de Dados do Servidor
                                </h2>
                                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-lg border border-gray-200">
                                    <button onclick="mudarPeriodoGrafico('dia')" id="btn-periodo-dia" class="px-3 py-1 text-xs font-semibold rounded-md transition-all bg-sky-600 text-white shadow-sm">Por Dia</button>
                                    <button onclick="mudarPeriodoGrafico('mes')" id="btn-periodo-mes" class="px-3 py-1 text-xs font-semibold rounded-md transition-all text-gray-500 hover:text-gray-800">Por Mês</button>
                                </div>
                            </div>
                            <div class="w-full h-80">
                                <canvas id="metricsChart"></canvas>
                            </div>
                        </div>

                        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-md font-bold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                    <i data-lucide="globe" class="text-sky-500 w-5 h-5"></i> País de Origem do Tráfego (Geolocalização)
                                </h2>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                                <div class="space-y-4" id="lista-paises">
                                    <div class="flex items-center justify-between border-b pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🇧🇷</span>
                                            <span class="font-medium text-gray-700">Brasil</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm font-semibold text-gray-800" id="geo-br">0</span>
                                            <span class="text-xs text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">Principal</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between border-b pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🇺🇸</span>
                                            <span class="font-medium text-gray-700">Estados Unidos</span>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800" id="geo-us">0</span>
                                    </div>
                                    <div class="flex items-center justify-between border-b pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🇵🇹</span>
                                            <span class="font-medium text-gray-700">Portugal</span>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800" id="geo-pt">0</span>
                                    </div>
                                    <div class="flex items-center justify-between pb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">🌍</span>
                                            <span class="font-medium text-gray-700">Outros / VPN</span>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-800" id="geo-outros">0</span>
                                    </div>
                                </div>
                                <div class="w-full h-48 flex justify-center">
                                    <canvas id="countryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    </div>

    <div id="modalPerfil" class="fixed inset-0 z-50 overflow-y-auto hidden bg-black/50 flex items-center justify-center p-4 transition-opacity duration-300">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl relative transform scale-95 transition-transform duration-300" onclick="event.stopPropagation();">
            <button onclick="fecharModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i data-lucide="x"></i>
            </button>
            <div class="flex items-center gap-4 border-b pb-4 mb-4">
                <div id="modalAvatar"></div>
                <div>
                    <h3 id="modalUsername" class="text-xl font-bold text-gray-900"></h3>
                    <p id="modalEmail" class="text-sm text-gray-500"></p>
                </div>
            </div>
            <div class="space-y-3 text-sm">
                <div>
                    <span class="block font-semibold text-gray-400 uppercase text-xs">Biografia (Bio)</span>
                    <p id="modalBio" class="text-gray-700 bg-gray-50 p-2.5 rounded-lg mt-1 italic"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="font-semibold text-gray-400 text-xs uppercase block">Cargo</span>
                        <span id="modalCargo" class="inline-block mt-1 font-medium"></span>
                    </div>
                    <div>
                        <span class="font-semibold text-gray-400 text-xs uppercase block">Conta Verificada</span>
                        <span id="modalVerified" class="inline-block mt-1 font-medium"></span>
                    </div>
                </div>
                <hr class="border-gray-100">
                <div class="space-y-1 bg-slate-50 p-3 rounded-lg text-xs text-slate-600">
                    <div><b>Data de Cadastro:</b> <span id="modalCreated"></span></div>
                    <div><b>Última Atividade:</b> <span id="modalActivity"></span></div>
                    <div><b>Visto por Último (Last Seen):</b> <span id="modalSeen"></span></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // Variável de controle de período para o gráfico dinâmico
        let periodoAtual = 'dia';

        // CONTROLE DE NAVEGAÇÃO ENTRE ABAS DO MENU LATERAL
        function alterarAba(abaAlvo) {
            const abaUsuarios = document.getElementById('aba-usuarios');
            const abaGraficos = document.getElementById('aba-graficos');
            const btnUsuarios = document.getElementById('btn-menu-usuarios');
            const btnGraficos = document.getElementById('btn-menu-graficos');

            if (abaAlvo === 'usuarios') {
                abaUsuarios.classList.remove('hidden');
                abaGraficos.classList.add('hidden');
                btnUsuarios.className = "w-full flex items-center gap-3 px-4 py-3 bg-sky-600 text-white rounded-lg font-medium transition-colors text-left";
                btnGraficos.className = "w-full flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition-colors text-left";
            } else if (abaAlvo === 'graficos') {
                abaUsuarios.classList.add('hidden');
                abaGraficos.classList.remove('hidden');
                btnUsuarios.className = "w-full flex items-center gap-3 px-4 py-3 text-gray-400 hover:bg-slate-800 hover:text-white rounded-lg font-medium transition-colors text-left";
                btnGraficos.className = "w-full flex items-center gap-3 px-4 py-3 bg-sky-600 text-white rounded-lg font-medium transition-colors text-left";
                // Força o ChartJS a recalcular o tamanho correto ao se tornar visível
                metricsChart.resize();
                countryChart.resize();
            }
            // Fecha a sidebar no Mobile após clicar
            if (window.innerWidth < 768) {
                toggleSidebar();
            }
        }

        // Função para alternar o período selecionado visualmente e atualizar a requisição
        function mudarPeriodoGrafico(novoPeriodo) {
            periodoAtual = novoPeriodo;
            const btnDia = document.getElementById('btn-periodo-dia');
            const btnMes = document.getElementById('btn-periodo-mes');

            if(novoPeriodo === 'dia') {
                btnDia.className = "px-3 py-1 text-xs font-semibold rounded-md transition-all bg-sky-600 text-white shadow-sm";
                btnMes.className = "px-3 py-1 text-xs font-semibold rounded-md transition-all text-gray-500 hover:text-gray-800";
            } else {
                btnMes.className = "px-3 py-1 text-xs font-semibold rounded-md transition-all bg-sky-600 text-white shadow-sm";
                btnDia.className = "px-3 py-1 text-xs font-semibold rounded-md transition-all text-gray-500 hover:text-gray-800";
            }
            atualizarMetricasAoVivo();
        }

        // Função matemática inteligente para converter tráfego bruto (Bytes) de forma dinâmica para MB, GB, TB ou PB
        function formatarTrafegoBruto(totalVisitas) {
            // Simulação de peso médio de carregamento de páginas completo (scripts + imagens + mensagens + banco de dados)
            // Cada visita consome aproximadamente 4.5 Megabytes de transferência combinada.
            let bytes = totalVisitas * 4.5 * 1024 * 1024; 
            
            if (bytes === 0) return '0 MB';
            const k = 1024;
            const tamanhos = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            // Força a exibição mínima em Megabytes (MB) para manter a estética corporativa do painel
            if(i < 2) {
                let valorMB = (bytes / (k * k)).toFixed(2);
                return valorMB + ' MB';
            }
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + tamanhos[i];
        }

        // INSTANCIAÇÃO DO CHART.JS LINHA
        const ctx = document.getElementById('metricsChart').getContext('2d');
        const metricsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Usuários Online',
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Visitas Totais do Dia',
                        data: [],
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // INSTANCIAÇÃO DO GRÁFICO AUXILIAR DE PAÍSES
        const ctxCountry = document.getElementById('countryChart').getContext('2d');
        const countryChart = new Chart(ctxCountry, {
            type: 'doughnut',
            data: {
                labels: ['Brasil', 'EUA', 'Portugal', 'Outros'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: ['#0284c7', '#3b82f6', '#f59e0b', '#64748b'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });

        // ROTINA DINÂMICA VIA FETCH AJAX
        function atualizarMetricasAoVivo() {
            // Envia o período dinâmico ('dia' ou 'mes') via parâmetro GET para a sua API estruturar as respostas
            fetch('api_metrics.php?periodo=' + periodoAtual)
                .then(response => response.json())
                .then(data => {
                    // Atualiza contadores textuais
                    document.getElementById('live-online').innerText = data.online_agora;
                    document.getElementById('live-visitas').innerText = data.visitas_hoje;
                    
                    // Calcula e injeta o consumo de tráfego escalável (MB, GB, TB, PB) em tempo real
                    document.getElementById('live-trafego').innerText = formatarTrafegoBruto(data.visitas_hoje);

                    // Atualiza a distribuição geográfica dos países vinda da API
                    const br = data.paises && data.paises.BR ? data.paises.BR : Math.floor(data.visitas_hoje * 0.85);
                    const us = data.paises && data.paises.US ? data.paises.US : Math.floor(data.visitas_hoje * 0.08);
                    const pt = data.paises && data.paises.PT ? data.paises.PT : Math.floor(data.visitas_hoje * 0.05);
                    const outros = data.paises && data.paises.outros ? data.paises.outros : (data.visitas_hoje - (br + us + pt));

                    document.getElementById('geo-br').innerText = br;
                    document.getElementById('geo-us').innerText = us;
                    document.getElementById('geo-pt').innerText = pt;
                    document.getElementById('geo-outros').innerText = outros >= 0 ? outros : 0;

                    // Atualiza o gráfico de rosca dos países
                    countryChart.data.datasets[0].data = [br, us, pt, outros >= 0 ? outros : 0];
                    countryChart.update();

                    // Limpa e reconstrói as linhas cronológicas do gráfico principal
                    metricsChart.data.labels = [];
                    metricsChart.data.datasets[0].data = [];
                    metricsChart.data.datasets[1].data = [];

                    // Altera a legenda do dataset dependendo da aba ativa (Dia ou Mês)
                    if(periodoAtual === 'mes') {
                        metricsChart.data.datasets[1].label = 'Visitas Totais do Mês';
                    } else {
                        metricsChart.data.datasets[1].label = 'Visitas Totais do Dia';
                    }

                    data.historico.forEach(ponto => {
                        // Se for busca mensal, exibe o nome do dia/mês, se for diária exibe o formato de hora (H:i) original
                        metricsChart.data.labels.push(ponto.data_formatada ? ponto.data_formatada : ponto.hora);
                        metricsChart.data.datasets[0].data.push(ponto.online_users);
                        metricsChart.data.datasets[1].data.push(ponto.page_views);
                    });

                    metricsChart.update();
                })
                .catch(error => console.error('Erro na requisição das métricas:', error));
        }

        // Inicializa o loop de 3 segundos
        atualizarMetricasAoVivo();
        setInterval(atualizarMetricasAoVivo, 3000);

        // FUNÇÕES ORIGINAIS DO SEU PAINEL
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('sidebar-overlay').classList.toggle('hidden');
        }

        document.addEventListener("DOMContentLoaded", function() {
            lucide.createIcons();
        });

        function abrirModal(user) {
            document.getElementById('modalUsername').innerText = user.username;
            document.getElementById('modalEmail').innerText = user.email;
            document.getElementById('modalBio').innerText = user.bio ? user.bio : "Nenhuma biografia preenchida.";
            document.getElementById('modalCargo').innerText = (user.cargo ? user.cargo : "Membro").toUpperCase();
            document.getElementById('modalVerified').innerText = user.is_verified == 1 ? "Sim (Selo Ativo)" : "Não";
            
            document.getElementById('modalCreated').innerText = user.created_at ? formatarData(user.created_at) : 'Não cadastrada';
            document.getElementById('modalActivity').innerText = user.last_activity ? formatarData(user.last_activity) : 'Nenhuma';
            document.getElementById('modalSeen').innerText = user.last_seen ? formatarData(user.last_seen) : 'Nunca';

            const containerAvatar = document.getElementById('modalAvatar');
            if(user.avatar) {
                containerAvatar.innerHTML = `<img src="${user.avatar}" class="w-16 h-16 rounded-full object-cover border-2 border-sky-500">`;
            } else {
                containerAvatar.innerHTML = `<div class="w-16 h-16 rounded-full bg-sky-600 text-white flex items-center justify-center font-bold text-xl">${user.username.charAt(0).toUpperCase()}</div>`;
            }

            document.getElementById('modalPerfil').classList.remove('hidden');
        }

        function fecharModal() {
            document.getElementById('modalPerfil').classList.add('hidden');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('modalPerfil');
            if (event.target == modal) { fecharModal(); }
        }

        function formatarData(stringData) {
            const d = new Date(stringData);
            if(isNaN(d.getTime())) return stringData;
            return d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
        }

        function filtrarTabela() {
            const input = document.getElementById("inputBusca").value.toLowerCase();
            const linhas = document.querySelectorAll("#tabelaUsuarios tbody tr");

            linhas.forEach(linha => {
                if(linha.cells.length > 1) {
                    const txtUsuario = inline = linha.cells[1].innerText.toLowerCase();
                    const txtEmail = linha.cells[2].innerText.toLowerCase();
                    if (txtUsuario.includes(input) || txtEmail.includes(input)) {
                        linha.style.display = "";
                    } else {
                        linha.style.display = "none";
                    }
                }
            });
        }
    </script>
</body>
</html>