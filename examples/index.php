<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vinti4Net — Exemplo de Integração</title>

    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --surface: #fff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f172a;
                --surface: #1e293b;
                --text: #f8fafc;
                --muted: #94a3b8;
                --border: #334155;
                --primary: #3b82f6;
                --primary-hover: #60a5fa;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            background: var(--bg);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            line-height: 1.6;
        }

        .container {
            width: min(1080px, calc(100% - 32px));
            min-height: 100vh;
            margin-inline: auto;
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 64px;
            align-items: center;
            padding-block: 64px;
        }

        .eyebrow {
            margin: 0 0 8px;
            color: var(--primary);
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(2rem, 5vw, 3.4rem);
            line-height: 1.1;
        }

        .lead {
            max-width: 640px;
            margin: 0 0 36px;
            color: var(--muted);
            font-size: 1.08rem;
        }

        .steps {
            display: grid;
            gap: 22px;
        }

        .step {
            display: grid;
            grid-template-columns: 38px 1fr;
            gap: 14px;
        }

        .step-number {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            background: var(--primary);
            font-weight: 700;
        }

        .step h2 {
            margin: 0 0 3px;
            font-size: 1rem;
        }

        .step p {
            margin: 0;
            color: var(--muted);
            font-size: .94rem;
        }

        code {
            padding: 2px 5px;
            border-radius: 4px;
            background: color-mix(in srgb, var(--border) 65%, transparent);
        }

        .payment-card {
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--surface);
            box-shadow: 0 20px 50px rgba(15, 23, 42, .08);
        }

        .payment-card h2 {
            margin: 0 0 6px;
            font-size: 1.4rem;
        }

        .payment-card > p {
            margin: 0 0 24px;
            color: var(--muted);
        }

        .field { margin-bottom: 18px; }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: .9rem;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--border);
            border-radius: 7px;
            color: var(--text);
            background: var(--bg);
            font: inherit;
        }

        input:focus {
            border-color: var(--primary);
            outline: 3px solid color-mix(in srgb, var(--primary) 18%, transparent);
        }

        button {
            width: 100%;
            padding: 12px 18px;
            border: 0;
            border-radius: 7px;
            color: #fff;
            background: var(--primary);
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover { background: var(--primary-hover); }

        .note {
            margin: 16px 0 0 !important;
            font-size: .82rem;
            text-align: center;
        }

        @media (max-width: 800px) {
            .container {
                grid-template-columns: 1fr;
                gap: 42px;
                padding-block: 40px;
            }
        }
    </style>
</head>

<body>
<main class="container">
    <section>
        <p class="eyebrow">Exemplo de integração</p>
        <h1>Pagamento com Vinti4Net</h1>

        <p class="lead">
            Este exemplo demonstra o fluxo básico para preparar uma compra 3DS,
            gerar o formulário de pagamento e processar a resposta da SISP.
        </p>

        <div class="steps">
            <article class="step">
                <span class="step-number">1</span>
                <div>
                    <h2>Configure as credenciais</h2>
                    <p>
                        Defina <code>VINTI4_POS_ID</code>, <code>VINTI4_AUTH_CODE</code>
                        e, se necessário, <code>VINTI4_ENDPOINT</code>.
                    </p>
                </div>
            </article>

            <article class="step">
                <span class="step-number">2</span>
                <div>
                    <h2>Prepare o pagamento</h2>
                    <p>
                        O arquivo <code>payment_example.php</code> recebe os dados,
                        cria o Billing e chama <code>preparePurchase()</code>.
                    </p>
                </div>
            </article>

            <article class="step">
                <span class="step-number">3</span>
                <div>
                    <h2>Envie para a SISP</h2>
                    <p>
                        <code>createPaymentForm()</code> gera o formulário que
                        redireciona o cliente para concluir o pagamento.
                    </p>
                </div>
            </article>

            <article class="step">
                <span class="step-number">4</span>
                <div>
                    <h2>Processe a resposta</h2>
                    <p>
                        A SISP retorna para <code>callback_example.php</code>, onde
                        o fingerprint e o estado da transação são validados.
                    </p>
                </div>
            </article>
        </div>
    </section>

    <section class="payment-card">
        <h2>Iniciar pagamento</h2>
        <p>Altere os dados abaixo ou utilize os valores padrão do exemplo.</p>

        <form action="payment_example.php" method="post">
            <div class="field">
                <label for="amount">Valor</label>
                <input id="amount" name="amount" type="number" min="1" step="1" value="150" required>
            </div>

            <div class="field">
                <label for="merchant_ref">Referência do pedido</label>
                <input id="merchant_ref" name="merchant_ref" type="text" maxlength="15" value="REF000000000001" required>
            </div>

            <div class="field">
                <label for="email">Email do cliente</label>
                <input id="email" name="email" type="email" value="cliente@example.cv" required>
            </div>

            <button type="submit">Chamar payment_example.php</button>
        </form>

        <p class="note">As credenciais são lidas apenas das variáveis de ambiente.</p>
    </section>
</main>
</body>
</html>