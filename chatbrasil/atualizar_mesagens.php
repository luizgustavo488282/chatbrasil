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
    echo json_encode(['status' => 'success', 'user_id' => $userId, 'messages' => []]);
    exit;
}

try {
    // Alinha a hora interna da consulta com o fuso brasileiro (-3 horas)
    $pdo->exec("SET time_zone = '-03:00'");

    $stmtUpdate = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmtUpdate->execute([$chatId, $userId]);

    $stmt = $pdo->prepare("
        SELECT id, sender_id, receiver_id, message, media_url, media_type, is_read, is_view_once, is_audio,
               DATE_FORMAT(created_at, '%H:%i') as hora 
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId, $chatId, $chatId, $userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$m) {
        $m['id'] = (int)$m['id'];
        $m['sender_id'] = (int)$m['sender_id'];
        $m['receiver_id'] = (int)$m['receiver_id'];
        $m['is_read'] = (int)$m['is_read'];
        $m['is_view_once'] = (int)($m['is_view_once'] ?? 0);
        $m['is_audio'] = (int)($m['is_audio'] ?? 0);
        
        if ($m['message'] === null) {
            $m['message'] = '';
        }
    }

    echo json_encode([
        'status' => 'success',
        'user_id' => $userId,
        'messages' => $messages
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro interno banco de dados']);
    exit;
}