<?php
// admin/telemetria.php

// ---------------------------------------------------------
// CONSTANTES DE CONEXÃO DO SEU TELEGRAM CLONE
// ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'telegram_clone');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // Conexão via PDO estável e persistente
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 1. CONSULTAS DE ESTADO EM TEMPO REAL (DO SEU BANCO DE DADOS)
    // Puxa a soma real de mensagens enviadas por todos os usuários
    $totalMensagensReais = $pdo->query("SELECT SUM(total_messages) FROM users")->fetchColumn() ?? 0;
    
    // Puxa o total real de usuários cadastrados no sistema
    $totalUsuariosReais = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;

    // Puxa o total real de contas banidas
    $totalBanidosReais = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn() ?? 0;

    // 2. ENDPOINT DE API REAL PARA O POLLED GRAPH (SEM NÚMEROS INVENTADOS)
    if (isset($_GET['action']) && $_GET['action'] === 'get_live_metrics') {
        header('Content-Type: application/json');
        
        // Retorna estritamente o valor exato contido na sua tabela no exato segundo da requisição
        echo json_encode([
            'total_mensagens' => (int)$totalMensagensReais,
            'total_usuarios'  => (int)$totalUsuariosReais,
            'timestamp'       => date('H:i:s')
        ]);
        exit;
    }

} catch (PDOException $e) {
    $erroBancoDeDados = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemetria Real-Time - Ultimate Core</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <span>Erro Crítico de Telemetria: <?php echo htmlspecialchars($erroBancoDeDados); ?></span>
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
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Telemetria Ativa
                    </span>
                </div>
            </div>
            
            <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200/60">
                <a href="index.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition text-slate-500 hover:text-slate-800 flex items-center gap-2">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i> Comunidade
                </a>
                <a href="telemetria.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition bg-white text-slate-800 shadow-sm flex items-center gap-2">
                    <i data-lucide="activity" class="w-3.5 h-3.5"></i> Telemetria
                </a>
                <a href="index.php" class="px-4 py-1.5 text-xs font-bold rounded-lg custom-transition text-slate-500 hover:text-slate-800 flex items-center gap-2">
                    <i data-lucide="settings" class="w-3.5 h-3.5"></i> Ajustes Globais
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 pb-3">
            <div>
                <h1 class="text-xl font-black text-slate-900 tracking-tight">Telemetria &amp; Estatísticas</h1>
                <p class="text-xs text-slate-500 mt-0.5">Métricas de transações de dados extraídas diretamente da base de dados local.</p>
            </div>
            <div class="flex items-center gap-2 bg-slate-200/60 text-slate-600 font-mono text-[10px] px-2.5 py-1 rounded-md border border-slate-300/40 w-fit">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> 
                Sincronizado: <span id="clock-live">--:--:--</span>
            </div>
        </div>

        <div id="conteudo-modulo-telemetria" class="space-y-6">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-slate-900 p-5 rounded-2xl text-white flex items-center justify-between relative overflow-hidden shadow-md">
                    <div class="space-y-0.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Contas de Usuários Registradas</span>
                        <h3 id="live-usuarios" class="text-3xl font-black tracking-tight text-white"><?php echo $totalUsuariosReais; ?></h3>
                    </div>
                    <div class="p-3 bg-slate-800 text-emerald-400 rounded-xl">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
                    <div class="space-y-0.5">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Volume Total de Mensagens (Métricas de Tráfego)</span>
                        <h3 id="live-mensagens" class="text-3xl font-black text-slate-800 tracking-tight"><?php echo number_format($totalMensagensReais, 0, ',', '.'); ?></h3>
                    </div>
                    <div class="p-3 bg-sky-50 text-sky-600 rounded-xl border border-sky-100"><i data-lucide="message-square" class="w-5 h-5"></i></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm md:col-span-2 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Monitor Real do Fluxo de Mensagens (Variação Ativa)</h4>
                        <span class="text-[9px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-extrabold tracking-wide uppercase">Banco de Dados Ativo</span>
                    </div>
                    <div class="h-48 w-full">
                        <canvas id="chartAtividade"></canvas>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 border-b border-slate-100 pb-2">Integridade do Servidor</h4>
                    <div class="space-y-3 pt-1">
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-600">Usuários Restritos</span>
                            <span class="text-sm font-black text-rose-600 bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-lg"><?php echo $totalBanidosReais; ?></span>
                        </div>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60 flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-600">Conexão Local DB</span>
                            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-lg uppercase tracking-wider">Estável</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function mudarTemaUI(classeTema) {
            document.body.className = "bg-slate-50 text-slate-800 font-sans antialiased " + classeTema;
            localStorage.setItem('painel-ui-theme', classeTema);
        }
        const temaSalvo = localStorage.getItem('painel-ui-theme');
        if(temaSalvo) mudarTemaUI(temaSalvo);
        
        lucide.createIcons();

        // Inicialização do fluxo assíncrono do gráfico
        (function() {
            const ctx = document.getElementById('chartAtividade');
            if(!ctx) return;

            const chartReal = new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [], // Populado em tempo de execução
                    datasets: [{
                        label: 'Histórico de Mensagens',
                        data: [],
                        borderColor: 'rgb(14, 165, 233)',
                        backgroundColor: 'rgba(14, 165, 233, 0.04)',
                        tension: 0.2,
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 2
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    }
                }
            });

            // Função que requisita dados reais ao próprio script sem recarregar a tela
            function puxarDadosDoServidor() {
                fetch('telemetria.php?action=get_live_metrics')
                    .then(response => response.json())
                    .then(data => {
                        // Atualiza os contadores na tela com os números brutos vindos do SQL
                        document.getElementById('live-usuarios').innerText = data.total_usuarios;
                        document.getElementById('live-mensagens').innerText = data.total_mensagens.toLocaleString('pt-BR');
                        document.getElementById('clock-live').innerText = data.timestamp;

                        // Mantém apenas os últimos 10 registros na timeline gráfica para perfeita visualização
                        if (chartReal.data.labels.length >= 10) {
                            chartReal.data.labels.shift();
                            chartReal.data.datasets[0].data.shift();
                        }

                        // Alimenta o gráfico diretamente com os dados estruturais reais
                        chartReal.data.labels.push(data.timestamp);
                        chartReal.data.datasets[0].data.push(data.total_mensagens);
                        chartReal.update();
                    })
                    .catch(error => console.error("Erro na leitura de telemetria ativa do MySQL:", error));
            }

            // Executa imediatamente ao carregar e monitora a cada 3 segundos
            puxarDadosDoServidor();
            setInterval(puxarDadosDoServidor, 3000);
        })();
    </script>
</body>
</html>