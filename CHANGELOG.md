# Changelog

All notable changes to this project will be documented in this file.

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

## [1.0.0] - 2025-11-13

### Added
- feat: initial commit - Vinti4Net PHP SDK

