# Changelog

All notable changes to this project will be documented in this file.


## [2.0.0] - 2025-11-21

### Changed
- refactor: change project namespace from Erilshk\Vinti4Net to Erilshk\Sisp;
- changelog

## [1.1.1] - 2025-11-23

### Added
- feat: add billing parameters and improve receipt rendering logic
- refactor: enhance Refund and Vinti4Response classes with additional fields and validation

### Changed
- refactor: update namespace and method names for consistency; remove deprecated billing parameters
- delete: teste.php from examples
- 
## [1.0.0] - 2025-11-18

### Added
- Update .gitignore to include additional files and directories for exclusion
- Add initial search index, sitemap, and tags configuration files
- Update .gitattributes to exclude additional directories and files from Composer package
- Enhance Billing class to support additional fields and improve data mapping in fill method
- Add detailed documentation and enhance Billing class with new features and improvements
- Add comprehensive documentation and assets for Vinti4Net PHP SDK

### Changed
- Replace favicon with new icon and remove unused image asset
- Refactor Vinti4Net class documentation for clarity and completeness
- Enhance Billing and Vinti4Response classes with detailed PHPDoc comments for better code documentation and clarity
- update Vinti4Net.php
- Delete Vinti4NetLegacy.php
- update Vinti4NetLegacy.php
- Merge branch 'main' of https://github.com/erilshackle/vinti4net-php
- chore: edit gitattributes and cgangelog

## [Unreleased] - 2025-11-16

### Added
- chore(ci/docs): rename workflow to ci.yml, update composer name and add README badges
- Add ParamsValidatorTrait and refactor SISP validation
- feat: add PHP 8+ support and refactor payment core

### Changed
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

