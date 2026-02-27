<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->package_root = realpath(__DIR__ . '/../../');
});

test('run_license_check returns 2 when compatibility is empty', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"license":"MIT"}');
    $config = $dir . '/config.php';
    file_put_contents($config, '<?php return ["compatibility" => [], "license_families" => [], "groups" => []];');
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(2);
    rewind($err);
    expect(stream_get_contents($err))->toContain('compatibility');
    unlink($dir . '/composer.json');
    unlink($config);
    rmdir($dir);
});

test('run_license_check returns 2 when no license detected', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(2);
    rewind($err);
    expect(stream_get_contents($err))->toContain('Could not detect');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check returns 2 when project license not in compatibility', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"license":"Custom-1.0"}');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(2);
    rewind($err);
    expect(stream_get_contents($err))->toContain('No compatibility rules');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check returns 0 and prints header when composer.json has license and no lock', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"test/pkg","version":"1.0","license":"MIT"}');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    $stdout = stream_get_contents($out);
    expect($stdout)->toContain('Name: test/pkg');
    expect($stdout)->toContain('Version: 1.0');
    expect($stdout)->toContain('Licenses: MIT');
    expect($stdout)->toContain('Composer dependencies:');
    expect($stdout)->toContain('No Composer dependencies');
    expect($stdout)->toContain('Result:');
    expect($stdout)->toContain('OK');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check returns 0 with composer.lock and all allowed', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"test/pkg","version":"1.0","license":"MIT"}');
    file_put_contents($dir . '/composer.lock', json_encode([
        'packages' => [
            ['name' => 'foo/bar', 'version' => '1.0', 'license' => 'MIT'],
        ],
        'packages-dev' => [],
    ]));
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('foo/bar');
    unlink($dir . '/composer.lock');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check returns 1 when dependency not allowed', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"test/pkg","version":"1.0","license":"MIT"}');
    $configPath = $dir . '/license-compliance.php';
    file_put_contents($configPath, '<?php return ["compatibility" => ["MIT" => ["Apache-2.0"]], "license_families" => [], "groups" => []];');
    file_put_contents($dir . '/composer.lock', json_encode([
        'packages' => [
            ['name' => 'foo/bar', 'version' => '1.0', 'license' => 'GPL-3.0'],
        ],
        'packages-dev' => [],
    ]));
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $configPath, $out, $err);
    expect($code)->toBe(1);
    rewind($out);
    expect(stream_get_contents($out))->toContain('FAIL');
    unlink($dir . '/composer.lock');
    unlink($dir . '/composer.json');
    unlink($configPath);
    rmdir($dir);
});

test('run_license_check detects license from package.json when composer has none', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/package.json', '{"license":"Apache-2.0"}');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('Licenses: Apache-2.0');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects AGPL from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'GNU AFFERO GENERAL PUBLIC LICENSE');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('AGPL-3.0-or-later');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects GPL from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'GNU GENERAL PUBLIC LICENSE');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('GPL-3.0-or-later');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects MIT from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'MIT License');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('MIT');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects BSD-2-Clause from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'BSD 2-Clause License');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('BSD-2-Clause');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects BSD-4-Clause from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'BSD 4-Clause');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('BSD-4-Clause');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects BSD-3-Clause from LICENSE file when no 2 or 4', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'BSD License');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('BSD-3-Clause');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects Apache-1.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Apache License Version 1.0');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('Apache-1.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects Apache-1.1 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Apache License Version 1.1');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('Apache-1.1');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects Apache-2.0 from LICENSE file when no version 1.0 or 1.1', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Apache License, Version 2.0');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('Apache-2.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects proprietary from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'All rights reserved.');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('proprietary');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC0-1.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons CC0 Public Domain');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC0-1.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC-BY-NC-SA-4.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons BY-NC-SA');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC-BY-NC-SA-4.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC-BY-NC-4.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons Attribution-NonCommercial');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC-BY-NC-4.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC-BY-SA-4.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons BY-SA');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC-BY-SA-4.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC-BY-4.0 from LICENSE file', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons Attribution');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC-BY-4.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check detects CC-BY-4.0 from LICENSE file as Creative Commons fallback', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    file_put_contents($dir . '/LICENSE', 'Creative Commons License (unspecified variant)');
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('CC-BY-4.0');
    unlink($dir . '/LICENSE');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check composer.lock with string license', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/composer.lock', json_encode([
        'packages' => [
            ['name' => 'a/b', 'version' => '1.0', 'license' => 'MIT'],
        ],
        'packages-dev' => [],
    ]));
    $config = $this->package_root . '/config/license-compliance.php';
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err);
    expect($code)->toBe(0);
    unlink($dir . '/composer.lock');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check composer.lock package with empty license becomes UNKNOWN', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"proprietary"}');
    $configPath = $dir . '/license-compliance.php';
    file_put_contents($configPath, '<?php return ["compatibility" => ["proprietary" => ["MIT"]], "license_families" => [], "groups" => []];');
    file_put_contents($dir . '/composer.lock', json_encode([
        'packages' => [
            ['name' => 'a/b', 'version' => '1.0', 'license' => []],
        ],
        'packages-dev' => [],
    ]));
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $configPath, $out, $err);
    expect($code)->toBe(1);
    unlink($dir . '/composer.lock');
    unlink($dir . '/composer.json');
    unlink($configPath);
    rmdir($dir);
});

