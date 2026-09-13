<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vinti4Net — Exemplos de Integração</title>
    <style>
        :root{color-scheme:light dark;--bg:#f8fafc;--surface:#fff;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--primary:#2563eb;--hover:#1d4ed8}
        @media(prefers-color-scheme:dark){:root{--bg:#0f172a;--surface:#1e293b;--text:#f8fafc;--muted:#94a3b8;--border:#334155;--primary:#3b82f6;--hover:#2563eb}}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        .container{width:min(1080px,calc(100% - 32px));min-height:100vh;margin:auto;display:grid;grid-template-columns:1.1fr .9fr;gap:64px;align-items:center;padding:56px 0}
        .eyebrow{margin:0 0 8px;color:var(--primary);font-size:.85rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase}
        h1{margin:0 0 16px;font-size:clamp(2rem,5vw,3.25rem);line-height:1.12}.lead{margin:0 0 32px;color:var(--muted);font-size:1.06rem}
        .steps{display:grid;gap:20px}.step{display:grid;grid-template-columns:38px 1fr;gap:14px}.step-number{width:38px;height:38px;display:grid;place-items:center;border-radius:50%;background:var(--primary);color:#fff;font-weight:700}
        .step h2{margin:0;font-size:1rem}.step p{margin:0;color:var(--muted);font-size:.93rem}code{padding:2px 5px;border-radius:4px;background:var(--border)}
        .card{padding:30px;border:1px solid var(--border);border-radius:16px;background:var(--surface);box-shadow:0 20px 50px rgba(15,23,42,.08)}.card h2{margin:0;font-size:1.4rem}.subtitle{margin:4px 0 22px;color:var(--muted);font-size:.94rem}
        .tabs{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-bottom:24px;padding:4px;border-radius:9px;background:var(--bg)}
        .tab{padding:9px 4px;border:0;border-radius:7px;background:transparent;color:var(--muted);font-size:.84rem;font-weight:600;cursor:pointer}.tab[aria-selected="true"]{background:var(--surface);color:var(--primary);box-shadow:0 1px 4px rgba(15,23,42,.13)}
        .panel[hidden]{display:none}.field{margin-bottom:16px}label{display:block;margin-bottom:5px;font-size:.89rem;font-weight:600}input{width:100%;padding:11px 12px;border:1px solid var(--border);border-radius:7px;background:var(--bg);color:var(--text);font:inherit}
        input:focus{outline:3px solid rgba(59,130,246,.2);border-color:var(--primary)}.submit{width:100%;padding:12px 18px;border:0;border-radius:7px;background:var(--primary);color:#fff;font:inherit;font-weight:700;cursor:pointer}.submit:hover{background:var(--hover)}
        .note{margin:16px 0 0;color:var(--muted);font-size:.81rem;text-align:center}.hint{margin:-5px 0 17px;color:var(--muted);font-size:.84rem}
        @media(max-width:800px){.container{grid-template-columns:1fr;gap:38px;padding:40px 0}}@media(max-width:440px){.card{padding:20px}.tabs{grid-template-columns:repeat(2,1fr)}}
    </style>
</head>
<body>
<main class="container">
    <section>
        <p class="eyebrow">Exemplos de integração</p>
        <h1>Pagamentos com Vinti4Net</h1>
        <p class="lead">Experimente compra, pagamento de serviço, recarga e estorno com os exemplos da v2.2.</p>
        <div class="steps">
            <article class="step"><span class="step-number">1</span><div><h2>Configure as credenciais</h2><p>Defina <code>VINTI4_POS_ID</code> e <code>VINTI4_AUTH_CODE</code>. O endpoint personalizado é opcional.</p></div></article>
            <article class="step"><span class="step-number">2</span><div><h2>Escolha uma operação</h2><p>Cada aba envia os dados ao seu próprio arquivo de preparação.</p></div></article>
            <article class="step"><span class="step-number">3</span><div><h2>Envie para a SISP</h2><p>A biblioteca cria o formulário para o endpoint da operação.</p></div></article>
            <article class="step"><span class="step-number">4</span><div><h2>Receba o resultado</h2><p>Pagamento e estorno têm callbacks separados, que processam <code>$_POST</code>.</p></div></article>
        </div>
    </section>
    <section class="card" aria-label="Iniciar transação">
        <h2>Iniciar transação</h2>
        <p class="subtitle">Selecione a operação e preencha os dados necessários.</p>
        <div class="tabs" role="tablist" aria-label="Tipo de transação">
            <button class="tab" type="button" role="tab" id="tab-purchase" aria-controls="panel-purchase" aria-selected="true">Compra</button>
            <button class="tab" type="button" role="tab" id="tab-service" aria-controls="panel-service" aria-selected="false" tabindex="-1">Serviço</button>
            <button class="tab" type="button" role="tab" id="tab-recharge" aria-controls="panel-recharge" aria-selected="false" tabindex="-1">Recarga</button>
            <button class="tab" type="button" role="tab" id="tab-refund" aria-controls="panel-refund" aria-selected="false" tabindex="-1">Estorno</button>
        </div>
        <div class="panel" role="tabpanel" id="panel-purchase" aria-labelledby="tab-purchase">
            <form action="purchase_example.php" method="post">
                <div class="field"><label for="purchase-amount">Valor (CVE)</label><input id="purchase-amount" name="amount" type="number" min="1" step="1" value="1000" required></div>
                <div class="field"><label for="purchase-email">Email do cliente</label><input id="purchase-email" name="email" type="email" value="cliente@example.cv" required></div>
                <button class="submit" type="submit">Iniciar compra</button>
            </form>
        </div>
        <div class="panel" role="tabpanel" id="panel-service" aria-labelledby="tab-service" hidden>
            <form action="service_example.php" method="post">
                <div class="field"><label for="service-amount">Valor (CVE)</label><input id="service-amount" name="amount" type="number" min="1" step="1" value="1000" required></div>
                <div class="field"><label for="service-entity">Entidade</label><input id="service-entity" name="entity" inputmode="numeric" pattern="[0-9]+" required></div>
                <div class="field"><label for="service-number">Referência</label><input id="service-number" name="number" inputmode="numeric" pattern="[0-9]{1,9}" required></div>
                <button class="submit" type="submit">Pagar serviço</button>
            </form>
        </div>
        <div class="panel" role="tabpanel" id="panel-recharge" aria-labelledby="tab-recharge" hidden>
            <form action="recharge_example.php" method="post">
                <div class="field"><label for="recharge-amount">Valor (CVE)</label><input id="recharge-amount" name="amount" type="number" min="1" step="1" value="500" required></div>
                <div class="field"><label for="recharge-entity">Entidade</label><input id="recharge-entity" name="entity" inputmode="numeric" pattern="[0-9]+" required></div>
                <div class="field"><label for="recharge-number">Telemóvel/referência</label><input id="recharge-number" name="number" inputmode="numeric" pattern="[0-9]{1,9}" required></div>
                <button class="submit" type="submit">Iniciar recarga</button>
            </form>
        </div>
        <div class="panel" role="tabpanel" id="panel-refund" aria-labelledby="tab-refund" hidden>
            <form action="refund_example.php" method="post">
                <div class="field"><label for="refund-amount">Valor integral da compra (CVE)</label><input id="refund-amount" name="amount" type="number" min="1" step="1" required></div>
                <div class="field"><label for="refund-transaction">Transaction ID original</label><input id="refund-transaction" name="transaction_id" required></div>
                <div class="field"><label for="refund-period">Clearing Period original</label><input id="refund-period" name="clearing_period" inputmode="numeric" required></div>
                <p class="hint">Use os três valores da mesma compra aprovada e ainda não estornada.</p>
                <button class="submit" type="submit">Solicitar estorno</button>
            </form>
        </div>
        <p class="note">As credenciais são lidas das variáveis de ambiente.</p>
    </section>
</main>
<script>
    const tabs=[...document.querySelectorAll('[role="tab"]')];
    function activate(tab){tabs.forEach(item=>{const active=item===tab;item.setAttribute('aria-selected',String(active));item.tabIndex=active?0:-1;document.getElementById(item.getAttribute('aria-controls')).hidden=!active})}
    tabs.forEach((tab,index)=>{tab.addEventListener('click',()=>activate(tab));tab.addEventListener('keydown',event=>{const delta=event.key==='ArrowRight'?1:event.key==='ArrowLeft'?-1:0;if(!delta)return;event.preventDefault();const next=tabs[(index+delta+tabs.length)%tabs.length];activate(next);next.focus()})});
</script>
</body>
</html>