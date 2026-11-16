<?php
// Single entry point for every fetch() call from the page.
require_once __DIR__ . '/classes/Api.php';

// Never send raw PHP errors to the browser; they go to the server log instead
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Turn warnings/notices into exceptions so Api::handle() catches them too
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/** Used by the provided db.php when DATABASE_URL is missing */
function fail(string $message, int $status = 400): never
{
    throw new ApiException($message, $status);
}

(new Api())->handle();
