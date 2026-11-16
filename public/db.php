<?php

// Database connection
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $u = parse_url(getenv('DATABASE_URL') ?: fail('DATABASE_URL is not set.', 500));
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $u['host'], $u['port'] ?? 5432, ltrim($u['path'], '/')); // the only postgres-specific code in the entire app
    return $pdo = new PDO($dsn, urldecode($u['user']), urldecode($u['pass'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // DO NOT uncomment the next line. The schema is applied by docker-entrypoint.sh, not by PHP. The PHP code never touches schema.sql
    // $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
    // The only reason we left this comment here is because we used this approach to create the database in the practice we did in class
}
