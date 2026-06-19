<?php
// O seu confi.php já inicia a sessão de forma segura, então apenas o incluímos
if (file_exists('confi.php')) {
    require_once 'confi.php';
} else {
    die("Erro: O arquivo confi.php não foi encontrado.");
}

// Verifica se o usuário está logado de acordo com o seu sistema de login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id_logado = $_SESSION['user_id'];

// Inicializa variáveis padrão de cargo e identificação
$cargo_usuario = 'usuarios'; 
$nome_usuario_logado = 'Usuário';

// Puxa com precisão os dados do usuário logado na tabela 'users' utilizando 'username'
try {
    $stmt_cargo = $pdo->prepare("SELECT cargo, username FROM users WHERE id = ?");
    $stmt_cargo->execute([$user_id_logado]);
    $dados_usuario = $stmt_cargo->fetch();
    
    if ($dados_usuario) {
        if (!empty($dados_usuario['cargo'])) {
            $cargo_usuario = strtolower(trim($dados_usuario['cargo']));
        }
        if (!empty($dados_usuario['username'])) {
            $nome_usuario_logado = $dados_usuario['username'];
        }
    }
} catch (PDOException $e) {
    // PROTEÇÃO: Se houver falha na estrutura, assume o padrão seguro
    $cargo_usuario = 'usuarios';
    
    try {
        $stmt_nome = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_nome->execute([$user_id_logado]);
        $dados_nome = $stmt_nome->fetch();
        if ($dados_nome && !empty($dados_nome['username'])) { 
            $nome_usuario_logado = $dados_nome['username']; 
        }
    } catch (PDOException $err) {
        // Ignora falhas menores silenciosamente
    }
}

// Configura os limites baseados no cargo retornado do banco
switch ($cargo_usuario) {
    case 'vip': 
        $limite_membros = 100; 
        $nome_cargo_formatado = "VIP"; 
        break;
    case 'empresas': 
        $limite_membros = 1000000; 
        $nome_cargo_formatado = "Empresa"; 
        break;
    case 'admin': 
        $limite_membros = INF; 
        $nome_cargo_formatado = "Admin"; 
        break;
    case 'usuarios':
    default: 
        $limite_membros = 50; 
        $nome_cargo_formatado = "Usuário"; 
        break;
}

$erro = "";

// Processar a criação do grupo usando PDO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['criar_grupo'])) {
    $nome_grupo = trim($_POST['nome_grupo']);
    $amigos_selecionados = isset($_POST['amigos']) ? $_POST['amigos'] : [];
    $total_selecionado = count($amigos_selecionados);

    if (empty($nome_grupo)) {
        $erro = "Por favor, escolha um nome marcante para o grupo.";
    } elseif ($total_selecionado > $limite_membros) {
        $erro = "Seu plano ($nome_cargo_formatado) permite adicionar no máximo $limite_membros pessoas. Você selecionou $total_selecionado.";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Inserir o Grupo
            $stmt = $pdo->prepare("INSERT INTO grupos (nome, criador_id) VALUES (?, ?)");
            $stmt->execute([$nome_grupo, $user_id_logado]);
            $grupo_id = $pdo->lastInsertId();

            // 2. Adicionar o Criador como membro
            $stmt_membro = $pdo->prepare("INSERT INTO grupo_membros (grupo_id, user_id) VALUES (?, ?)");
            $stmt_membro->execute([$grupo_id, $user_id_logado]);

            // 3. Adicionar os Amigos selecionados
            foreach ($amigos_selecionados as $amigo_id) {
                $stmt_membro->execute([$grupo_id, $amigo_id]);
            }

            $pdo->commit();

            header("Location: grupo.php?id=" . $grupo_id);
            exit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = "Erro ao criar grupo. Certifique-se de ter criado as tabelas 'grupos' e 'grupo_membros'.";
        }
    }
}

