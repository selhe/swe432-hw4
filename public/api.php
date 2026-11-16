<?php
require_once __DIR__ . '/classes/Api.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Turn warnings/notices into exceptions
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/** DATABASE_URL is missing */
function fail(string $message, int $status = 400): never
{
    throw new ApiException($message, $status);
}

(new Api())->handle();
