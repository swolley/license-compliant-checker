<?php

declare(strict_types=1);

$bin = realpath(__DIR__ . '/../../bin/check-licenses');

test('bin exits 2 when path is not a directory', function () use ($bin): void {
    $output = [];
    $return = 0;
    exec('php ' . escapeshellarg($bin) . ' --path=/nonexistent_' . uniqid() . ' 2>&1', $output, $return);
    expect($return)->toBe(2);
    expect(implode("\n", $output))->toContain('not a directory');
});

test('bin exits 2 when project has no license and no config in project', function () use ($bin): void {
    $dir = sys_get_temp_dir() . '/lcc_bin_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{}');
    $output = [];
    $return = 0;
    exec('php ' . escapeshellarg($bin) . ' --path=' . escapeshellarg($dir) . ' 2>&1', $output, $return);
    expect($return)->toBe(2);
    $out = implode("\n", $output);
    expect($out)->toContain('Could not detect project license');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('bin exits 0 when project has scripts/license-compliance.php', function () use ($bin): void {
    $dir = sys_get_temp_dir() . '/lcc_bin_' . uniqid();
    mkdir($dir . '/scripts', 0755, true);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    file_put_contents($dir . '/scripts/license-compliance.php', '<?php return ["compatibility"=>["MIT"=>["MIT"]],"license_families"=>[],"groups"=>[]];');
    $output = [];
    $return = 0;
    exec('php ' . escapeshellarg($bin) . ' --path=' . escapeshellarg($dir) . ' 2>&1', $output, $return);
    expect($return)->toBe(0);
    expect(implode("\n", $output))->toContain('Licenses: MIT');
    unlink($dir . '/scripts/license-compliance.php');
    rmdir($dir . '/scripts');
    unlink($dir . '/composer.json');
    rmdir($dir);
});

test('bin uses getcwd when no --path', function () use ($bin): void {
    $dir = sys_get_temp_dir() . '/lcc_bin_' . uniqid();
    mkdir($dir);
    file_put_contents($dir . '/composer.json', '{"name":"t","version":"1","license":"MIT"}');
    $orig = getcwd();
    chdir($dir);
    $output = [];
    $return = 0;
    exec('php ' . escapeshellarg($bin) . ' 2>&1', $output, $return);
    chdir($orig);
    expect($return)->toBe(0);
    expect(implode("\n", $output))->toContain('Licenses: MIT');
    unlink($dir . '/composer.json');
    rmdir($dir);
});
