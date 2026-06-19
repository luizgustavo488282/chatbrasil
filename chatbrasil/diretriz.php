<?php
session_start();
// O ambiente PHP está pronto para receber suas lógicas caso necessário.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diretrizes da Comunidade</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-main: #17212b;      /* Fundo Telegram App */
            --bg-card: #24303f;      /* Cards e cabeçalho do Telegram */
            --tg-blue: #5288c1;      /* Azul clássico do Telegram */
            --tg-hover: #6499d3;
            --text-main: #f5f5f5;    /* Texto principal */
            --text-muted: #7f91a4;   /* Texto secundário */
            --border-color: rgba(255, 255, 255, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-main);
            line-height: 1.6;
            padding-bottom: 40px;
        }

        /* Cabeçalho Estilo Telegram Mobile */
        header {
            background-color: var(--bg-card);
            padding: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        header .back-btn {
            color: var(--tg-blue);
            font-size: 20px;
            text-decoration: none;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            transition: background 0.2s;
        }

        header .back-btn:active {
            background: rgba(255, 255, 255, 0.05);
        }

        header h1 {
            font-size: 19px;
            font-weight: 600;
        }

        /* Container Principal */
        .container {
            width: 100%;
            padding: 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Introdução estilo WhatsApp */
        .intro-box {
            text-align: center;
            padding: 20px 10px;
            margin-bottom: 15px;
        }

        .intro-box i {
            font-size: 45px;
            color: var(--tg-blue);
            margin-bottom: 12px;
        }

        .intro-box p {
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Estrutura de tópicos colapsáveis (Inspirado no FAQ do WhatsApp) */
        .policy-item {
            background-color: var(--bg-card);
            border-radius: 12px;
            margin-bottom: 12px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .policy-item:active {
            transform: scale(0.99);
        }

        .policy-header {
            padding: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .policy-title-wrapper {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .policy-title-wrapper i {
            font-size: 18px;
            color: var(--tg-blue);
            width: 24px;
            text-align: center;
        }

        .policy-header h3 {
            font-size: 15px;
            font-weight: 500;
        }

        .policy-header .chevron {
            font-size: 14px;
            color: var(--text-muted);
            transition: transform 0.3s ease;
        }

        .policy-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background-color: rgba(0, 0, 0, 0.1);
        }

        .policy-body-text {
            padding: 16px;
            font-size: 14px;
            color: #d1d7de;
            border-top: 1px solid var(--border-color);
        }

        /* Caixa de Alerta das Diretrizes (Aviso Amarelado) */
        .policy-body-text .notice-box {
            margin-top: 10px;
            padding: 10px 12px;
            background: rgba(241, 196, 15, 0.08);
            border-left: 3px solid #f1c40f;
            border-radius: 4px;
            font-size: 13px;
            color: #fce8a6;
        }

        /* Ativação via JS */
        .policy-item.active .policy-content {
            max-height: 500px;
            transition: max-height 0.4s ease-in;
        }

        .policy-item.active .chevron {
            transform: rotate(180deg);
            color: var(--tg-blue);
        }

        /* Nota de Rodapé */
        .footer-note {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <header>
        <a href="login.php" class="back-btn">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <h1>Diretrizes</h1>
    </header>

    <div class="container">
        
        <div class="intro-box">
            <i class="fa-solid fa-users"></i>
            <p>Estas diretrizes servem para garantir um ecossistema saudável, seguro e acolhedor para que todos os membros conversem com tranquilidade.</p>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-heart"></i>
                    <h3>1. Respeito Mútuo</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Trate todos os usuários com dignidade. Não toleramos discursos de ódio, discriminação de qualquer natureza (raça, gênero, orientação sexual ou religião), bullying, ameaças de agressão ou intimidações.
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <h3>2. Conteúdo Proibido</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    É proibida a publicação, compartilhamento ou divulgação de materiais com teor pornográfico explícito, violência gráfica extrema, incentivo a atos de automutilação ou comercialização de produtos ilegais.
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-bullhorn"></i>
                    <h3>3. Spam e Autopromoção</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Evite poluir os canais ou chats com mensagens repetitivas, links maliciosos (phishing), publicidade em massa não autorizada ou bots projetados para atrapalhar a experiência dos demais membros da rede.
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-flag"></i>
                    <h3>4. Sistema de Denúncias</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Se você flagrar qualquer usuário quebrando as regras listadas acima, utilize a ferramenta de denúncia integrada ao lado do perfil do infrator ou entre em contato com um administrador imediatamente.
                    
                    <div class="notice-box">
                        <i class="fa-solid fa-circle-exclamation" style="margin-right: 5px;"></i>
                        <strong>Importante:</strong> Denúncias falsas ou feitas de má-fé com o único intuito de prejudicar outros membros também serão passíveis de penalização e suspensão da conta.
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Última atualização: Junho de 2026<br>
            Contamos com sua ajuda para manter o nosso app seguro!
        </div>

    </div>

    <script>
        function togglePolicy(element) {
            const item = element.parentElement;
            
            document.querySelectorAll('.policy-item').forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });

            item.classList.toggle('active');
        }
    </script>
</body>
</html>