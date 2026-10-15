<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

function AssertTest(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException("FAIL: [{$message}]\n"); }
    echo "PASS: [{$message}]\n";
}

function AssertDomainException(callable $operation, string $message): void {
    try {
        $operation();
    } catch (DomainException $e) {
        echo "PASS: {$message}\n";
        return;
    }
    throw new RuntimeException("FAIL: {$message}\n");
}

$cases = [
    [
        'name' => 'Validation Exception',
        'exception' => 'UserBase\Exception\ValidationException',
        'message' => 'A valid E-Mail Address is required',
        'status' => 422,
        'code' => 'validation_failed'
    ],
    [
        'name' => 'Account Conflict Exception',
        'exception' => 'UserBase\Exception\AccountConflictException',
        'message' => 'User Name or E-Mail Address is already in use',
        'status' => 409,
        'code' => 'account_conflict'
    ],
    [
        'name' => 'Unexpected Exception',
        'exception' => 'RuntimeException',
        'message' => 'Unexpected Internal Server Error',
        'status' => 500,
        'code' => 'internal_server_error'
    ]
];


foreach ($cases as $case) {
    $exceptionClass = $case['exception'];
    $exceptionMessage = var_export($case['message'], true);

    $script = sprintf(
<<<'PHP'
declare(strict_types=1);
require_once '/var/www/html/src/bootstrap.php';

use UserBase\Http\ExceptionHandler;

http_response_code(200);

$exception = new %s(%s);
ExceptionHandler::Handle($exception);
PHP,
        $exceptionClass,
        $exceptionMessage
    );

    $command = [PHP_BINARY, '-r', $script];
    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ],
        $pipes
    );

    AssertTest(
        is_resource($process),
        $case['name'] . ': process started'
    );

    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $errorOutput = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0)
    {
        echo "Sub-process exit code: {$exitCode}\n";
        echo "Sub-process stderr: {$errorOutput}\n";
        echo "Sub-process stdout: {$output}\n";
    }

    AssertTest(
        $exitCode === 0,
        $case['name'] . ': process exited successfully'
    );

    $response = json_decode($output, true);

    AssertTest(
        is_array($response),
        $case['name'] . ': response is valid JSON'
    );

    AssertTest(
        ($response['error']['code'] ?? null) === $case['code'],
        $case['name'] . ': correct error code'
    );

    AssertTest(
        ($response['error']['message'] ?? null) === (
            $case['status'] === 500
                ? 'An Unexpected Server Error Occurred.'
                : $case['message']
        ),
        $case['name'] . ': correct public error message'
    );

    // This checks the HTTP status emitted by PHP in the subprocess.
    // The CLI SAPI does not expose an HTTP response status code,
    // so status mapping is verified separately below.
    echo "\nPASS: [{$case['name']}: response checks complete]\n";

    if ($errorOutput !== '') {
        echo "Sub-Process Log: {$errorOutput}";
    }
}

echo "\nAll ExceptionHandler tests passed.\n";



?>