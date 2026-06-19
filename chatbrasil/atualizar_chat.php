<?php
require_once 'confi.php';

// Seta o fuso horário brasileiro no PHP
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$chatId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($chatId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

// Lógica de cálculo do Status "Online" ou "Visto por último há..." (Estilo Telegram)
function obterTextoStatusTelegram($lastActivityStr) {
    $statusText = "visto há muito tempo";
    $isOnline = false;

    if (!empty($lastActivityStr)) {
        // Converte a string de data do banco respeitando o timezone setado no PHP acima
        $lastSeenTime = strtotime($lastActivityStr);
        $currentTime = time();
        $diff = $currentTime - $lastSeenTime;

        // Se a diferença for menor ou igual a 60 segundos, exibe Online
        if ($diff <= 60 && $diff >= -10) { 
            $statusText = "online";
            $isOnline = true;
        } else {
            if ($diff < 3600 && $diff > 0) {
                $minutos = round($diff / 60);
                if ($minutos < 1) $minutos = 1;
                $statusText = "visto por último há " . $minutos . " " . ($minutos == 1 ? "minuto" : "minutos");
            } elseif ($diff < 86400 && $diff > 0) {
                $horas = round($diff / 3600);
                $statusText = "visto por último há " . $horas . " " . ($horas == 1 ? "hora" : "horas");
            } else {
                // Formatação exata com Hora e Minuto atualizados (evitando travar em textos estáticos)
                $hojeInicial = strtotime('today midnight');
                $ontemInicial = strtotime('yesterday midnight');
                $horaFormatada = date('H:i', $lastSeenTime);

                if ($lastSeenTime >= $hojeInicial) {
                    $statusText = "visto por último hoje às " . $horaFormatada;
                } elseif ($lastSeenTime >= $ontemInicial) {
                    $statusText = "visto por último ontem às " . $horaFormatada;
                } else {
                    $statusText = "visto por último em " . date('d/m/Y', $lastSeenTime) . " às " . $horaFormatada;
                }
            }
        }
    }

    return ['text' => $statusText, 'online' => $isOnline];
}

try {
    // Sincroniza o fuso horário do banco de dados para a sessão atual
    $pdo->exec("SET time_zone = '-03:00'");

    // Selecionamos a string pura do last_activity para evitar incompatibilidade com UNIX_TIMESTAMP do servidor
    $stmt = $pdo->prepare("
        SELECT last_activity, is_typing, last_typing_id
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$chatId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        if ((int)$result['is_typing'] === 1 && (int)$result['last_typing_id'] === $userId) {
            echo json_encode([
                'status' => 'success',
                'status_text' => 'digitando...',
                'is_online' => true,
                'last_activity' => $result['last_activity']
            ]);
            exit;
        }

        $lastActivityStr = isset($result['last_activity']) ? $result['last_activity'] : '';
        $statusInfo = obterTextoStatusTelegram($lastActivityStr);
        
        echo json_encode([
            'status' => 'success',
            'status_text' => $statusInfo['text'],
            'is_online' => $statusInfo['online'],
            'last_activity' => $lastActivityStr
        ]);
        exit;
    } else {
        echo json_encode([
            'status' => 'success',
            'status_text' => 'visto recentemente',
            'is_online' => false,
            'last_activity' => ''
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Falha ao buscar status']);
    exit;
}