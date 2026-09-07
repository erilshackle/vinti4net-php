# Changelog

All notable changes to this project are documented in this file.


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