// Buscar lista de amigos vinculados utilizando o PDO - RESOLVIDO: GROUP BY u.id remove duplicidades da lista (Adicionado u.is_verified)
$amigos_lista = [];
try {
    $query_friends = "
        SELECT u.id, u.username AS nome, u.is_verified FROM friends f 
        JOIN users u ON (f.friend_id = u.id OR f.user_id = u.id) 
        WHERE (f.user_id = ? OR f.friend_id = ?) AND u.id != ?
        GROUP BY u.id
    ";
    $stmt_f = $pdo->prepare($query_friends);
    $stmt_f->execute([$user_id_logado, $user_id_logado, $user_id_logado]);
    $amigos_lista = $stmt_f->fetchAll();
} catch (PDOException $e) {
    try {
        $stmt_b = $pdo->prepare("SELECT id, username AS nome, is_verified FROM users WHERE id != ? GROUP BY id LIMIT 100");
        $stmt_b->execute([$user_id_logado]);
        $amigos_lista = $stmt_b->fetchAll();
    } catch (PDOException $err) {
        $amigos_lista = [];
    }
}

// Função auxiliar para gerar avatares gradientes estilo Telegram Premium
function obterCorAvatar($string) {
    $hash = md5($string);
    $cores = [
        ['#ff416c', '#ff4b2b'],
        ['#00b4db', '#0083b0'],
        ['#11998e', '#38ef7d'],
        ['#7f00ff', '#e100ff'],
        ['#b92b27', '#1565c0']
    ];
    $indice = hexdec(substr($hash, 0, 2)) % count($cores);
    return $cores[$indice];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Criar Grupo — Telegram Dark</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; }
        
        body { background-color: #182533; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; color: #f5f5f5; }
        
        .app-container { width: 100%; max-width: 480px; height: 100vh; background: #17212b; display: flex; flex-direction: column; box-shadow: 0 12px 40px rgba(0,0,0,0.5); position: relative; }
        
        .header { background: #243447; color: white; padding: 22px 20px; display: flex; flex-direction: column; gap: 6px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .header-top { display: flex; justify-content: space-between; align-items: center; }
        .header h2 { margin: 0; font-size: 20px; font-weight: 600; color: #ffffff; }
        .user-welcome { font-size: 13px; color: #7f91a4; }
        .user-welcome b { color: #5288c1; }
        
        .badge { padding: 4px 10px; background-color: rgba(82, 136, 193, 0.25); border: 1px solid #5288c1; color: #6499d3; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .content { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; background: #17212b; }
        
        .alert { background-color: rgba(229, 62, 62, 0.1); color: #fc8181; padding: 14px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #e53e3e; }
        
        .input-wrapper { display: flex; flex-direction: column; margin-bottom: 24px; }
        label { font-size: 12px; color: #5288c1; font-weight: 600; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .input-group-name { width: 100%; padding: 16px; font-size: 16px; border: 1px solid #243447; border-radius: 12px; outline: none; transition: all 0.2s ease; background: #182533; color: #ffffff; }
        .input-group-name:focus { border-color: #5288c1; box-shadow: 0 0 0 3px rgba(82, 136, 193, 0.2); }
        
        .section-title { font-size: 13px; color: #7f91a4; font-weight: 700; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; text-transform: uppercase; }
        .limit-counter { background: #243447; color: #f5f5f5; padding: 3px 9px; border-radius: 10px; font-size: 11px; border: 1px solid rgba(255,255,255,0.05); }
        
        .friend-list { flex: 1; border: 1px solid #243447; border-radius: 14px; overflow-y: auto; background: #182533; padding: 6px; }
        .friend-item { display: flex; align-items: center; padding: 12px; border-radius: 10px; margin-bottom: 4px; cursor: pointer; transition: all 0.2s ease; }
        .friend-item:hover { background-color: #243447; }
        .friend-item.selected { background-color: rgba(82, 136, 193, 0.15); }
        
        .avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 15px; margin-right: 14px; text-transform: uppercase; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        
        /* Ajuste do container do nome para exibir o verificado lado a lado */
        .friend-name { font-size: 15px; color: #ffffff; font-weight: 500; flex: 1; display: flex; align-items: center; gap: 5px; }
        
        /* Estilização do selo de verificado */
        .verified-badge { color: #4f9fe3; font-size: 14px; }
        
        .checkbox-container { width: 22px; height: 22px; border: 2px solid #5288c1; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; background: transparent; pointer-events: none; }
        .friend-item input[type="checkbox"] { display: none; }
        
        .friend-item.selected .checkbox-container { background-color: #5288c1; border-color: #5288c1; animation: pop 0.25s ease; }
        .friend-item.selected .checkbox-container::after { content: '✓'; color: white; font-size: 12px; font-weight: bold; }
        
        .footer-btn { padding: 18px; background: #243447; border-top: 1px solid rgba(255,255,255,0.05); }
        .btn-submit { width: 100%; background: #5288c1; color: white; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.2); text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-submit:hover { background: #6499d3; transform: translateY(-1px); }
        
        @keyframes pop {
            0% { transform: scale(0.8); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        @media(min-width: 481px) {
            .app-container { height: 85vh; border-radius: 16px; overflow: hidden; }
        }
    </style>
</head>
<body>

<div class="app-container">
    <div class="header">
        <div class="header-top">
            <h2>Criar Grupo</h2>
            <div class="badge"><?php echo $nome_cargo_formatado; ?></div>
        </div>
        <div class="user-welcome">Olá, <b><?php echo htmlspecialchars($nome_usuario_logado); ?></b>! Monte o chat de sua comunidade.</div>
    </div>

    <div class="content">
        <?php if (!empty($erro)): ?>
            <div class="alert"><?php echo $erro; ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="grupoForm" style="display: flex; flex-direction: column; flex: 1;">
            <div class="input-wrapper">
                <label for="nome_grupo">Nome do Grupo</label>
                <input type="text" id="nome_grupo" name="nome_grupo" class="input-group-name" placeholder="Digite o nome do grupo..." autocomplete="off" required>
            </div>

            <div class="section-title">
                <span>Adicionar Integrantes</span>
                <span class="limit-counter">Limite: <?php echo ($limite_membros === INF) ? 'Ilimitado' : $limite_membros; ?></span>
            </div>
            
            <div class="friend-list">
                <?php if (count($amigos_lista) > 0): ?>
                    <?php foreach ($amigos_lista as $amigo): 
                        $iniciais = mb_substr($amigo['nome'], 0, 1);
                        $gradiente = obterCorAvatar($amigo['nome']);
                    ?>
                        <div class="friend-item" id="item_<?php echo $amigo['id']; ?>" onclick="alternarSelecao('<?php echo $amigo['id']; ?>')">
                            
                            <input type="checkbox" name="amigos[]" value="<?php echo $amigo['id']; ?>" id="amigo_<?php echo $amigo['id']; ?>">
                            
                            <div class="avatar" style="background: linear-gradient(135deg, <?php echo $gradiente[0]; ?>, <?php echo $gradiente[1]; ?>)">
                                <?php echo htmlspecialchars($iniciais); ?>
                            </div>
                            
                            <div class="friend-name">
                                <?php echo htmlspecialchars($amigo['nome']); ?>
                                <?php if (isset($amigo['is_verified']) && $amigo['is_verified'] == 1): ?>
                                    <i class="fa-solid fa-circle-check verified-badge"></i>
                                <?php endif; ?>
                            </div>
                            
                            <div class="checkbox-container" id="box_<?php echo $amigo['id']; ?>"></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #7f91a4; margin-top: 40px; font-size: 14px; padding: 0 10px;">
                        Nenhum contato disponível para adicionar.
                    </p>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="footer-btn">
        <button type="submit" form="grupoForm" name="criar_grupo" class="btn-submit">Criar Grupo</button>
    </div>
</div>

<script>
    function alternarSelecao(id) {
        const checkbox = document.getElementById('amigo_' + id);
        const item = document.getElementById('item_' + id);
        
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
    }
</script>

</body>
</html>