# Referência da API

## `Vinti4Net`

```php
new Vinti4Net(string $posId, string $authCode, ?string $endpoint = null);
```

Métodos públicos principais:

```php
$vinti4->purchase(...): TransactionRequest;
$vinti4->servicePayment(...): TransactionRequest;
$vinti4->recharge(...): TransactionRequest;
$vinti4->refund(...): TransactionRequest;
$vinti4->processResponse(array $payload): Vinti4Response;
```

## `TransactionRequest`

```php
$request->form(string $returnUrl, string $lang = 'pt'): string;
$request->send(string $returnUrl, string $lang = 'pt'): never;
```

## `Billing`

```php
Billing::make(): Billing;
Billing::from(array $data): Billing;
$billing->toArray(): array;
```

## `Vinti4Response`

```php
$response->status(): string;
$response->message(): string;
$response->isValid(): bool;
$response->isSuccess(): bool;
$response->isCancelled(): bool;
$response->hasFailed(): bool;
$response->hasInvalidFingerprint(): bool;
$response->transactionId(): ?string;
$response->clearingPeriod(): ?string;
$response->merchantReference(): ?string;
$response->merchantSession(): ?string;
$response->amount(): ?string;
$response->currency(): ?string;
$response->transactionType(): ?string;
$response->toArray(): array;
$response->toJson(): string;
```

## Exceções

Todas descendem de `Eril\Sisp\Exception\Vinti4Exception`:

- `InvalidConfigurationException`;
- `InvalidRequestException`;
- `InvalidResponseException`;
- `ReceiptException`.
