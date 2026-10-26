<?php

declare(strict_types=1);

$testFiles = glob(__DIR__ . '/test-*.php');

if ($testFiles === false || count($testFiles) === 0) {
    fwrite(STDERR, "No test files found.\n");
    exit(1);
}

sort($testFiles);

$passed = 0;
$failed = 0;

echo "================ BEGIN TEST SUITE ================\n";
echo "Running UserBase Test Suite...\n\n";

foreach ($testFiles as $testFile) {
    $testName = basename($testFile);

    echo "=== {$testName} ===\n";

    $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($testFile)
                . ' 2>&1';

    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    if ($output !== []) { echo implode("\n", $output) . "\n"; }

    if ($exitCode === 0) {
        echo "PASS: {$testName}\n\n";
        $passed++;
    } else {
        echo "FAIL: {$testName} (exit code: {$exitCode})\n\n";
        $failed++;
    }
}

echo "================ TEST SUMMARY ================\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "____________\n";
echo "Total: " . ($passed + $failed) . "\n";

?>