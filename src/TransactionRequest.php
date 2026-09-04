<?php

declare(strict_types=1);

namespace Eril\Sisp;

use Eril\Sisp\Core\Sisp;
use Eril\Sisp\Exception\InvalidRequestException;
use RuntimeException;

final class TransactionRequest
{
    /**
     * Create a prepared Vinti4Net transaction.
     *
     * @param array<string, mixed> $params
     */
    public function __construct(
        private readonly Sisp $processor,
        private readonly array $params,
    ) {}

    /**
     * Generate the auto-submit HTML payment form.
     */
    public function form(
        string $returnUrl,
        string $lang = 'pt',
    ): string {
        $lang = strtolower(trim($lang));

        $request = $this->processor->preparePayment([
            ...$this->params,
            'urlMerchantResponse' => $returnUrl,
            'languageMessages' => $lang,
        ]);

        $action = $request['postUrl'] ?? null;
        $fields = $request['fields'] ?? null;

        if (
            !is_string($action) ||
            $action === '' ||
            !is_array($fields) ||
            $fields === []
        ) {
            throw new InvalidRequestException(
                'Não foi possível gerar o formulário de pagamento.'
            );
        }

        return $this->renderForm(
            action: $action,
            fields: $fields,
            lang: $lang,
        );
    }

    /**
     * Output the auto-submit payment form and terminate execution.
     */
    public function send(
        string $returnUrl,
        string $lang = 'pt',
    ): never {
        echo $this->form($returnUrl, $lang);

        exit;
    }

    /**
     * Render the complete auto-submit HTML document.
     *
     * @param array<string, mixed> $fields
     */
    private function renderForm(
        string $action,
        array $fields,
        string $lang,
    ): string {
        $inputs = [];

        foreach ($fields as $name => $value) {
            if (is_array($value)) {
                continue;
            }

            $inputs[] = sprintf(
                '<input type="hidden" name="%s" value="%s">',
                $this->escape((string) $name),
                $this->escape((string) $value),
            );
        }

        $action = $this->escape($action);
        $langAttribute = $this->escape($lang);
        $message = $this->escape($this->processingMessage($lang));
        $hiddenInputs = implode("\n                ", $inputs);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="{$langAttribute}">
        <head>
            <meta charset="UTF-8">
            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >
            <title>Vinti4Net</title>
        </head>
        <body>
            <form id="vinti4net-payment" method="post" action="{$action}">
                {$hiddenInputs}
                <noscript>
                    <button type="submit">{$message}</button>
                </noscript>
            </form>

            <p>{$message}</p>

            <script>
                document.getElementById('vinti4net-payment').submit();
            </script>
        </body>
        </html>
        HTML;
    }

    /**
     * Return the processing message for the selected language.
     */
    private function processingMessage(string $lang): string
    {
        return match ($lang) {
            'pt' => 'A processar pagamento...',
            'fr' => 'Traitement du paiement...',
            default => 'Processing payment...',
        };
    }

    /**
     * Escape a value for safe HTML output.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        );
    }
}
