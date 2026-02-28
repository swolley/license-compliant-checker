<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->package_root = realpath(__DIR__ . '/../../');
});

test('expand_allowed_patterns returns empty for empty patterns', function (): void {
    expect(expand_allowed_patterns([], []))->toBe([]);
});

test('expand_allowed_patterns trims and skips empty strings', function (): void {
    expect(expand_allowed_patterns(['  ', 'MIT', ''], []))->toBe(['MIT']);
});

test('expand_allowed_patterns expands @group reference', function (): void {
    $groups = ['permissive' => ['MIT', 'BSD-*']];
    $patterns = ['@permissive'];
    expect(expand_allowed_patterns($patterns, $groups))->toBe(['MIT', 'BSD-*']);
});

test('expand_allowed_patterns expands nested @group', function (): void {
    $groups = [
        'base' => ['MIT'],
        'extra' => ['@base', 'Apache-*'],
    ];
    $patterns = ['@extra'];
    expect(expand_allowed_patterns($patterns, $groups))->toBe(['MIT', 'Apache-*']);
});

test('expand_allowed_patterns mixes literal and group', function (): void {
    $groups = ['g' => ['MIT']];
    $patterns = ['ISC', '@g'];
    expect(expand_allowed_patterns($patterns, $groups))->toBe(['ISC', 'MIT']);
});

test('expand_allowed_patterns unknown @group is skipped', function (): void {
    $patterns = ['@missing', 'MIT'];
    expect(expand_allowed_patterns($patterns, []))->toBe(['MIT']);
});

test('license_matches_patterns exact match', function (): void {
    $families = [];
    expect(license_matches_patterns('MIT', ['MIT'], $families))->toBeTrue();
    expect(license_matches_patterns('MIT', ['Apache-2.0'], $families))->toBeFalse();
});

test('license_matches_patterns wildcard prefix', function (): void {
    $families = [];
    expect(license_matches_patterns('BSD-3-Clause', ['BSD-*'], $families))->toBeTrue();
    expect(license_matches_patterns('BSDD', ['BSD-*'], $families))->toBeFalse();
});

test('license_matches_patterns version constraint GPL', function (): void {
    $families = [
        'GPL-2.0-only' => ['GPL', 2.0],
        'GPL-3.0-only' => ['GPL', 3.0],
    ];
    expect(license_matches_patterns('GPL-3.0-only', ['GPL->=3.0'], $families))->toBeTrue();
    expect(license_matches_patterns('GPL-2.0-only', ['GPL->=3.0'], $families))->toBeFalse();
    expect(license_matches_patterns('GPL-2.0-only', ['GPL-<=2.0'], $families))->toBeTrue();
    expect(license_matches_patterns('GPL-3.0-only', ['GPL-<=2.0'], $families))->toBeFalse();
    expect(license_matches_patterns('GPL-2.0-only', ['GPL-<3.0'], $families))->toBeTrue();
    expect(license_matches_patterns('GPL-3.0-only', ['GPL-<3.0'], $families))->toBeFalse();
    expect(license_matches_patterns('GPL-3.0-only', ['GPL->2.0'], $families))->toBeTrue();
    expect(license_matches_patterns('GPL-2.0-only', ['GPL->2.0'], $families))->toBeFalse();
});

test('license_matches_patterns version constraint unknown license family', function (): void {
    $families = [];
    expect(license_matches_patterns('GPL-3.0-only', ['GPL->=3.0'], $families))->toBeFalse();
});

test('license_matches_patterns version constraint family mismatch continues', function (): void {
    $families = [
        'MIT' => ['MIT', 1.0],
        'GPL-3.0-only' => ['GPL', 3.0],
    ];
    expect(license_matches_patterns('MIT', ['GPL->=3.0'], $families))->toBeFalse();
    expect(license_matches_patterns('GPL-3.0-only', ['GPL-<=2.0'], $families))->toBeFalse();
});

test('colorize_check with empty green returns symbol', function (): void {
    expect(colorize_check('✓', '', '', ''))->toBe('✓');
    expect(colorize_check('✗', '', '', ''))->toBe('✗');
});

