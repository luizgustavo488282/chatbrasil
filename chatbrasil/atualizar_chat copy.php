<?php
require_once 'confi.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
    exit;
}

$chatId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($chatId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

function obterTextoStatusTelegram($lastActivityString) {
    if (!$lastActivityString) {
        return ['text' => 'visto há muito tempo', 'online' => false];
    }
    
    $lastActivity = new DateTime($lastActivityString);
    $now = new DateTime();
    $diff = $now->getTimestamp() - $lastActivity->getTimestamp();

    if ($diff < 45) {
        return ['text' => 'online', 'online' => true];
    }
    
    if ($lastActivity->format('Y-m-d') === $now->format('Y-m-d')) {
        return ['text' => "visto hoje às " . $lastActivity->format('H:i'), 'online' => false];
    }
    
    $yesterday = new DateTime('yesterday');
    if ($lastActivity->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        return ['text' => "visto ontem às " . $lastActivity->format('H:i'), 'online' => false];
    }
    
    return [
        'text' => "visto por último em " . $lastActivity->format('d/m/Y') . " às " . $lastActivity->format('H:i'), 
        'online' => false
    ];
}

try {
    $stmt = $pdo->prepare("SELECT last_activity FROM users WHERE id = ?");
    $stmt->execute([$chatId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $statusInfo = obterTextoStatusTelegram($result['last_activity']);
        echo json_encode([
            'status' => 'success',
            'status_text' => $statusInfo['text'],
            'is_online' => $statusInfo['online']
        ]);
        exit;
    } else {
        echo json_encode([
            'status' => 'success',
            'status_text' => 'visto recentemente',
            'is_online' => false
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Falha ao buscar status']);
    exit;
}