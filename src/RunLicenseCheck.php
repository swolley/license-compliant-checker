<?php

declare(strict_types=1);

/**
 * Expand @group references and return flat list of allowed patterns.
 *
 * @param  array<string>  $patterns
 * @param  array<string, array<string>>  $groups
 * @return array<string>
 */
function expand_allowed_patterns(array $patterns, array $groups): array
{
    $out = [];

    foreach ($patterns as $p) {
        $p = mb_trim($p);

        if ($p === '') {
            continue;
        }

        if (str_starts_with($p, '@')) {
            $name = mb_substr($p, 1);

            if (isset($groups[$name])) {
                foreach (expand_allowed_patterns($groups[$name], $groups) as $sub) {
                    $out[] = $sub;
                }
            }

            continue;
        }

        $out[] = $p;
    }

    return array_values(array_unique($out));
}

/**
 * Check if a single SPDX license identifier is allowed by any of the given patterns.
 *
 * @param  array<string>  $allowed_patterns
 * @param  array<string, array{0: string, 1: float}>  $license_families  SPDX => [family, version]
 */
function license_matches_patterns(string $license, array $allowed_patterns, array $license_families): bool
{
    $license = mb_trim($license);

    foreach ($allowed_patterns as $pattern) {
        if (str_ends_with($pattern, '-*')) {
            $prefix = mb_substr($pattern, 0, -1);

            if (str_starts_with($license, $prefix)) {
                return true;
            }

            continue;
        }

        if (preg_match('/^([A-Za-z0-9.-]+)-(>=|<=|>|<)([0-9.]+)$/', $pattern, $m)) {
            [, $family, $op, $ver_str] = $m;
            $constraint_ver = (float) $ver_str;

            if (! isset($license_families[$license])) {
                continue;
            }

            [$lic_family, $lic_ver] = $license_families[$license];

            if ($lic_family !== $family) {
                continue;
            }

            $match = match ($op) {
                '>=' => $lic_ver >= $constraint_ver,
                '<=' => $lic_ver <= $constraint_ver,
                '>' => $lic_ver > $constraint_ver,
                '<' => $lic_ver < $constraint_ver,
                default => false, // @codeCoverageIgnore
            };

            if ($match) {
                return true;
            }

            continue;
        }

        if ($license === $pattern) {
            return true;
        }
    }

    return false;
}

function colorize_check(string $symbol, string $green, string $red, string $reset): string
{
    if ($green === '') {
        return $symbol;
    }

    return $symbol === '✓' ? $green . '✓' . $reset : $red . '✗' . $reset;
}

/**
 * @param  array<int, array{name: string, version: string, licenses: string, allowed: bool}>  $rows
 * @param  resource  $stdout
 */
function print_table(array $rows, string $green, string $red, string $reset, $stdout, int $name_width = 44, int $version_width = 12, int $licenses_width = 44, int $check_width = 5): void
{
    $header = mb_str_pad('Name', $name_width) . '  ' . mb_str_pad('Version', $version_width) . '  ' . mb_str_pad('Licenses', $licenses_width) . '  ' . mb_str_pad('Check', $check_width);
    fwrite($stdout, $header . PHP_EOL);

    foreach ($rows as $row) {
        $check = colorize_check($row['allowed'] ? '✓' : '✗', $green, $red, $reset);
        fwrite($stdout, mb_str_pad(mb_substr($row['name'], 0, $name_width), $name_width)
            . '  '
            . mb_str_pad(mb_substr($row['version'], 0, $version_width), $version_width)
            . '  '
            . mb_str_pad(mb_substr($row['licenses'], 0, $licenses_width), $licenses_width)
            . '  '
            . $check
            . PHP_EOL);
    }
}

function is_license_allowed(string $license_expression, array $allowed_patterns, array $license_families): bool
{
    $license_expression = mb_trim($license_expression);

    if ($license_expression === '' || $license_expression === 'UNKNOWN') {
        return false;
    }

    if (license_matches_patterns($license_expression, $allowed_patterns, $license_families)) {
        return true;
    }

    if (str_contains($license_expression, ' AND ')) {
        foreach (array_map('trim', explode(' AND ', $license_expression)) as $part) {
            if (! license_matches_patterns($part, $allowed_patterns, $license_families)) {
                return false;
            }
        }

        return true;
    }

    if (str_contains($license_expression, ' OR ')) {
        foreach (array_map('trim', explode(' OR ', $license_expression)) as $part) {
            if (license_matches_patterns($part, $allowed_patterns, $license_families)) {
                return true;
            }
        }

        return false;
    }

    return false;
}