test('colorize_check with color wraps symbol', function (): void {
    $g = "\033[32m";
    $r = "\033[31m";
    $z = "\033[0m";
    expect(colorize_check('✓', $g, $r, $z))->toBe($g . '✓' . $z);
    expect(colorize_check('✗', $g, $r, $z))->toBe($r . '✗' . $z);
});

test('is_tty returns false for non resource', function (): void {
    expect(is_tty(null))->toBeFalse();
});

test('is_tty returns false for memory stream', function (): void {
    $stream = fopen('php://memory', 'rw');
    fwrite($stream, 'x');
    rewind($stream);
    expect(is_tty($stream))->toBeFalse();
    fclose($stream);
});

test('is_tty can be called on regular file stream', function (): void {
    $path = sys_get_temp_dir() . '/lcc_tty_' . uniqid();
    file_put_contents($path, '');
    $stream = fopen($path, 'rw');
    is_tty($stream);
    fclose($stream);
    unlink($path);
    expect(true)->toBeTrue();
});

test('is_license_allowed rejects empty and UNKNOWN', function (): void {
    expect(is_license_allowed('', ['MIT'], []))->toBeFalse();
    expect(is_license_allowed('UNKNOWN', ['MIT'], []))->toBeFalse();
});

test('is_license_allowed single license', function (): void {
    expect(is_license_allowed('MIT', ['MIT'], []))->toBeTrue();
    expect(is_license_allowed('MIT', ['Apache-2.0'], []))->toBeFalse();
});

test('is_license_allowed AND expression', function (): void {
    expect(is_license_allowed('MIT AND BSD-3-Clause', ['MIT', 'BSD-*'], []))->toBeTrue();
    expect(is_license_allowed('MIT AND GPL-2.0', ['MIT'], []))->toBeFalse();
});

test('is_license_allowed OR expression', function (): void {
    expect(is_license_allowed('MIT OR Apache-2.0', ['Apache-2.0'], []))->toBeTrue();
    expect(is_license_allowed('GPL-2.0 OR GPL-3.0', ['MIT'], []))->toBeFalse();
});

test('is_license_allowed no AND or OR returns false when no match', function (): void {
    expect(is_license_allowed('Proprietary', ['MIT'], []))->toBeFalse();
});

test('resolve_config_path returns project scripts path when readable', function (): void {
    $project = sys_get_temp_dir() . '/lcc_test_' . uniqid();
    $package = $this->package_root;
    mkdir($project . '/scripts', 0755, true);
    file_put_contents($project . '/scripts/license-compliance.php', '<?php return ["compatibility" => ["MIT" => ["MIT"]]];');
    expect(resolve_config_path($project, $package))->toBe($project . '/scripts/license-compliance.php');
    (static function () use ($project): void {
        unlink($project . '/scripts/license-compliance.php');
        rmdir($project . '/scripts');
        rmdir($project);
    })();
});

test('resolve_config_path returns project config path when scripts missing', function (): void {
    $project = sys_get_temp_dir() . '/lcc_test_' . uniqid();
    $package = $this->package_root;
    mkdir($project . '/config', 0755, true);
    file_put_contents($project . '/config/license-compliance.php', '<?php return ["compatibility" => ["MIT" => ["MIT"]]];');
    expect(resolve_config_path($project, $package))->toBe($project . '/config/license-compliance.php');
    unlink($project . '/config/license-compliance.php');
    rmdir($project . '/config');
    rmdir($project);
});

test('resolve_config_path returns package config when project has neither', function (): void {
    $project = sys_get_temp_dir() . '/lcc_test_' . uniqid();
    mkdir($project);
    $package = $this->package_root;
    expect(resolve_config_path($project, $package))->toBe($package . '/config/license-compliance.php');
    rmdir($project);
});

test('resolve_config_path returns null when no config readable', function (): void {
    $project = sys_get_temp_dir() . '/lcc_test_' . uniqid();
    mkdir($project);
    $package = sys_get_temp_dir() . '/nonexistent_package_' . uniqid();
    expect(resolve_config_path($project, $package))->toBeNull();
    rmdir($project);
});
