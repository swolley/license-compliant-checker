# license-compliance-checker

[![Tests](https://github.com/swolley/license-compliance-checker/actions/workflows/tests.yml/badge.svg)](https://github.com/swolley/license-compliance-checker/actions/workflows/tests.yml)

Check Composer and npm dependency licenses against your project license.

- **Repository:** [github.com/swolley/license-compliance-checker](https://github.com/swolley/license-compliance-checker)
- **Issues:** [github.com/swolley/license-compliance-checker/issues](https://github.com/swolley/license-compliance-checker/issues)

## Installation

```bash
composer require swolley/license-compliance-checker --dev
```

## Usage

Run from your project root (e.g. where `composer.json` and `composer.lock` are):

```bash
./vendor/bin/check-licenses
```

Or with a custom path:

```bash
./vendor/bin/check-licenses --path=/path/to/your/project
```

### Composer script

Add to your project's `composer.json`:

```json
{
  "scripts": {
    "test:licenses": "@php vendor/bin/check-licenses"
  }
}
```

Then:

```bash
composer test:licenses
```

## License matrix (config)

The checker looks for a license matrix file in this order:

1. `project/scripts/license-compliance.php`
2. `project/config/license-compliance.php`
3. Package default: `vendor/swolley/license-compliance-checker/config/license-compliance.php`

To customize which dependency licenses are allowed for your project license, add `scripts/license-compliance.php` or `config/license-compliance.php` in your project (see the package `config/license-compliance.php` for structure and syntax: exact SPDX id, wildcards like `BSD-*`, version constraints like `GPL->=3.0`, and `@group` references).

## Requirements

- PHP 8.2+
- For npm dependency checks: Node.js and `npx` (the script runs `npx license-checker` when `package.json` and `node_modules` exist)

## Development

```bash
git clone https://github.com/swolley/license-compliance-checker.git
cd license-compliance-checker
composer install
composer test
composer run test:coverage       # coverage report
composer run test:coverage:min   # fail if coverage < 100%
```

To release a new version, tag with a semantic version (e.g. `v1.0.0`) and push; Packagist will pick it up from the repository.

## License

MIT
