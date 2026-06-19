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

    // Garante que a coluna reply_to_id existe na tabela de mensagens para evitar erros de SQL
    try { 
        $pdo->exec("ALTER TABLE messages ADD COLUMN reply_to_id INT DEFAULT NULL"); 
    } catch (Exception $e) {
        // Ignora se a coluna já existir
    }

    // Marca as mensagens recebidas como lidas
    $stmtUpdate = $pdo->prepare("
        UPDATE messages 
        SET is_read = 1 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmtUpdate->execute([$chatId, $userId]);

    // Busca todas as mensagens do chat fazendo o JOIN com as respostas de forma segura
    $stmt = $pdo->prepare("
        SELECT m.id, m.sender_id, m.receiver_id, m.message, m.media_url, m.media_type, m.is_read, m.is_view_once, m.is_audio, m.reply_to_id,
               r.message AS reply_to_text,
               DATE_FORMAT(m.created_at, '%H:%i') as hora 
        FROM messages m
        LEFT JOIN messages r ON m.reply_to_id = r.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$userId, $chatId, $chatId, $userId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Garante tipagem correta e evita valores nulos que travam o JavaScript
    foreach ($messages as &$m) {
        $m['id'] = (int)$m['id'];
        $m['sender_id'] = (int)$m['sender_id'];
        $m['receiver_id'] = (int)$m['receiver_id'];
        $m['is_read'] = (int)$m['is_read'];
        $m['is_view_once'] = (int)($m['is_view_once'] ?? 0);
        $m['is_audio'] = (int)($m['is_audio'] ?? 0);
        $m['reply_to_id'] = $m['reply_to_id'] !== null ? (int)$m['reply_to_id'] : null;
        
        if ($m['message'] === null) {
            $m['message'] = '';
        }
        if ($m['reply_to_text'] === null) {
            $m['reply_to_text'] = '';
        }
    }

    echo json_encode([
        'status' => 'success',
        'user_id' => $userId,
        'messages' => $messages
    ]);
    exit;

} catch (Exception $e) {
    // Se der erro, mostra o erro real em vez de uma mensagem genérica para sabermos o que há de errado no banco
    echo json_encode([
        'status' => 'error', 
        'message' => 'Erro interno banco de dados: ' . $e->getMessage()
    ]);
    exit;
}