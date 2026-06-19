<?php
// api_metrics.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'telegram_clone'; 
$dbuser = 'root';            
$dbpass = '';                

/**
 * Função para capturar o IP público real, corrigindo o problema de ::1 em ambiente local
 */
function obterIpPublicoReal() {
    // 1. Verifica cabeçalhos padrão de proxy/redirecionamento primeiro
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // 2. Se o IP detectado for o localhost (::1 ou 127.0.0.1), força a descoberta do IP público da máquina
    if ($ip === '::1' || $ip === '127.0.0.1') {
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            // Consulta um serviço externo seguro que retorna apenas o IP público atual da sua rede
            $ipPublico = @file_get_contents('https://icanhazip.com', false, $ctx);
            if ($ipPublico !== false) {
                $ip = trim($ipPublico);
            }
        } catch (Exception $e) {
            // Se a consulta falhar, mantém o IP local para não quebrar a execução
        }
    }

    return $ip;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $dbuser, $dbpass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // =========================================================================
    // 1. RASTREAMENTO - Captura IP Público Real da Pessoa, Geolocaliza e Registra
    // =========================================================================
    if (isset($_GET['track'])) {
        
        // Chama a função inteligente para obter o IP real (resolve o problema do ::1)
        $ip = obterIpPublicoReal();

        $hoje = date('Y-m-d');
        $countryCode = 'BR'; // Padrão de segurança caso a API de geolocalização falhe

        // Consulta a API para identificar de qual país do mundo pertence o IP real da pessoa
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]); // Limita a espera em 2 segundos para não lentificar o site
            $geoJson = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,countryCode", false, $ctx);
            if ($geoJson) {
                $geoData = json_decode($geoJson, true);
                if (isset($geoData['status']) && $geoData['status'] === 'success') {
                    $countryCode = strtoupper($geoData['countryCode']);
                }
            }
        } catch (Exception $e) {
            $countryCode = 'BR';
        }

        try {
            // Salva a visita vinculando o IP real da pessoa e o código do país correspondente
            $stmt = $pdo->prepare("INSERT IGNORE INTO page_tracks (ip_address, access_date, country_code) VALUES (?, ?, ?)");
            $stmt->execute([$ip, $hoje, $countryCode]);
        } catch (Exception $e) {}
        exit;
    }

    // =========================================================================
    // 2. CÁLCULO DOS DADOS EM TEMPO REAL
    // =========================================================================
    // Usuários online nos últimos 3 minutos
    $stmtOnline = $pdo->query("SELECT COUNT(*) FROM users WHERE last_activity >= NOW() - INTERVAL 3 MINUTE");
    $onlineAgora = (int)$stmtOnline->fetchColumn();

    // Total de tráfego/visitas de hoje
    $stmtVisitas = $pdo->prepare("SELECT COUNT(*) FROM page_tracks WHERE access_date = CURDATE()");
    $stmtVisitas->execute();
    $visitasHoje = (int)$stmtVisitas->fetchColumn();

    // =========================================================================
    // 3. GEOLOCALIZAÇÃO DINÂMICA - TODOS OS PAÍSES DO MUNDO
    // =========================================================================
    // Agrupa e conta automaticamente qualquer país do mundo que acessou o site hoje
    $stmtPaises = $pdo->prepare("SELECT country_code, COUNT(*) as total FROM page_tracks WHERE access_date = CURDATE() GROUP BY country_code ORDER BY total DESC");
    $stmtPaises->execute();
    $dadosPaises = $stmtPaises->fetchAll(PDO::FETCH_ASSOC);

    // Monta o array dinâmico com base nos países que realmente geraram tráfego
    $paisesResponse = [];
    foreach ($dadosPaises as $linha) {
        $codigo = strtoupper($linha['country_code']);
        $paisesResponse[$codigo] = (int)$linha['total'];
    }

    // Se não houver visitas registradas ainda, mantém uma estrutura limpa
    if (empty($paisesResponse)) {
        $paisesResponse = ['Sem tráfego' => 0];
    }

    // =========================================================================
    // 4. REGISTRO CRONOLÓGICO (Histórico do Gráfico)
    // =========================================================================
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM site_metrics WHERE recorded_at >= NOW() - INTERVAL 1 MINUTE");
    if ((int)$stmtCheck->fetchColumn() === 0) {
        $stmtInsert = $pdo->prepare("INSERT INTO site_metrics (online_users, page_views) VALUES (?, ?)");
        $stmtInsert->execute([$onlineAgora, $visitasHoje]);
    }

    // Busca os últimos 10 registros cronológicos
    $stmtChart = $pdo->query("SELECT DATE_FORMAT(recorded_at, '%H:%i') as hora, online_users, page_views FROM site_metrics ORDER BY id DESC LIMIT 10");
    $historico = array_reverse($stmtChart->fetchAll(PDO::FETCH_ASSOC));

    // =========================================================================
    // 5. RETORNO DO JSON ATUALIZADO PARA O PAINEL
    // =========================================================================
    header('Content-Type: application/json');
    echo json_encode([
        'online_agora' => $onlineAgora,
        'visitas_hoje' => $visitasHoje,
        'paises'       => $paisesResponse, // Lista dinâmica contendo as siglas de qualquer país do mundo
        'historico'    => $historico
    ]);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}
?>