<?php
require_once "confi.php";

header("Content-Type: text/html; charset=utf-8");

echo "<h2>🔐 CRIPTO.PHP - Criptografia de Ponta a Ponta Real (E2E)</h2>";

// ==========================
// FUNÇÕES DE CRIPTOGRAFIA
// ==========================

// Criptografa usando a CHAVE PÚBLICA DO DESTINATÁRIO
function criptografarParaDestinatario($dados, $publicKeyReceiver) {
    if (empty($dados)) return null;

    $aesKey = random_bytes(32);
    $iv = random_bytes(12);

    // Protege a chave AES usando a chave pública de quem vai RECEBER
    if (!openssl_public_encrypt($aesKey, $encryptedKey, $publicKeyReceiver)) {
        return null; 
    }

    $ciphertext = openssl_encrypt(
        $dados,
        "aes-256-gcm",
        $aesKey,
        1,
        $iv,
        $tag
    );

    return base64_encode(json_encode([
        "key" => base64_encode($encryptedKey),
        "iv" => base64_encode($iv),
        "tag" => base64_encode($tag),
        "data" => base64_encode($ciphertext)
    ]));
}

// Descriptografa usando a CHAVE PRIVADA DO USUÁRIO LOGADO
function descriptografarComMinhaChave($pacote, $privateKeyOwner) {
    if (empty($pacote)) return null;

    $json = json_decode(base64_decode($pacote), true);
    if (!$json) return null;

    // Decodifica a chave AES usando a chave privada de quem RECEBEU
    $decrypted = openssl_private_decrypt(base64_decode($json["key"]), $aesKey, $privateKeyOwner);
    if (!$decrypted) return "[Erro: Você não tem a chave correta para ler esta mensagem]";

    return openssl_decrypt(
        base64_decode($json["data"]),
        "aes-256-gcm",
        $aesKey,
        1,
        base64_decode($json["iv"]),
        base64_decode($json["tag"])
    );
}

// ==========================
// SIMULAÇÃO DE USUÁRIOS (Troque pelos dados da sua sessão/banco)
// ==========================

// IMPORTANTE: Em produção, armazene as chaves públicas no banco associadas ao ID do usuário.
// As chaves privadas devem ser salvas de forma segura no dispositivo do usuário.
$config = ["private_key_bits" => 2048, "private_key_type" => OPENSSL_KEYTYPE_RSA];

// Usuário 1 (Remetente fictício)
$res1 = openssl_pkey_new($config); openssl_pkey_export($res1, $privKey1); $pubKey1 = openssl_pkey_get_details($res1)["key"];

// Usuário 2 (Destinatário fictício)
$res2 = openssl_pkey_new($config); openssl_pkey_export($res2, $privKey2); $pubKey2 = openssl_pkey_get_details($res2)["key"];

// Definindo quem está logado no momento da página (Simulação)
$meuUser = 1; 
$minhaChavePrivada = $privKey1; // Usuário 1 usa sua própria chave para ler o que mandam para ele

// Banco de chaves públicas conhecidas pelo sistema
$chavesPublicasDosUsuarios = [
    1 => $pubKey1,
    2 => $pubKey2
];

// ==========================
// ENVIAR MENSAGEM / MÍDIA
// ==========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $receiver = isset($_POST["receiver_id"]) ? (int)$_POST["receiver_id"] : null;
    $message = $_POST["message"] ?? "";
    
    // IMPORTANTE: Buscando a chave pública de quem vai RECEBER a mensagem
    $publicKeyOfReceiver = $chavesPublicasDosUsuarios[$receiver] ?? null;

    if (!$publicKeyOfReceiver) {
        echo "<p style='color: red;'>❌ Erro: Chave pública do destinatário não encontrada!</p>";
    } else {
        $encryptedMessage = !empty($message) ? criptografarParaDestinatario($message, $publicKeyOfReceiver) : null;
        $encryptedMediaData = null;
        $encryptedMediaType = null;

        // Processamento de Arquivos
        if (isset($_FILES["media"]) && $_FILES["media"]["error"] == 0) {
            $fileTmpPath = $_FILES["media"]["tmp_name"];
            $fileType = $_FILES["media"]["type"];
            
            $fileData = file_get_contents($fileTmpPath);
            $base64File = 'data:' . $fileType . ';base64,' . base64_encode($fileData);

            // Criptografa os arquivos também com a chave pública do destinatário
            $encryptedMediaData = criptografarParaDestinatario($base64File, $publicKeyOfReceiver);
            $encryptedMediaType = criptografarParaDestinatario($fileType, $publicKeyOfReceiver);
        }

        if ($encryptedMessage || $encryptedMediaData) {
            $stmt = $pdo->prepare("
                INSERT INTO messages 
                (sender_id, receiver_id, message, media_url, media_type, is_read)
                VALUES (?, ?, ?, ?, ?, 0)
            ");

            $stmt->execute([
                $meuUser,
                $receiver,
                $encryptedMessage,
                $encryptedMediaData,
                $encryptedMediaType
            ]);

            echo "<p style='color: green;'>✅ Mensagem enviada de ponta a ponta! Somente o ID {$receiver} conseguirá abrir.</p>";
        }
    }
}

// ==========================
// LISTAR MENSAGENS
// ==========================
echo "<h3>📨 Mensagens do Banco: ezyro_42084035_chatbrasil</h3>";

$stmt = $pdo->query("SELECT * FROM messages ORDER BY id DESC");

while ($row = $stmt->fetch()) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; border-radius: 5px;'>";
    echo "<b>De:</b> Usuário {$row['sender_id']} | <b>Para:</b> Usuário {$row['receiver_id']} <br>";
    
    // Na criptografia E2E padrão, apenas o RECEBEDOR original consegue abrir.
    if ($meuUser == $row['receiver_id']) {
        $chaveParaUsar = $minhaChavePrivada;
        $podeLer = true;
    } else {
        $podeLer = false;
    }

    if ($podeLer) {
        // Descriptografando texto
        $texto = $row["message"] ? descriptografarComMinhaChave($row["message"], $chaveParaUsar) : "";
        if (!empty($texto)) {
            echo "<b>Mensagem:</b> " . htmlspecialchars($texto) . "<br>";
        }

        // Descriptografando mídia
        if ($row["media_url"] && $row["media_type"]) {
            $mediaType = descriptografarComMinhaChave($row["media_type"], $chaveParaUsar);
            $mediaData = descriptografarComMinhaChave($row["media_url"], $chaveParaUsar);

            if (strpos($mediaType, 'image/') === 0) {
                echo "<img src='{$mediaData}' style='max-width: 250px; margin-top: 5px; border-radius: 8px;'><br>";
            } elseif (strpos($mediaType, 'audio/') === 0) {
                echo "<audio controls src='{$mediaData}' style='margin-top: 5px;'></audio><br>";
            } else {
                echo "<a href='{$mediaData}' download>Baixar Arquivo Seguro</a><br>";
            }
        }
    } else {
        echo "<span style='color: red;'>🔒 [Conteúdo Criptografado] - Apenas o destinatário (Usuário {$row['receiver_id']}) possui a chave privada para decodificar esta mensagem.</span><br>";
    }
    
    echo "</div>";
}
?>