test('run_license_check with npm getter returning data prints npm table and exits 0 when all allowed', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/package.json', '{}');
    mkdir($dir . '/node_modules');
    $config = $this->package_root . '/config/license-compliance.php';
    $get_npm = fn (): array => [
        'lodash@4.17.21' => ['licenses' => 'MIT'],
    ];
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err, $get_npm);
    expect($code)->toBe(0);
    rewind($out);
    $stdout = stream_get_contents($out);
    expect($stdout)->toContain('npm dependencies:');
    expect($stdout)->toContain('lodash');
    expect($stdout)->toContain('OK');
    rmdir($dir . '/node_modules');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check with npm getter returning package with licenses array', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/package.json', '{}');
    mkdir($dir . '/node_modules');
    $config = $this->package_root . '/config/license-compliance.php';
    $get_npm = fn (): array => [
        'dual-licensed@1.0' => ['licenses' => ['MIT', 'Apache-2.0']],
    ];
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $config, $out, $err, $get_npm);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('MIT, Apache-2.0');
    rmdir($dir . '/node_modules');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check with npm getter returning disallowed license sets has_errors and exits 1', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/package.json', '{}');
    mkdir($dir . '/node_modules');
    $configPath = $dir . '/license-compliance.php';
    file_put_contents($configPath, '<?php return ["compatibility" => ["MIT" => ["MIT"]], "license_families" => [], "groups" => []];');
    $get_npm = fn (): array => [
        'gpl-pkg@1.0' => ['licenses' => 'GPL-3.0'],
    ];
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = run_license_check($dir, $configPath, $out, $err, $get_npm);
    expect($code)->toBe(1);
    rewind($out);
    expect(stream_get_contents($out))->toContain('FAIL');
    rmdir($dir . '/node_modules');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    unlink($configPath);
    rmdir($dir);
});

test('run_license_check with npm getter returning null does not print Skip', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/package.json', '{}');
    mkdir($dir . '/node_modules');
    $config = $this->package_root . '/config/license-compliance.php';
    $get_npm = fn (): ?array => null;
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    run_license_check($dir, $config, $out, $err, $get_npm);
    rewind($out);
    expect(stream_get_contents($out))->not->toContain('npm: Skip');
    rmdir($dir . '/node_modules');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run_license_check without npm getter with package.json and node_modules prints Skip when fetch returns null', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_run_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/package.json', '{}');
    mkdir($dir . '/node_modules');
    $config = $this->package_root . '/config/license-compliance.php';
    $old_path = getenv('PATH');
    putenv('PATH=');
    $out = fopen('php://memory', 'rw');
    $err = fopen('php://memory', 'rw');
    $code = @run_license_check($dir, $config, $out, $err, null);
    putenv('PATH=' . $old_path);
    expect($code)->toBe(0);
    rewind($out);
    expect(stream_get_contents($out))->toContain('npm: Skip');
    rmdir($dir . '/node_modules');
    unlink($dir . '/package.json');
    unlink($dir . '/composer.json');
    rmdir($dir);
});
