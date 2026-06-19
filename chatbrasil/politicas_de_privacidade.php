<?php
session_start();
// Caso queira proteger a página ou puxar dados, o ambiente PHP já está pronto.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Políticas de Privacidade</title>
    
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

        /* Cabeçalho Estilo Telegram Desktop/Mobile */
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
            max-width: 600px; /* Mantém elegante se aberto no tablet/PC */
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

        /* Destaque para o aviso importante */
        .policy-body-text .warning-box {
            margin-top: 10px;
            padding: 10px 12px;
            background: rgba(82, 136, 193, 0.1);
            border-left: 3px solid var(--tg-blue);
            border-radius: 4px;
            font-size: 13px;
            color: #f5f5f5;
        }

        /* Quando o item estiver ativo (via JavaScript) */
        .policy-item.active .policy-content {
            max-height: 500px; /* Limite seguro para animação */
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
        <h1>Privacidade</h1>
    </header>

    <div class="container">
        
        <div class="intro-box">
            <i class="fa-solid fa-shield-halved"></i>
            <p>Sua privacidade está no nosso DNA. Nos esforçamos para criar uma plataforma segura e transparente para todos os nossos usuários.</p>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-user-lock"></i>
                    <h3>Dados que Coletamos</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Coletamos apenas as informações essenciais para o funcionamento do serviço: seu nome de usuário criado, o histórico de atividades e informações de segurança da conta para evitar fraudes ou acessos não autorizados.
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-cookie-bite"></i>
                    <h3>Uso de Cookies</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Utilizamos cookies funcionais para lembrar de suas preferências e manter sua sessão segura. 
                    
                    <div class="warning-box">
                        <i class="fa-solid fa-circle-info" style="color: var(--tg-blue); margin-right: 5px;"></i>
                        <strong>Aviso sobre login automático:</strong> Ao marcar a opção "Mantenha-me conectado", seus dados de login ficam salvos neste navegador para que você entre direto na conta sem precisar digitar a senha toda vez. Sua conta <strong>só será desconectada se você clicar manualmente no botão "Sair"</strong> nas configurações do app.
                    </div>
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-eye-slash"></i>
                    <h3>Compartilhamento de Informações</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Nós <strong>nunca</strong> vendemos ou compartilhamos seus dados pessoais com anunciantes ou empresas parceiras. Suas informações permanecem criptografadas e protegidas em nossos servidores privados.
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-user-gear"></i>
                    <h3>Seus Direitos</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Você tem total controle sobre seus dados. A qualquer momento, você pode solicitar a alteração de suas informações, revogação do seu consentimento de cookies ou a exclusão permanente de sua conta diretamente pelo painel de configurações.
                </div>
            </div>
        </div>

        <div class="footer-note">
            Última atualização: Junho de 2026<br>
            Baseado nas diretrizes gerais de proteção de dados.
        </div>

    </div>

    <script>
        // Função para abrir e fechar os tópicos no mobile
        function togglePolicy(element) {
            const item = element.parentElement;
            
            // Fecha outros itens abertos (Efeito Acordeão do WhatsApp)
            document.querySelectorAll('.policy-item').forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                }
            });

            // Alterna o item atual
            item.classList.toggle('active');
        }
    </script>
</body>
</html>