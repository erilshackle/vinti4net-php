<?php

declare(strict_types=1);

/**
 * Official SISP Dynamic Currency Conversion receipt.
 *
 * Available variables:
 *
 * @var array<string, mixed> $receipt Normalized transaction receipt data.
 * @var array{
 *     enabled: bool,
 *     amount: string|null,
 *     currency: string|null,
 *     rate: string|null,
 *     markup: string|null
 * } $dcc Normalized DCC information.
 */

$escape = static function (mixed $value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8',
    );
};

$amount = $receipt['amount'] ?? '';
$baseCurrency = $receipt['currency'] ?? 'CVE';

$dccAmount = $dcc['amount'] ?? '';
$dccCurrency = $dcc['currency'] ?? '';
$dccRate = $dcc['rate'] ?? '';
$dccMarkup = $dcc['markup'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Recibo DCC</title>

    <style>
        :root {
            color-scheme: light;

            --receipt-background: #f4f4f4;
            --receipt-border: #b9b9b9;
            --receipt-text: #111111;
            --receipt-muted: #3f3f3f;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            color: var(--receipt-text);
            background: #ffffff;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 14px;
            line-height: 1.45;
        }

        .dcc-receipt {
            width: min(100%, 760px);
            margin: 24px auto;
            padding: 18px;

            background: var(--receipt-background);
            border: 1px solid var(--receipt-border);
        }

        .dcc-receipt__title {
            margin: 0 0 22px;

            font-size: 18px;
            font-weight: 700;
            text-align: center;
        }

        .dcc-receipt__details {
            display: grid;
            gap: 14px;

            margin: 0;
        }

        .dcc-receipt__row {
            display: grid;
            grid-template-columns: minmax(250px, 1.4fr) minmax(180px, 1fr);
            gap: 24px;
            align-items: baseline;
        }

        .dcc-receipt__label,
        .dcc-receipt__value {
            margin: 0;
        }

        .dcc-receipt__label {
            font-weight: 400;
        }

        .dcc-receipt__value {
            font-weight: 400;
        }

        .dcc-receipt__totals {
            display: grid;
            gap: 12px;

            margin-top: 34px;
        }

        .dcc-receipt__total {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 24px;

            margin: 0;
        }

        .dcc-receipt__choice {
            font-weight: 700;
        }

        .dcc-receipt__notices {
            display: grid;
            gap: 12px;

            margin-top: 20px;
        }

        .dcc-receipt__notice {
            margin: 0;
        }

        .dcc-receipt__notice--secondary {
            color: var(--receipt-muted);
        }

        @media (max-width: 600px) {
            body {
                font-size: 13px;
            }

            .dcc-receipt {
                margin: 0;
                padding: 16px;

                border-right: 0;
                border-left: 0;
            }

            .dcc-receipt__row {
                grid-template-columns: 1fr;
                gap: 4px;
            }

            .dcc-receipt__total {
                grid-template-columns: 100px 1fr;
                gap: 12px;
            }
        }

        @media print {
            @page {
                margin: 12mm;
            }

            body {
                background: #ffffff;
            }

            .dcc-receipt {
                width: 100%;
                margin: 0;

                background: #ffffff;
                border-color: #777777;
                box-shadow: none;
            }
        }
    </style>
</head>

<body>
    <main
        class="dcc-receipt"
        aria-labelledby="dcc-receipt-title">
        <h1
            id="dcc-receipt-title"
            class="dcc-receipt__title">
            Recibo
        </h1>

        <section
            class="dcc-receipt__details"
            aria-label="Informações de conversão de moeda">
            <div class="dcc-receipt__row">
                <p class="dcc-receipt__label">
                    Taxa de conversão \
                    Currency Conversion Rate:
                </p>

                <p class="dcc-receipt__value">
                    1 <?= $escape($dccCurrency) ?>
                    =
                    [<?= $escape($dccRate) ?>]
                    <?= $escape($baseCurrency) ?>
                </p>
            </div>

            <div class="dcc-receipt__row">
                <p class="dcc-receipt__label">
                    Taxa do serviço DCC \
                    DCC Markup:
                </p>

                <p class="dcc-receipt__value">
                    [<?= $escape($dccMarkup) ?>]
                    [<?= $escape($dccCurrency) ?>]
                </p>
            </div>

            <div class="dcc-receipt__row">
                <p class="dcc-receipt__label">
                    Moeda da Transação \
                    Transaction Currency:
                </p>

                <p class="dcc-receipt__value">
                    [<?= $escape($dccCurrency) ?>]
                </p>
            </div>
        </section>

        <section
            class="dcc-receipt__totals"
            aria-label="Valor da transação">
            <p class="dcc-receipt__total">
                <span>[ ] TOTAL:</span>

                <span>
                    [<?= $escape($amount) ?>]
                    <?= $escape($baseCurrency) ?>
                </span>
            </p>

            <p class="dcc-receipt__total">
                <span class="dcc-receipt__choice">
                    [x] TOTAL:
                </span>

                <span class="dcc-receipt__choice">
                    [<?= $escape($dccAmount) ?>]
                    [<?= $escape($dccCurrency) ?>]
                </span>
            </p>
        </section>

        <section
            class="dcc-receipt__notices"
            aria-label="Condições da conversão">
            <p class="dcc-receipt__notice">
                I have been offered choice of currencies and agreed
                to pay in [<?= $escape($dccCurrency) ?>].
            </p>

            <p class="dcc-receipt__notice">
                Dynamic Currency Conversion (DCC) offered by rede vinti4.
            </p>

            <p class="dcc-receipt__notice dcc-receipt__notice--secondary">
                Exchange rate provided by Banco de Cabo Verde.
            </p>
        </section>
    </main>
</body>

</html>