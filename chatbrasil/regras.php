<?php
session_start();
// O ambiente PHP está pronto caso queira validar sessões futuramente.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regras da Plataforma</title>
    
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
            --tg-green: #4caf50;     /* Verde para o que pode */
            --tg-red: #e53935;       /* Vermelho para o que NÃO pode */
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
            width: 24px;
            text-align: center;
        }

        /* Cores customizadas dos ícones dos títulos */
        .allowed-icon { color: var(--tg-green); }
        .prohibited-icon { color: var(--tg-red); }

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

        /* Lista de sub-regras interna */
        .rules-list {
            list-style: none;
        }

        .rules-list li {
            position: relative;
            padding-left: 26px;
            margin-bottom: 12px;
        }

        .rules-list li:last-child {
            margin-bottom: 0;
        }

        .rules-list li i {
            position: absolute;
            left: 0;
            top: 4px;
            font-size: 14px;
        }

        .rules-list.allowed li i { color: var(--tg-green); }
        .rules-list.prohibited li i { color: var(--tg-red); }

        /* Caixa de Alerta Final */
        .policy-body-text .info-box {
            margin-top: 10px;
            padding: 10px 12px;
            background: rgba(82, 136, 193, 0.1);
            border-left: 3px solid var(--tg-blue);
            border-radius: 4px;
            font-size: 13px;
            color: #f5f5f5;
        }

        /* Ativação via JS */
        .policy-item.active .policy-content {
            max-height: 600px; /* Limite aumentado para as listas */
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
        <h1>Regras do App</h1>
    </header>

    <div class="container">
        
        <div class="intro-box">
            <i class="fa-solid fa-gavel"></i>
            <p>Para manter nossa comunidade organizada e divertida para todos, listamos abaixo de forma clara o que é permitido e o que gera banimento.</p>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-circle-check allowed-icon"></i>
                    <h3>O que você PODE fazer</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    <ul class="rules-list allowed">
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Conversar livremente:</strong> Fazer novas amizades, participar dos chats públicos e interagir respeitosamente.
                        </li>
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Compartilhar mídias:</strong> Enviar fotos, vídeos e memes engraçados, desde que não infrinjam as regras de conteúdo adulto ou violento.
                        </li>
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Divulgar conteúdos permitidos:</strong> Compartilhar e divulgar canais internos, links de grupos da própria plataforma, projetos parceiros oficiais ou conteúdos informativos úteis para a comunidade.
                        </li>
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Personalizar seu perfil:</strong> Mudar seu nome de exibição, foto de avatar e bio sempre que desejar.
                        </li>
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Denunciar abusos:</strong> Reportar qualquer mensagem, comportamento ou perfil que quebre as regras para nossa equipe analisar.
                        </li>
                        <li>
                            <i class="fa-solid fa-check"></i>
                            <strong>Utilizar em múltiplos aparelhos:</strong> Fazer login no celular, tablet ou PC mantendo suas sessões salvas simultaneamente.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-circle-xmark prohibited-icon"></i>
                    <h3>O que você NÃO PODE fazer</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    <ul class="rules-list prohibited">
                        <li>
                            <i class="fa-solid fa-xmark"></i>
                            <strong>Ofender e Desrespeitar:</strong> Praticar preconceito, racismo, homofobia, bullying ou ataques verbais direcionados a outros membros.
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark"></i>
                            <strong>Enviar Spam de Links e Divulgações Proibidas:</strong> Enviar links repetitivos sem contexto, convites para plataformas concorrentes, divulgar sistemas de pirâmides financeiras, golpes ou propagandas massivas que incomodem os usuários nos chats.
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark"></i>
                            <strong>Divulgar Conteúdo Ilegal:</strong> Compartilhar links maliciosos (vírus, roubo de dados), fraudes financeiras, pirataria ou mídias de violência explícita.
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark"></i>
                            <strong>Se passar por outra pessoa:</strong> Criar contas fakes fingindo ser influenciadores, marcas conhecidas ou membros da nossa equipe de moderação.
                        </li>
                        <li>
                            <i class="fa-solid fa-xmark"></i>
                            <strong>Abusar do Sistema:</strong> Explorar bugs ou falhas do código para obter vantagens dentro da plataforma em vez de reportá-los.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="policy-item">
            <div class="policy-header" onclick="togglePolicy(this)">
                <div class="policy-title-wrapper">
                    <i class="fa-solid fa-triangle-exclamation" style="color: #f1c40f;"></i>
                    <h3>Como funcionam os Avisos</h3>
                </div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="policy-content">
                <div class="policy-body-text">
                    Nossa equipe avalia cada denúncia de forma individual. Dependendo da gravidade, as ações tomadas seguem esta ordem:
                    
                    <div class="info-box" style="border-left-color: #f1c40f; background: rgba(241, 196, 15, 0.05); margin-top: 12px;">
                        <strong>1º Infração Leve:</strong> Advertência formal via sistema.<br>
                        <strong>2º Infração Média:</strong> Silenciamento temporário da conta por 24h ou 7 dias.<br>
                        <strong>3º Infração Grave:</strong> Banimento permanente e remoção total do IP de nossa base de dados.
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Última atualização: Junho de 2026<br>
            O respeito garante a diversão de todos no aplicativo.
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