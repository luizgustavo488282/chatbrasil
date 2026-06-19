<?php
// Inclui a configuração do banco de dados
if (file_exists('confi.php')) {
    require_once 'confi.php';
} else {
    die("Erro: O arquivo confi.php não foi encontrado.");
}

// Opcional: Descomente as linhas abaixo se quiser que apenas ADMINS acessem esta página
/*
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$stmt_check = $pdo->prepare("SELECT cargo FROM users WHERE id = ?");
$stmt_check->execute([$_SESSION['user_id']]);
$user_atual = $stmt_check->fetch();
if (!$user_atual || strtolower($user_atual['cargo']) !== 'admin') {
    die("Acesso negado. Apenas administradores podem acessar esta página.");
}
*/

$mensagem = "";

// Processa a atualização do cargo enviada via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_cargo'])) {
    $user_id = intval($_POST['user_id']);
    $novo_cargo = trim($_POST['novo_cargo']);
    
    // Lista de cargos permitidos para validação de segurança
    $cargos_permitidos = ['usuarios', 'vip', 'empresas', 'admin'];
    
    if (in_array($novo_cargo, $cargos_permitidos)) {
        try {
            $stmt_update = $pdo->prepare("UPDATE users SET cargo = ? WHERE id = ?");
            $stmt_update->execute([$novo_cargo, $user_id]);
            $mensagem = "Cargo atualizado com sucesso!";
        } catch (PDOException $e) {
            $mensagem = "Erro ao atualizar cargo: " . $e->getMessage();
        }
    }
}

// Busca todos os usuários cadastrados
try {
    $stmt_users = $pdo->query("SELECT id, username, cargo FROM users ORDER BY username ASC");
    $usuarios = $stmt_users->fetchAll();
} catch (PDOException $e) {
    die("Erro ao buscar usuários: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Cargos — Painel Admin</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: #182533; color: #f5f5f5; margin: 0; padding: 20px; display: flex; justify-content: center; min-height: 100vh; }
        .container { width: 100%; max-width: 600px; background: #17212b; padding: 25px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        h2 { text-align: center; color: #5288c1; margin-bottom: 20px; font-size: 22px; }
        .alert { background: rgba(82, 136, 193, 0.1); border-left: 4px solid #5288c1; color: #6499d3; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        
        .user-list { display: flex; flex-direction: column; gap: 12px; }
        .user-item { display: flex; align-items: center; justify-content: space-between; background: #243447; padding: 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); }
        .user-info { display: flex; flex-direction: column; gap: 4px; }
        .username { font-weight: 600; color: #ffffff; font-size: 16px; }
        .current-badge { font-size: 11px; color: #7f91a4; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .select-cargo { background: #182533; color: white; border: 1px solid #5288c1; padding: 8px 12px; border-radius: 8px; outline: none; cursor: pointer; font-size: 14px; transition: 0.2s; }
        .select-cargo:focus { border-color: #6499d3; box-shadow: 0 0 5px rgba(82, 136, 193, 0.4); }
        
        .btn-save { background: #5288c1; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 13px; margin-left: 8px; transition: 0.2s; }
        .btn-save:hover { background: #6499d3; }
        .action-form { display: flex; align-items: center; }
    </style>
</head>
<body>

<div class="container">
    <h2>Gerenciar Cargos de Usuários</h2>

    <?php if (!empty($mensagem)): ?>
        <div class="alert"><?php echo $mensagem; ?></div>
    <?php endif; ?>

    <div class="user-list">
        <?php if (count($usuarios) > 0): ?>
            <?php foreach ($usuarios as $user): ?>
                <div class="user-item">
                    <div class="user-info">
                        <span class="username">@<?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="current-badge">Atual: <b><?php echo strtoupper($user['cargo']); ?></b></span>
                    </div>
                    
                    <form method="POST" class="action-form">
                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                        
                        <select name="novo_cargo" class="select-cargo">
                            <option value="usuarios" <?php echo ($user['cargo'] == 'usuarios') ? 'selected' : ''; ?>>Usuário Comum</option>
                            <option value="vip" <?php echo ($user['cargo'] == 'vip') ? 'selected' : ''; ?>>VIP</option>
                            <option value="empresas" <?php echo ($user['cargo'] == 'empresas') ? 'selected' : ''; ?>>Empresa</option>
                            <option value="admin" <?php echo ($user['cargo'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                        
                        <button type="submit" name="alterar_cargo" class="btn-save">Salvar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: #7f91a4;">Nenhum usuário encontrado no sistema.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>