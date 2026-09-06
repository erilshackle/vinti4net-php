<?php

declare(strict_types=1);

namespace Erilshk\Sisp\Receipt;

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Response;

/** Renders default, custom and DCC receipts. */
final class Receipt
{
    /** Create a receipt renderer for a processed response. */
    public function __construct(private readonly Vinti4Response $response)
    {
    }

    /**
     * Render the default receipt or a custom PHP/HTML template.
     *
     * @param string|null $template Absolute path to a .php, .html or .htm template.
     * @param array<string, mixed> $data Custom template data.
     *
     * @throws Vinti4Exception
     */
    public function render(?string $template = null, array $data = []): string
    {
        $template ??= __DIR__ . '/templates/default-receipt.php';

        return $this->renderTemplate($template, $data);
    }

    /**
     * Render the official DCC receipt using values returned by SISP.
     *
     * @param array<string, mixed> $data Custom template data.
     *
     * @throws Vinti4Exception
     */
    public function renderDcc(array $data = []): string
    {
        $dcc = $this->response->dcc;

        foreach (['amount', 'currency', 'markup', 'rate'] as $field) {
            if (($dcc['enabled'] ?? false) !== true || !isset($dcc[$field]) || $dcc[$field] === '') {
                throw new Vinti4Exception('A resposta não contém dados DCC completos.');
            }
        }

        return $this->renderTemplate(
            __DIR__ . '/templates/default-dcc-receipt.php',
            $data,
        );
    }

    /**
     * Render a plain-text transaction receipt for storage or email.
     *
     * @param array<string, mixed> $data Custom receipt data.
     */
    public function renderText(array $data = []): string
    {
        $receipt = $this->receiptData();
        $lines = [
            '==== RECIBO DE TRANSAÇÃO ====',
            'Empresa: ' . ($data['companyName'] ?? 'Comerciante/Entidade'),
            'Data/Hora: ' . ($receipt['timestamp'] ?: 'N/A'),
            'Status: ' . ($this->response->isSuccess() ? 'APROVADA' : 'NÃO CONCLUÍDA'),
            'Mensagem: ' . $this->response->message,
            '',
            'Transação ID: ' . ($receipt['transactionId'] ?: 'N/A'),
            'Referência: ' . ($receipt['merchantReference'] ?: 'N/A'),
            'Valor: ' . ($receipt['amount'] ?? 'N/A') . ' ' . $receipt['currency'],
        ];

        if ($receipt['pan'] !== '') {
            $lines[] = 'Cartão: ' . $receipt['pan'];
        }

        if (($receipt['dcc']['enabled'] ?? false) === true) {
            $lines[] = '';
            $lines[] = '=== DCC (Moeda Estrangeira) ===';
            $lines[] = 'Taxa de conversão: 1 ' . $receipt['dcc']['currency'] . ' = '
                . $receipt['dcc']['rate'] . ' CVE';
            $lines[] = 'Taxa do serviço DCC: ' . $receipt['dcc']['markup'] . ' '
                . $receipt['dcc']['currency'];
            $lines[] = 'Total DCC: ' . $receipt['dcc']['amount'] . ' '
                . $receipt['dcc']['currency'];
        }

        $lines[] = '';
        $lines[] = '===========================';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Render a supported template.
     *
     * @param array<string, mixed> $data
     */
    private function renderTemplate(string $template, array $data): string
    {
        if (!is_file($template) || !is_readable($template)) {
            throw new Vinti4Exception("O template de recibo não foi encontrado: {$template}");
        }

        $extension = strtolower((string) pathinfo($template, PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => $this->renderPhpTemplate($template, $data),
            'html', 'htm' => $this->renderHtmlTemplate($template, $data),
            default => throw new Vinti4Exception('O template deve utilizar a extensão .php, .html ou .htm.'),
        };
    }

    /**
     * Render a PHP template with isolated receipt variables.
     *
     * @param array<string, mixed> $data
     */
    private function renderPhpTemplate(string $template, array $data): string
    {
        $receipt = $this->receiptData();

        try {
            ob_start();
            include $template;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            throw new Vinti4Exception('Não foi possível renderizar o recibo.', 0, $exception);
        }
    }

    /**
     * Replace escaped placeholders in an HTML template.
     *
     * @param array<string, mixed> $data
     */
    private function renderHtmlTemplate(string $template, array $data): string
    {
        $html = file_get_contents($template);
        if ($html === false) {
            throw new Vinti4Exception('Não foi possível ler o template de recibo.');
        }

        $values = array_replace_recursive($data, $this->receiptData());

        return (string) preg_replace_callback(
            '/{{\s*([a-zA-Z0-9_.]+)\s*}}/',
            fn(array $match): string => $this->escape($this->value($values, $match[1])),
            $html,
        );
    }

    /** @return array<string, mixed> */
    private function receiptData(): array
    {
        $raw = $this->response->data;

        return [
            'status' => $this->response->status,
            'success' => $this->response->success,
            'message' => $this->response->message,
            'detail' => $this->response->detail,
            'transactionId' => (string) ($raw['merchantRespTid'] ?? ''),
            'merchantReference' => (string) ($raw['merchantRespMerchantRef'] ?? ''),
            'merchantSession' => (string) ($raw['merchantRespMerchantSession'] ?? ''),
            'amount' => $raw['merchantRespPurchaseAmount'] ?? null,
            'currency' => $this->currency((string) ($raw['merchantRespCurrency'] ?? 'CVE')),
            'timestamp' => (string) ($raw['merchantRespTimeStamp'] ?? ''),
            'authorizationCode' => (string) ($raw['merchantRespMessageID'] ?? ''),
            'entityCode' => (string) ($raw['merchantRespEntityCode'] ?? ''),
            'referenceNumber' => (string) ($raw['merchantRespReferenceNumber'] ?? ''),
            'reloadCode' => (string) ($raw['merchantRespReloadCode'] ?? ''),
            'pan' => $this->maskPan((string) ($raw['merchantRespPan'] ?? '')),
            'dcc' => $this->response->dcc,
        ];
    }

    /** Resolve a dot-notated template value. */
    private function value(array $values, string $path): mixed
    {
        $value = $values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return '';
            }

            $value = $value[$segment];
        }

        return is_scalar($value) || $value === null ? $value : '';
    }

    /** Convert the numeric CVE code for display. */
    private function currency(string $currency): string
    {
        return $currency === '132' || $currency === '' ? 'CVE' : $currency;
    }

    /** Mask a PAN while preserving the first six and last four digits. */
    private function maskPan(string $pan): string
    {
        $digits = preg_replace('/\D+/', '', $pan) ?? '';
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) < 10) {
            return str_repeat('•', strlen($digits));
        }

        return substr($digits, 0, 6)
            . str_repeat('•', strlen($digits) - 10)
            . substr($digits, -4);
    }

    /** Escape a template value for HTML. */
    private function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
