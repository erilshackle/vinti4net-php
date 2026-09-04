<?php

declare(strict_types=1);

namespace Eril\Sisp\Receipt;

use Eril\Sisp\Exception\ReceiptException;
use Eril\Sisp\Vinti4Response;
use Throwable;

final class ReceiptRenderer
{
    public function __construct(
        private readonly Vinti4Response $response,
    ) {}

    /**
     * Render the default minimal receipt.
     */
    public function renderDefault(
        ?string $companyName = null,
        ?string $logo = null,
    ): string {
        return $this->render(
            template: dirname(__DIR__, 2) . '/resources/views/default-receipt.php',
            data: [
                'companyName' => $companyName,
                'logo' => $logo,
            ],
        );
    }

    /**
     * Render a receipt using a PHP or HTML template.
     *
     * PHP templates receive the variables $receipt and $data.
     * HTML templates support escaped {{ field }} placeholders.
     *
     * @param array<string, mixed> $data
     *
     * @throws ReceiptException
     */
    public function render(
        string $template,
        array $data = [],
    ): string {
        $template = trim($template);

        if ($template === '' || !is_file($template)) {
            throw new ReceiptException(
                "Template de recibo não encontrado: {$template}"
            );
        }

        $extension = strtolower(
            pathinfo($template, PATHINFO_EXTENSION)
        );

        return match ($extension) {
            'php' => $this->renderPhp($template, $data),
            'html', 'htm' => $this->renderHtml($template, $data),

            default => throw new ReceiptException(
                'O template de recibo deve ser PHP, HTML ou HTM.'
            ),
        };
    }

    /**
     * Render a PHP receipt template.
     *
     * @param array<string, mixed> $data
     */
    private function renderPhp(
        string $template,
        array $data,
    ): string {
        $receipt = $this->response->receiptData();
        $bufferLevel = ob_get_level();

        ob_start();

        try {
            require $template;

            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            throw new ReceiptException(
                'Não foi possível renderizar o recibo.',
                previous: $exception,
            );
        }
    }

    /**
     * Render an HTML template using escaped placeholders.
     *
     * @param array<string, mixed> $data
     */
    private function renderHtml(
        string $template,
        array $data,
    ): string {
        $html = file_get_contents($template);

        if ($html === false) {
            throw new ReceiptException(
                'Não foi possível ler o template de recibo.'
            );
        }

        $values = [
            ...$data,
            ...$this->response->receiptData(),
        ];

        return preg_replace_callback(
            '/{{\s*([A-Za-z0-9_.-]+)\s*}}/',
            function (array $match) use ($values): string {
                $value = $this->placeholder(
                    $values,
                    $match[1],
                );

                if ($value === null || is_array($value)) {
                    return '';
                }

                return $this->escape((string) $value);
            },
            $html,
        ) ?? $html;
    }

    /**
     * Resolve a placeholder using dot notation.
     *
     * @param array<string, mixed> $values
     */
    private function placeholder(
        array $values,
        string $path,
    ): mixed {
        $value = $values;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Escape a value for HTML output.
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
