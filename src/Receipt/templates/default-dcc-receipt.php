<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $receipt
 * @var array<string, mixed> $data
 */

$escape = static fn(mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8',
);

$dcc = $receipt['dcc'];
$companyName = $data['companyName'] ?? null;
?>
<article class="vinti4-dcc-receipt">
    <h1>Recibo</h1>

    <?php if (is_string($companyName) && $companyName !== ''): ?>
        <p class="vinti4-dcc-receipt__company"><?= $escape($companyName) ?></p>
    <?php endif; ?>

    <dl>
        <div>
            <dt>Taxa de conversão \ Currency Conversion Rate:</dt>
            <dd>1 <?= $escape($dcc['currency']) ?> = <?= $escape($dcc['rate']) ?> CVE</dd>
        </div>
        <div>
            <dt>Taxa do serviço DCC \ DCC Markup:</dt>
            <dd><?= $escape($dcc['markup']) ?> <?= $escape($dcc['currency']) ?></dd>
        </div>
        <div>
            <dt>Moeda da Transação \ Transaction Currency:</dt>
            <dd><?= $escape($dcc['currency']) ?></dd>
        </div>
    </dl>

    <div class="vinti4-dcc-receipt__totals">
        <p>[ ] TOTAL: <strong><?= $escape($receipt['amount']) ?> <?= $escape($receipt['currency']) ?></strong></p>
        <p>[x] TOTAL: <strong><?= $escape($dcc['amount']) ?> <?= $escape($dcc['currency']) ?></strong></p>
    </div>

    <p>I have been offered choice of currencies and agreed to pay in [<?= $escape($dcc['currency']) ?>].</p>
    <p>Dynamic Currency Conversion (DCC) offered by rede vinti4.</p>
    <p>Exchange rate provided by Banco de Cabo Verde.</p>
</article>

<style>
.vinti4-dcc-receipt{box-sizing:border-box;width:min(100%,620px);margin:1.5rem auto;padding:1.25rem;border:1px solid #bbb;background:#f7f7f7;color:#111;font:14px/1.45 Arial,sans-serif}.vinti4-dcc-receipt h1{text-align:center;font-size:18px;margin:0 0 1.1rem}.vinti4-dcc-receipt__company{text-align:center;margin:-.7rem 0 1rem}.vinti4-dcc-receipt dl{margin:0}.vinti4-dcc-receipt dl div{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:1rem;margin:.8rem 0}.vinti4-dcc-receipt dt{font-weight:400}.vinti4-dcc-receipt dd{margin:0}.vinti4-dcc-receipt__totals{margin:2rem 0 1rem}.vinti4-dcc-receipt__totals p{display:grid;grid-template-columns:8rem 1fr;margin:.6rem 0}.vinti4-dcc-receipt p{margin:.7rem 0}@media(max-width:560px){.vinti4-dcc-receipt dl div{grid-template-columns:1fr;gap:.2rem}.vinti4-dcc-receipt__totals p{grid-template-columns:1fr}}
</style>
