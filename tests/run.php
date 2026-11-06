<?php

declare(strict_types=1);

require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;

$testFiles = glob(__DIR__ . '/test-*.php');

if ($testFiles === false || count($testFiles) === 0) {
    fwrite(STDERR, "No test files found.\n");
    exit(1);
}

sort($testFiles);

$passed = 0;
$failed = 0;
$failures = [];
$index = 0;
$count = count($testFiles);

//echo "================ BEGIN TEST SUITE ================\n";
Out::ColorLine(Out::MAGENTA, "================ BEGIN TEST SUITE ================");
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
        //echo "PASS: {$testName}\n\n";
        Out::Label(Out::GREEN, "[MODULE PASSED]:", $testName . "\n");
        $passed++;
    } else {
        //echo "FAIL: {$testName} (exit code: {$exitCode})\n\n";
        Out::Label(Out::RED, "[MODULE FAILED]:", "{$testName} (exit code: {$exitCode})\n");
        $n = $index+1;
        $failures[] = "({$n}/{$count})\t: [{$testName}]\t<{$exitCode}>";
        $failed++;
    }

    $index++;
}

$total = $passed + $failed;
$color = Out::WHITE;

Out::ColorLine(Out::CYAN, "================ TEST SUITE SUMMARY ================");


if (count($failures) > 0) {
    Out::ColorLine(Out::RED, "\n---------------- FAILURES ----------------");
    foreach ($failures as $fail)
    {
        Out::ColorLine(Out::RED, $fail);
    }
    Out::ColorLine(Out::RED, "------------------------------------------\n");
}

if ($passed === $total) $color = Out::GREEN;
else if ($passed === 0) $color = Out::RED;
else $color = Out::YELLOW;
Out::Label($color, '[PASSED]: ', (string) $passed);

if ($failed > 0) $color = Out::RED;
else $color = Out::WHITE;
Out::Label($color, '[FAILED]: ', (string) $failed);

if ($passed === $total) $color = Out::GREEN;
else if ($passed === 0) $color = Out::RED;
else $color = Out::YELLOW;
echo "____________\n";
$pct = (int)(($passed / $count) * 100);
Out::Label($color, '[TOTAL]: ', "{$total} ({$pct}%%)");

Out::ColorLine(Out::BLUE, "================ END TEST SUITE ================");

?>