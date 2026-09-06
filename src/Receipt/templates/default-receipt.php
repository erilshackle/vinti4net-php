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

$companyName = $data['companyName'] ?? 'Comerciante';
$logo = $data['logo'] ?? null;
$styled = $data['styled'] ?? true;
?>
<article class="vinti4-receipt">
    <header class="vinti4-receipt__header">
        <?php if (is_string($logo) && $logo !== ''): ?>
            <img class="vinti4-receipt__logo" src="<?= $escape($logo) ?>" alt="<?= $escape($companyName) ?>">
        <?php endif; ?>

        <div>
            <h1>Comprovativo de pagamento</h1>
            <p><?= $escape($companyName) ?></p>
        </div>
    </header>

    <dl class="vinti4-receipt__details">
        <div><dt>Referência</dt><dd><?= $escape($receipt['merchantReference']) ?></dd></div>
        <div><dt>Transação</dt><dd><?= $escape($receipt['transactionId']) ?></dd></div>
        <div><dt>Data/Hora</dt><dd><?= $escape($receipt['timestamp']) ?></dd></div>
        <div><dt>Cartão</dt><dd><?= $escape($receipt['pan'] ?: 'N/A') ?></dd></div>
        <div><dt>Autorização</dt><dd><?= $escape($receipt['authorizationCode'] ?: 'N/A') ?></dd></div>
    </dl>

    <div class="vinti4-receipt__total">
        <span>Total</span>
        <strong><?= $escape($receipt['amount'] ?? 'N/A') ?> <?= $escape($receipt['currency']) ?></strong>
    </div>

    <footer class="vinti4-receipt__status">
        <?= $receipt['success'] ? 'Transação aprovada' : $escape($receipt['message']) ?>
    </footer>
</article>

<?php if ($styled): ?>
<style>
.vinti4-receipt{box-sizing:border-box;width:min(100%,440px);margin:1.5rem auto;padding:1.25rem;border:1px solid #d7dce2;border-radius:12px;background:#fff;color:#20242a;font:14px/1.5 Arial,sans-serif}.vinti4-receipt__header{display:flex;align-items:center;gap:1rem;padding-bottom:1rem;border-bottom:1px solid #e5e7eb}.vinti4-receipt__header h1{margin:0;font-size:19px}.vinti4-receipt__header p{margin:.2rem 0 0;color:#68707b}.vinti4-receipt__logo{display:block;max-width:64px;max-height:48px;object-fit:contain}.vinti4-receipt__details{margin:1rem 0}.vinti4-receipt__details div{display:flex;justify-content:space-between;gap:1rem;padding:.35rem 0}.vinti4-receipt__details dt{font-weight:600;color:#68707b}.vinti4-receipt__details dd{margin:0;text-align:right;overflow-wrap:anywhere}.vinti4-receipt__total{display:flex;justify-content:space-between;align-items:center;margin-top:1rem;padding:1rem;background:#f4f6f8;border-radius:8px}.vinti4-receipt__total strong{font-size:20px}.vinti4-receipt__status{margin-top:1rem;text-align:center;font-weight:700;color:#176b3a}
</style>
<?php endif; ?>
