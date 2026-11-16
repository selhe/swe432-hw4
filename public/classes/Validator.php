<?php
require_once __DIR__ . '/ApiException.php';

/**
 * Server-side validation of one request body. Each method returns the
 * cleaned value or throws an ApiException (HTTP 400) with a readable message.
 */
class Validator
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function text(string $field, string $label, int $maxLength = 255): string
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value === '') {
            throw new ApiException("$label is required.");
        }
        if (mb_strlen($value) > $maxLength) {
            throw new ApiException("$label must be at most $maxLength characters.");
        }
        return $value;
    }

    public function email(string $field, string $label): string
    {
        $value = $this->text($field, $label);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ApiException("$label must be a valid email address.");
        }
        return $value;
    }

    /** A real calendar date in YYYY-MM-DD format (rejects e.g. 2025-02-30) */
    public function date(string $field, string $label): string
    {
        $value = $this->text($field, $label, 10);
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ApiException("$label must be a valid date (YYYY-MM-DD).");
        }
        return $value;
    }

    /** A whole number between $min and $max (strings like "3" are accepted, "3.5" or "abc" are not) */
    public function integer(string $field, string $label, int $min = 1, int $max = PHP_INT_MAX): int
    {
        $raw = $this->data[$field] ?? '';
        if ($raw === '' || $raw === null) {
            throw new ApiException("$label is required.");
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($value === false) {
            $range = $max === PHP_INT_MAX ? "at least $min" : "between $min and $max";
            throw new ApiException("$label must be a whole number $range.");
        }
        return $value;
    }

    /** One of a fixed list of values, e.g. a letter grade */
    public function oneOf(string $field, string $label, array $allowed): string
    {
        $value = $this->text($field, $label);
        if (!in_array($value, $allowed, true)) {
            throw new ApiException("$label must be one of: " . implode(', ', $allowed) . '.');
        }
        return $value;
    }
}
