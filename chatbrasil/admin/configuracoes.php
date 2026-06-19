<?php
// admin/configuracoes.php

// ---------------------------------------------------------
// CONSTANTES DE CONEXÃO DO SEU TELEGRAM CLONE
// ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_clone');
define('DB_USER', 'root');
define('DB_PASS', '');

$pedidosVerificacao = [];

try {
    // Conexão estável via PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // ---------------------------------------------------------
    // PROCESSAMENTO DAS SOLICITAÇÕES DE SELO (APROVAR / RECUSAR)
    // ---------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
        $action = $_POST['action'];

        if ($userId > 0) {
            if ($action === 'aprovar_verificado') {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, solicitou_verificacao = 0 WHERE id = ?");
                $stmt->execute([$userId]);
            } elseif ($action === 'recusar_verificado') {
                $stmt = $pdo->prepare("UPDATE users SET solicitou_verificacao = 0, motivo_solicitacao = NULL WHERE id = ?");
                $stmt->execute([$userId]);
            }
        }
        
        // Recarrega a página de forma limpa para atualizar o estado
        header("Location: configuracoes.php");
        exit;
    }

    // Busca os pedidos reais de verificação direto do banco de dados
    $stmtPedidos = $pdo->query("SELECT id AS user_id, username, motivo_solicitacao FROM users WHERE solicitou_verificacao = 1 ORDER BY id ASC");
    $pedidosVerificacao = $stmtPedidos->fetchAll();

} catch (PDOException $e) {
    $erroBancoDeDados = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes Globais - Ultimate Core</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .custom-transition { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .theme-sky { --main-color: 14, 165, 233; }
        .theme-emerald { --main-color: 16, 185, 129; }
        .theme-indigo { --main-color: 99, 102, 241; }
        .theme-slate { --main-color: 71, 85, 105; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans theme-sky antialiased">

    <?php if (isset($erroBancoDeDados)): ?>
        <div class="bg-rose-50 border-b border-rose-200 text-rose-800 p-4 text-xs font-semibold shadow-inner flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i data-lucide="database" class="w-4 h-4 text-rose-600"></i>
                <span>Falha no Módulo de Configurações: <?php echo htmlspecialchars($erroBancoDeDados); ?></span>
            </div>
            <span class="bg-rose-600 text-white px-2 py-0.5 rounded text-[10px]">VERIFICAR MYSQL</span>
        </div>
    <?php endif; ?>

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 px-6 py-3 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-sky-500 text-white rounded-xl flex items-center justify-center font-black shadow-md shadow-sky-500/20">TG</div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 leading-tight">Terminal de Controle</h2>
                    <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Sistema Online
                    </span>
                </div>
            </div>
            
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200/60">
                <a href="index.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition text-slate-500 hover:text-slate-800 flex items-center gap-2">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i> Comunidade
                </a>
                <a href="telemetria.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition text-slate-500 hover:text-slate-800 flex items-center gap-2">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i> Telemetria
                </a>
                <a href="configuracoes.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition bg-white text-slate-800 shadow-sm flex items-center gap-2">
                    <i data-lucide="settings" class="w-3.5 h-3.5"></i> Ajustes Globais
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <div class="border-b border-slate-200 pb-3">
            <h1 class="text-xl font-black text-slate-900 tracking-tight">Ajustes Globais e Preferências</h1>
            <p class="text-xs text-slate-500 mt-0.5">Triagem de selos oficiais solicitados por usuários e customização visual da interface.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <div class="p-1.5 bg-slate-100 rounded-lg text-slate-700"><i data-lucide="palette" class="w-4 h-4"></i></div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Aparência do Painel</h3>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">Selecione a paleta de realce desejada para o seu terminal de gerenciamento.</p>
                <div class="grid grid-cols-1 gap-2 pt-1">
                    <button onclick="mudarTemaUI('theme-sky')" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 font-bold text-xs custom-transition">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-sky-500"></span> Padrão Sky</div>
                    </button>
                    <button onclick="mudarTemaUI('theme-emerald')" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 font-bold text-xs custom-transition">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Esmeralda</div>
                    </button>
                    <button onclick="mudarTemaUI('theme-indigo')" class="flex items-center justify-between p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 font-bold text-xs custom-transition">
                        <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span> Índigo</div>
                    </button>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4 md:col-span-2">
                <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                    <div class="p-1.5 bg-sky-50 text-sky-600 rounded-lg border border-sky-100"><i data-lucide="award" class="w-4 h-4"></i></div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pedidos de Verificado Pendentes</h3>
                </div>
                <div class="space-y-2 max-h-[300px] overflow-y-auto pr-1">
                    <?php if(empty($pedidosVerificacao)): ?>
                        <div class="text-center py-12 bg-slate-50/50 rounded-xl border border-dashed border-slate-200 text-slate-400 font-medium text-[11px]">
                            Nenhuma solicitação de selo pendente no banco de dados.
                        </div>
                    <?php else: ?>
                        <?php foreach($pedidosVerificacao as $pedido): ?>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/70 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-slate-900 text-white flex items-center justify-center font-bold text-xs flex-shrink-0">
                                        <?php echo strtoupper(substr($pedido['username'], 0, 1)); ?>
                                    </div>
                                    <div class="truncate">
                                        <span class="block text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($pedido['username']); ?></span>
                                        <?php if(!empty($pedido['motivo_solicitacao'])): ?>
                                            <span class="block text-[10px] text-slate-500 italic truncate">"<?php echo htmlspecialchars($pedido['motivo_solicitacao']); ?>"</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <form method="POST" class="inline m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $pedido['user_id']; ?>">
                                        <input type="hidden" name="action" value="aprovar_verificado">
                                        <button type="submit" class="p-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 custom-transition shadow-sm">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline m-0" onsubmit="return confirm('Deseja rejeitar este pedido?');">
                                        <input type="hidden" name="user_id" value="<?php echo $pedido['user_id']; ?>">
                                        <input type="hidden" name="action" value="recusar_verificado">
                                        <button type="submit" class="p-1.5 rounded-lg bg-rose-600 text-white hover:bg-rose-700 custom-transition shadow-sm">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Sincroniza e salva a paleta de cores globalmente via LocalStorage
        function mudarTemaUI(classeTema) {
            document.body.className = "bg-slate-50 text-slate-800 font-sans antialiased " + classeTema;
            localStorage.setItem('painel-ui-theme', classeTema);
        }

        const temaSalvo = localStorage.getItem('painel-ui-theme');
        if(temaSalvo) mudarTemaUI(temaSalvo);
        
        // Renderiza os ícones Lucide nativamente
        lucide.createIcons();
    </script>
</body>
</html>