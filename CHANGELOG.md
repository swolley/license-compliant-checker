# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-02-27

### Added

- CLI `check-licenses` to verify Composer and npm dependency licenses against the project license.
- Config lookup order: `project/scripts/license-compliance.php`, `project/config/license-compliance.php`, package default config.
- License matrix syntax: exact SPDX ids, wildcards (`BSD-*`), version constraints (`GPL->=3.0`), and `@group` references.
- Project license detection from `composer.json`, `package.json`, or `LICENSE` file (AGPL, GPL, MIT, BSD 2/3/4-Clause, Apache 1.0/1.1/2.0, proprietary, CC0, CC-BY variants).
- Composer dependencies from `composer.lock` (packages and packages-dev).
- npm dependencies via `npx license-checker` when `package.json` and `node_modules` exist.
- Optional project config to allow only compatible dependency licenses per project license.
- Exit codes: 0 (OK), 1 (some dependencies not allowed), 2 (error: invalid path, no config, no project license).
- Test suite (Pest) with 100% code coverage.

[Unreleased]: https://github.com/swolley/license-compliance-checker/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/swolley/license-compliance-checker/releases/tag/v1.0.0
