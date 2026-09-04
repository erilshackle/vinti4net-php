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
?>

<article class="custom-receipt">
    <h1><?= $escape($data['title'] ?? '') ?></h1>
    <p><?= $escape($receipt['merchantReference'] ?? '') ?></p>
    <p><?= $escape($receipt['amount'] ?? '') ?> <?= $escape($receipt['currency'] ?? '') ?></p>
</article>
