<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->package_root = realpath(__DIR__ . '/../../');
});

test('run_license_check_cli returns 2 when path is not a directory', function (): void {
    $argv = ['check-licenses', '--path=/nonexistent_' . uniqid()];
    $code = run_license_check_cli($argv, $this->package_root);
    expect($code)->toBe(2);
});

test('run_license_check_cli returns 2 when config not found', function (): void {
    $project = sys_get_temp_dir() . '/lcc_cli_' . uniqid();
    $package = sys_get_temp_dir() . '/lcc_pkg_' . uniqid();
    mkdir($project);
    mkdir($package);
    file_put_contents($project . '/composer.json', '{"license":"MIT"}');
    $argv = ['check-licenses', '--path=' . $project];
    $code = run_license_check_cli($argv, $package);
    expect($code)->toBe(2);
    unlink($project . '/composer.json');
    rmdir($project);
    rmdir($package);
});

test('run_license_check_cli returns 0 when project and config valid', function (): void {
    $project = sys_get_temp_dir() . '/lcc_cli_ok_' . uniqid();
    mkdir($project);
    file_put_contents($project . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    $argv = ['check-licenses', '--path=' . $project];
    $code = run_license_check_cli($argv, $this->package_root);
    expect($code)->toBe(0);
    unlink($project . '/composer.json');
    rmdir($project);
});

test('run_license_check_from_globals returns exit code from run_license_check', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_glob_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    $config = $this->package_root . '/config/license-compliance.php';
    $GLOBALS['LICENSE_CHECK_PROJECT_ROOT'] = $dir;
    $GLOBALS['LICENSE_CHECK_CONFIG_PATH'] = $config;
    $code = run_license_check_from_globals();
    unset($GLOBALS['LICENSE_CHECK_PROJECT_ROOT'], $GLOBALS['LICENSE_CHECK_CONFIG_PATH']);
    expect($code)->toBe(0);
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('run-license-check.php returns exit code when required with globals set', function (): void {
    $dir = sys_get_temp_dir() . '/lcc_req_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    $config = $this->package_root . '/config/license-compliance.php';
    $GLOBALS['LICENSE_CHECK_PROJECT_ROOT'] = $dir;
    $GLOBALS['LICENSE_CHECK_CONFIG_PATH'] = $config;
    $code = require $this->package_root . '/src/run-license-check.php';
    unset($GLOBALS['LICENSE_CHECK_PROJECT_ROOT'], $GLOBALS['LICENSE_CHECK_CONFIG_PATH']);
    expect($code)->toBe(0);
    unlink($dir . '/composer.json');
    rmdir($dir);
});
