# Changelog

All notable changes to this project will be documented in this file.


## [v2.1.0] - 2025-11-30

### Added
- feat(receipts): improve rendering logic and add comprehensive tests
- docs: update documentation pages and add new API & receipt sections

### Changed
- test(vinti4): update response test data to use messageType
- docs(changelog): prepare v2.1.0 release notes

## [2.1.0] - 2025-11-30

### Added
- feat(receipt): add plain text receipt and handle failures
- docs(composer): add feature details to package description
- test(unit): add validation and formatting test cases
- test(vinti4net): add coverage for setMerchant and createPaymentForm
- fix(refund): add default value for merchantRef

### Changed
- docs(changelog): update and consolidate for v2.1.0 release
- docs(readme): refine content and update badges
- test: enhance unit test coverage for core logic
- style(billing): apply standard code formatting

## [2.0.0] - 2025-11-30

### Added
- chore: add readme field to composer.json
- chore: update changelog for version 2.0.0 and 1.1.1, add enhancements and changes
- Add language option to payment form example
- Enhance receipt generation with additional transaction types
- feat: add billing parameters and improve receipt rendering logic
- refactor: enhance Refund and Vinti4Response classes with additional fields and validation
- Update .gitignore to include additional files and directories for exclusion
- Add initial search index, sitemap, and tags configuration files
- Update .gitattributes to exclude additional directories and files from Composer package
- Enhance Billing class to support additional fields and improve data mapping in fill method
- Add detailed documentation and enhance Billing class with new features and improvements
- Add comprehensive documentation and assets for Vinti4Net PHP SDK
- chore(ci/docs): rename workflow to ci.yml, update composer name and add README badges
- Add ParamsValidatorTrait and refactor SISP validation
- feat: add PHP 8+ support and refactor payment core
- feat: initial commit - Vinti4Net PHP SDK

### Fixed
- chore: update changelog and fix payment example and tests for refund method
- Fix documentation link in README.md
- Fix method call in error handling example

### Changed
- chore: update changelog for version 2.0.0 and modify installation section in README
- chore: update changelog versioning and enhance composer.json keywords
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- chore: update changelog for version 2.0.1, enhance README, and refactor Refund class validation
- Delete .github/FUNDING.yml
- Update funding options in FUNDING.yml
- Update funding sources in FUNDING.yml
- Update issue templates
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- Correct link formatting in README.md
- Enhance README with hyperlinks for SISP and documentation
- Update transaction methods and database reference
- Update namespace import in README.md
- update docs
- Update Documentation to reflect recent changes in project structure
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- Refactor payment methods in README.md
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- chore: update changelog for version 2.0.0 and 1.1.1 changes
- Delete examples/teste.php
- Refactor fingerprint methods and update payment params
- refactor: update namespace and method names for consistency; remove deprecated billing parameters
- delete: teste.php from examples
- Update export-ignore rules in .gitattributes
- Update .gitattributes to include mkdocs and teste.php
- refactor: change project namespace from Erilshk\Vinti4Net to Erilshk\Sisp;
- changelog
- Replace favicon with new icon and remove unused image asset
- Refactor Vinti4Net class documentation for clarity and completeness
- Enhance Billing and Vinti4Response classes with detailed PHPDoc comments for better code documentation and clarity
- update Vinti4Net.php
- Delete Vinti4NetLegacy.php
- update Vinti4NetLegacy.php
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- chore: edit gitattributes and cgangelog
- Update README.md
- Update README.md
- Update README.md
- Update project to PHP version ^8.1
- Update project, composer to 8.0 and Refator Vinti4Response class removing readonly
- RefundException Test
- test coverage for  Vinti4Response
- test coverage for ReceiptRednderer
- update readme
- docs: adicionar CONTRIBUTING.md, atualizar README com badges e foto
- Update README.md
- Update ci.yml
- Update ci.yml
- update ci
- - name: Upgrade Composer to v2   run: composer self-update --2
- Update and rename readme to README.md
- - name: Run tests with coverage   run: php vendor/bin/phpunit tests/Unit --coverage-clover=coverage.xml
- ci: update workflow to PHP 8.2 and adjust badges in README
- Update tests.yml
- Create tests.yml
- refactor: unify param validation in Sisp and improve billing handling
- edit: rename namespace for ValidatorParamTrait
- Refactor Payment and Refund classes for SISP integration

## [Start] - 2025-11-13

### Added
- feat: initial commit - Vinti4Net PHP SDK

