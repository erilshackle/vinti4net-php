# Changelog

All notable changes to this project are documented in this file.

## [2.4.0] - 2026-10-04

### Added

- `Billing::account()` with named account parameters; address matching also accepts SISP `Y`/`N` strings.

- `Currency` string constants and `Currency::toNumeric()` for ISO 4217 name-to-code conversion.
- `Entity` integer constants for service payments and recharges, with `Entity::all()` listing names and codes and filtering by category.
- A Composer-free integration in `dist/standalone.php` for PHP 8.1+.
- Regression tests for signed errors, unsigned cancellations, injected cancellation flags, currency conversion, entities and receipt rendering.

### Fixed

- Preserve legacy billing country and account fields when merging explicit inputs.
- Stop inventing account-age/password-age indicators or treating profile updates as password changes.
- Normalize address matching and copy billing fields to shipping for `addrMatch=Y`.
- Validate required billing fields, email and nested account ID before generating `purchaseRequest`, without restricting optional field formats.
- Apply documented billing line 2 and unknown postal-code fallbacks.

- Validate error callbacks (`messageType=6`) with the dedicated error fingerprint formula. Missing or invalid fingerprints now produce `INVALID_FINGERPRINT`.
- Prevent `UserCancelled` from overriding a signed success or error callback. Cancellation is recognized without `messageType`, using `merchantRef`, `merchantSession` and `UserCancelled`.

### Changed

- The default purchase currency is now `Currency::CVE` (`'132'`). The gateway payload is unchanged, but `getRequest()['currency']` now contains `'132'` instead of `'CVE'` when no currency is supplied.
- Expanded currency, entity and callback security documentation.

### Deprecated

- `Vinti4Response::getCurrency()`: documented callbacks do not return the order currency. Use the stored order currency, or `dcc['currency']` for the DCC currency.

### Compatibility

- PHP remains `^8.1`. Currency arguments remain strings and entity arguments remain integers.
- Existing payment methods remain available. Currency constants do not imply support by the gateway; the documented SISP protocol uses CVE.
- Applications must check `hasInvalidFingerprint()` before acting on errors as well as successful transactions.


## [2.3.0] - 2026-09-14

### Added

- `Vinti4Net::generateMerchantRef()` as a 15-character timestamp-based + 2 sufix reference helper.
- `Vinti4Response::renderRefundReceipt()` and a dedicated refund receipt template.
- `Vinti4Response::isDccEnabled()` and `getClearingPeriod()` helpers.
- Expanded payment, refund, DCC and receipt documentation.

### Changed

- Merchant reference generation now includes randomness to reduce collisions between transactions created in the same second.
- Merchant references and sessions must contain exactly 15 characters. Applications that previously supplied a shorter reference must update their reference generation.
- Refund receipts take the original amount from the merchant application because the provider may return zero.


## [2.2.1] - 2026-09-06

### Added

- `Billing::from()` and friendly aliases for normalized billing data.
- Shipping address, account information and safe phone normalization.
- `Vinti4Exception` as the single library exception in the v2 line.
- `Vinti4Response::hasFailed()`.
- `Vinti4Response::renderReceipt()` with default, PHP, HTML and HTM templates.
- `Vinti4Response::renderDccReceipt()` using the SISP DCC receipt format.
- Default receipt templates under `src/Receipt/templates`.

### Changed

- Reworked request amount conversion without float arithmetic or BCMath.
- Normalized purchase billing while preserving explicit values over legacy user data.
- Improved validation for merchant identifiers, amounts, currencies, URLs, languages and refunds.
- Preserved merchant reference and session when preparing a transaction.
- Improved callback status normalization and refund response detection.
- Updated form escaping and receipt rendering security.
- Updated CI to cover PHP 8.1, 8.2, 8.3 and 8.4.
- Changed PHPUnit to `^10.5` for PHP 8.1 compatibility.

### Fixed

- Fixed incorrect `YmdHms` timestamps.
- Fixed undefined request data and the hard-coded localhost callback in `getRequest()`.
- Fixed billing phone arrays that could cause type errors.
- Fixed billing fields being discarded during normalization.
- Fixed invalid fingerprints being reported as cancelled transactions.
- Fixed unsafe raw values in generated payment forms.
- Fixed full PAN exposure from `toArray()` and `toJson()`.
- Fixed DCC markup being displayed as a percentage.
- Fixed DCC values being reformatted or recalculated by receipt rendering.

### Compatibility

- Existing v2 payment methods remain supported and are not deprecated.
- `generateReceiptHtml()` and `generateReceiptText()` remain available.
- Legacy `Billing` aliases remain available where a direct v2.2 replacement exists.

## [2.1.0] - 2025-11-30

### Added

- HTML and plain-text transaction receipts.
- Additional response and billing test coverage.
- Merchant data configuration through `setMerchant()`.

### Changed

- Improved receipt rendering, response handling and documentation.

## [2.0.0] - 2025-11-30

### Added

- PHP 8 API for purchases, service payments, recharges and refunds.
- `Billing` and `Vinti4Response` abstractions.
- Unified request validation and fingerprint processing.
- Project documentation and test suite.

### Changed

- Namespace changed to `Erilshk\Sisp`.
- Minimum PHP version updated to PHP 8.1.

## [1.1.1] - 2025-11-13

### Fixed

- Initial maintenance fixes for the original integration.

## [1.0.0] - 2025-11-13

### Added

- Initial Vinti4Net PHP integration.
