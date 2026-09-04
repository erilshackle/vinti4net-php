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

$companyName = $data['companyName']
    ?? 'Comerciante';

$logo = $data['logo'] ?? null;

$status = $receipt['status'] ?? 'ERROR';

$statusText = match ($status) {
    'SUCCESS' => 'Transação aprovada',
    'CANCELLED' => 'Transação cancelada',
    'INVALID_FINGERPRINT' => 'Resposta inválida',
    default => 'Transação não concluída',
};

$statusClass = match ($status) {
    'SUCCESS' => 'success',
    'CANCELLED' => 'cancelled',
    default => 'error',
};
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Comprovativo de pagamento</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 16px;
            color: #1f2937;
            background: #f3f4f6;
            font: 15px/1.5 system-ui, sans-serif;
        }

        .receipt {
            width: min(100%, 520px);
            margin: auto;
            padding: 32px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        .header {
            margin-bottom: 28px;
            text-align: center;
        }

        .logo {
            max-width: 120px;
            max-height: 64px;
            margin-bottom: 12px;
        }

        h1 {
            margin: 0;
            font-size: 21px;
        }

        .company {
            margin: 6px 0 0;
            color: #6b7280;
        }

        .amount {
            margin: 26px 0;
            font-size: 30px;
            font-weight: 700;
            text-align: center;
        }

        .details {
            margin: 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            padding: 11px 0;
            border-bottom: 1px solid #f0f1f3;
        }

        .row dt {
            color: #6b7280;
        }

        .row dd {
            margin: 0;
            font-weight: 600;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .status {
            margin-top: 26px;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 700;
            text-align: center;
        }

        .status.success {
            color: #166534;
            background: #dcfce7;
        }

        .status.cancelled {
            color: #854d0e;
            background: #fef9c3;
        }

        .status.error {
            color: #991b1b;
            background: #fee2e2;
        }

        .message {
            margin: 14px 0 0;
            color: #6b7280;
            text-align: center;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .receipt {
                border: 0;
            }
        }
    </style>
</head>
<body>
    <main class="receipt">
        <header class="header">
            <?php if (is_string($logo) && $logo !== ''): ?>
                <img
                    class="logo"
                    src="<?= $escape($logo) ?>"
                    alt="<?= $escape($companyName) ?>"
                >
            <?php endif ?>

            <h1>Comprovativo de pagamento</h1>

            <p class="company">
                <?= $escape($companyName) ?>
            </p>
        </header>

        <?php if ($receipt['amount'] !== null): ?>
            <div class="amount">
                <?= $escape($receipt['amount']) ?>
                <?= $escape($receipt['currency']) ?>
            </div>
        <?php endif ?>

        <dl class="details">
            <div class="row">
                <dt>Referência</dt>
                <dd>
                    <?= $escape(
                        $receipt['merchantReference'] ?? 'N/A'
                    ) ?>
                </dd>
            </div>

            <div class="row">
                <dt>Transação</dt>
                <dd>
                    <?= $escape(
                        $receipt['transactionId'] ?? 'N/A'
                    ) ?>
                </dd>
            </div>

            <div class="row">
                <dt>Tipo</dt>
                <dd>
                    <?= $escape(
                        $receipt['transactionType'] ?? 'N/A'
                    ) ?>
                </dd>
            </div>

            <div class="row">
                <dt>Data</dt>
                <dd>
                    <?= $escape(
                        $receipt['timestamp'] ?? 'N/A'
                    ) ?>
                </dd>
            </div>

            <?php if (!empty($receipt['maskedPan'])): ?>
                <div class="row">
                    <dt>Cartão</dt>
                    <dd>
                        <?= $escape($receipt['maskedPan']) ?>
                    </dd>
                </div>
            <?php endif ?>

            <?php if (!empty($receipt['authorizationCode'])): ?>
                <div class="row">
                    <dt>Autorização</dt>
                    <dd>
                        <?= $escape(
                            $receipt['authorizationCode']
                        ) ?>
                    </dd>
                </div>
            <?php endif ?>
        </dl>

        <div class="status <?= $escape($statusClass) ?>">
            <?= $escape($statusText) ?>
        </div>

        <p class="message">
            <?= $escape($receipt['message'] ?? '') ?>
        </p>
    </main>
</body>
</html>