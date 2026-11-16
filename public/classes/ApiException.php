<?php
/**
 * An error the user should see: carries a readable message and the HTTP
 * status code to send (400 invalid input, 404 not found, 409 conflict, ...).
 */
class ApiException extends Exception
{
    private int $status;

    public function __construct(string $message, int $status = 400)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function getStatus(): int
    {
        return $this->status;
    }
}