/**
 * Resolve config path: project scripts/, project config/, then package config.
 *
 * @return string|null path if readable, null otherwise
 */
function resolve_config_path(string $project_root, string $package_root): ?string
{
    $candidates = [
        $project_root . '/scripts/license-compliance.php',
        $project_root . '/config/license-compliance.php',
        $package_root . '/config/license-compliance.php',
    ];

    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Run npx license-checker and return decoded JSON or null.
 *
 * @return array<string, array{licenses: string|array<string>}>|null
 * @codeCoverageIgnore Exercised when npx is available; npm path tested via getter injection.
 */
function fetch_npm_license_data(string $project_root): ?array
{
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $p = proc_open(
        ['npx', '--yes', 'license-checker@27.0.1', '--json'],
        $descriptor,
        $pipes,
        $project_root,
        null,
        ['PATH' => getenv('PATH') ?: '/usr/bin', 'CI' => '1'],
    );

    if (! is_resource($p)) {
        return null;
    }

    fclose($pipes[0]);
    $stdout_npx = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($p);

    $npm_data = json_decode($stdout_npx, true);

    if (! is_array($npm_data)) {
        $p2 = proc_open(
            ['npx', '--yes', 'license-checker', '--json'],
            $descriptor,
            $pipes2,
            $project_root,
            null,
            ['PATH' => getenv('PATH') ?: '/usr/bin', 'CI' => '1'],
        );

        if (is_resource($p2)) {
            fclose($pipes2[0]);
            $stdout_npx = stream_get_contents($pipes2[1]);
            fclose($pipes2[1]);
            fclose($pipes2[2]);
            proc_close($p2);
            $npm_data = json_decode($stdout_npx, true);
        }
    }

    return is_array($npm_data) ? $npm_data : null;
}

/**
 * Run the license compliance check. Writes to $stdout and $stderr; returns exit code (0 = OK, 1 = fail, 2 = error).
 *
 * @param  resource  $stdout
 * @param  resource  $stderr
 * @param  (callable(string): ?array<string, mixed>)|null  $get_npm_license_data  When set, used instead of running npx (for testing).
 */
function run_license_check(string $project_root, string $config_path, $stdout = STDOUT, $stderr = STDERR, ?callable $get_npm_license_data = null): int
{
    $config = require $config_path;
    $compatibility = $config['compatibility'] ?? [];
    $license_families = $config['license_families'] ?? [];
    $groups = $config['groups'] ?? [];

    if ($compatibility === []) {
        fwrite($stderr, "Error: License matrix has no 'compatibility' array." . PHP_EOL);

        return 2;
    }

    $project_license = null;
    $composer_json_path = $project_root . '/composer.json';

    if (is_readable($composer_json_path)) {
        $data = json_decode((string) file_get_contents($composer_json_path), true);

        if (isset($data['license']) && is_string($data['license'])) {
            $project_license = mb_trim($data['license']);
        }
    }

    if ($project_license === null || $project_license === '') {
        $package_json_path = $project_root . '/package.json';

        if (is_readable($package_json_path)) {
            $data = json_decode((string) file_get_contents($package_json_path), true);

            if (isset($data['license']) && is_string($data['license'])) {
                $project_license = mb_trim($data['license']);
            }
        }
    }

    if ($project_license === null || $project_license === '') {
        $license_path = $project_root . '/LICENSE';

        if (is_readable($license_path)) {
            $license_head = mb_trim((string) file_get_contents($license_path, false, null, 0, 1024));

            if (mb_stripos($license_head, 'GNU AFFERO') !== false || mb_stripos($license_head, 'AGPL') !== false) {
                $project_license = 'AGPL-3.0-or-later';
            } elseif (mb_stripos($license_head, 'GNU GENERAL') !== false || mb_stripos($license_head, 'GPL') !== false) {
                $project_license = 'GPL-3.0-or-later';
            } elseif (mb_stripos($license_head, 'MIT') !== false) {
                $project_license = 'MIT';
            } elseif (mb_stripos($license_head, 'BSD') !== false) {
                if (mb_stripos($license_head, '2-Clause') !== false || mb_stripos($license_head, 'Simplified BSD') !== false) {
                    $project_license = 'BSD-2-Clause';
                } elseif (mb_stripos($license_head, '4-Clause') !== false || mb_stripos($license_head, 'Original BSD') !== false) {
                    $project_license = 'BSD-4-Clause';
                } else {
                    $project_license = 'BSD-3-Clause';
                }
            } elseif (mb_stripos($license_head, 'Apache') !== false) {
                if (mb_stripos($license_head, 'Version 1.0') !== false) {
                    $project_license = 'Apache-1.0';
                } elseif (mb_stripos($license_head, 'Version 1.1') !== false) {
                    $project_license = 'Apache-1.1';
                } else {
                    $project_license = 'Apache-2.0';
                }
            } elseif (mb_stripos($license_head, 'all rights reserved') !== false) {
                $project_license = 'proprietary';
            } elseif (mb_stripos($license_head, 'Creative Commons') !== false || mb_stripos($license_head, 'CC0') !== false) {
                if (mb_stripos($license_head, 'CC0') !== false || mb_stripos($license_head, 'Public Domain') !== false) {
                    $project_license = 'CC0-1.0';
                } elseif (mb_stripos($license_head, 'BY-NC-SA') !== false || mb_stripos($license_head, 'Attribution-NonCommercial-ShareAlike') !== false) {
                    $project_license = 'CC-BY-NC-SA-4.0';
                } elseif (mb_stripos($license_head, 'BY-NC ') !== false || (mb_stripos($license_head, 'Attribution-NonCommercial') !== false && mb_stripos($license_head, 'ShareAlike') === false)) {
                    $project_license = 'CC-BY-NC-4.0';
                } elseif (mb_stripos($license_head, 'BY-SA') !== false || mb_stripos($license_head, 'Attribution-ShareAlike') !== false) {
                    $project_license = 'CC-BY-SA-4.0';
                } elseif (mb_stripos($license_head, 'BY ') !== false || mb_stripos($license_head, 'Attribution') !== false) {
                    $project_license = 'CC-BY-4.0';
                } else {
                    $project_license = 'CC-BY-4.0';
                }
            }
        }
    }

    if ($project_license === null || $project_license === '') {
        fwrite($stderr, "Error: Could not detect project license. Set 'license' in composer.json or package.json, or use a standard LICENSE file." . PHP_EOL);

        return 2;
    }

    if (! isset($compatibility[$project_license])) {
        fwrite($stderr, "Error: No compatibility rules for project license: {$project_license}. Add it to your license matrix file." . PHP_EOL);

        return 2;
    }

    $allowed_patterns = expand_allowed_patterns($compatibility[$project_license], $groups);
    $has_errors = false;

    $use_color = getenv('NO_COLOR') === false
        && function_exists('posix_isatty')
        && posix_isatty($stdout);
    $green = $use_color ? "\033[32m" : '';
    $red = $use_color ? "\033[31m" : '';
    $reset = $use_color ? "\033[0m" : '';

    $project_name = 'unknown';
    $project_version = '';

    if (is_readable($composer_json_path)) {
        $data = json_decode((string) file_get_contents($composer_json_path), true);

        if (isset($data['name']) && is_string($data['name'])) {
            $project_name = $data['name'];
        }

        if (isset($data['version']) && is_string($data['version'])) {
            $project_version = $data['version'];
        }
    }

    fwrite($stdout, "Name: {$project_name}" . PHP_EOL);
    fwrite($stdout, "Version: {$project_version}" . PHP_EOL);
    fwrite($stdout, "Licenses: {$project_license}" . PHP_EOL);
    fwrite($stdout, PHP_EOL);
    fwrite($stdout, 'Composer dependencies:' . PHP_EOL . PHP_EOL);
    $lock_path = $project_root . '/composer.lock';

    $composer_rows = [];

    if (is_readable($lock_path)) {
        $lock = json_decode((string) file_get_contents($lock_path), true);
        $packages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? [],
        );

        foreach ($packages as $pkg) {
            $name = $pkg['name'] ?? '';
            $version = $pkg['version'] ?? '';
            $lic_raw = $pkg['license'] ?? [];

            if (is_string($lic_raw)) {
                $lic_raw = [$lic_raw];
            }
            $lic_str = implode(', ', array_map('mb_trim', array_filter($lic_raw, 'is_string')));

            if ($lic_str === '') {
                $lic_str = 'UNKNOWN';
            }

            $allowed = is_license_allowed(str_replace(', ', ' OR ', $lic_str), $allowed_patterns, $license_families);

            if (! $allowed) {
                $has_errors = true;
            }

            $composer_rows[] = [
                'name' => $name,
                'version' => $version,
                'licenses' => $lic_str,
                'allowed' => $allowed,
            ];
        }
    }

    if ($composer_rows !== []) {
        print_table($composer_rows, $green, $red, $reset, $stdout);
    } else {
        fwrite($stdout, 'No Composer dependencies (composer.lock not found or empty).' . PHP_EOL);
    }

    $package_json_path = $project_root . '/package.json';
    $node_modules = $project_root . '/node_modules';

    $npm_rows = [];

    if (is_readable($package_json_path) && is_dir($node_modules)) {
        $npm_data = $get_npm_license_data !== null
            ? $get_npm_license_data($project_root)
            : fetch_npm_license_data($project_root);

        if (is_array($npm_data)) {
            foreach ($npm_data as $pkg_key => $info) {
                $lic = $info['licenses'] ?? 'UNKNOWN';

                if (is_array($lic)) {
                    $lic = implode(', ', $lic);
                }
                $lic = mb_trim((string) $lic);

                $allowed = is_license_allowed(str_replace(', ', ' OR ', $lic), $allowed_patterns, $license_families);

                if (! $allowed) {
                    $has_errors = true;
                }

                $at = mb_strrpos($pkg_key, '@');
                $name = $at !== false ? mb_substr($pkg_key, 0, $at) : $pkg_key;
                $version = $at !== false ? mb_substr($pkg_key, $at + 1) : '';

                $npm_rows[] = [
                    'name' => $name,
                    'version' => $version,
                    'licenses' => $lic,
                    'allowed' => $allowed,
                ];
            }
        }
    }

    if ($npm_rows !== []) {
        fwrite($stdout, PHP_EOL);
        fwrite($stdout, 'npm dependencies:' . PHP_EOL . PHP_EOL);
        print_table($npm_rows, $green, $red, $reset, $stdout);
    } elseif (is_readable($project_root . '/package.json') && is_dir($node_modules) && $get_npm_license_data === null) {
        fwrite($stdout, 'npm: Skip – could not get license list (npx license-checker --json)' . PHP_EOL);
    }

    fwrite($stdout, PHP_EOL . str_repeat('=', 60) . PHP_EOL);
    fwrite($stdout, PHP_EOL);

    if ($has_errors) {
        fwrite($stdout, 'Result: ' . colorize_check('✗', $green, $red, $reset) . " FAIL – some dependencies are not compatible with {$project_license}" . PHP_EOL);

        return 1;
    }

    fwrite($stdout, 'Result: ' . colorize_check('✓', $green, $red, $reset) . " OK – all dependencies are compatible with {$project_license}" . PHP_EOL);

    return 0;
}

/**
 * CLI entry point: parse argv, resolve config, run check. Returns exit code.
 *
 * @param  array<int, string>  $argv
 */
function run_license_check_cli(array $argv, string $package_root): int
{
    $project_root = getcwd();

    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--path=')) {
            $project_root = mb_substr($arg, 7);

            break;
        }
    }

    if (! is_dir($project_root)) {
        fwrite(STDERR, "Error: Project path is not a directory: {$project_root}" . PHP_EOL);

        return 2;
    }

    $config_path = resolve_config_path($project_root, $package_root);

    if ($config_path === null) {
        fwrite(STDERR, 'Error: License matrix not found. Add one of: project/scripts/license-compliance.php, project/config/license-compliance.php, or use the package default.' . PHP_EOL);

        return 2;
    }

    return run_license_check($project_root, $config_path);
}

/**
 * Entry point when invoked via include (run-license-check.php). Reads project root and config path from $GLOBALS and runs check. Returns exit code.
 */
function run_license_check_from_globals(): int
{
    $project_root = $GLOBALS['LICENSE_CHECK_PROJECT_ROOT'] ?? '';
    $config_path = $GLOBALS['LICENSE_CHECK_CONFIG_PATH'] ?? '';

    return run_license_check($project_root, $config_path);
}
