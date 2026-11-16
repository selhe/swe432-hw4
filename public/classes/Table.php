<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Validator.php';
require_once __DIR__ . '/ApiException.php';

/**
 * Base class for the four tables.
 */
abstract class Table
{
    protected Database $db;
    protected string $table;   
    protected string $label;  

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Every row with related records shown by readable names */
    abstract public function all(): array;

    abstract public function create(array $input): array;

    abstract public function update(int $id, array $input): array;

    /** One raw row  */
    public function find(int $id): array
    {
        $row = $this->db->fetchOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
        if ($row === null) {
            throw new ApiException("{$this->label} #$id does not exist.", 404);
        }
        return $row;
    }

    /** Deletes a row after the subclass confirms nothing depends on it */
    public function delete(int $id): void
    {
        $this->find($id);            
        $this->checkCanDelete($id);   
        $this->db->execute("DELETE FROM {$this->table} WHERE id = ?", [$id]);
    }

    /** Override to block deletes that would break dependencies */
    protected function checkCanDelete(int $id): void
    {
    }

    /** Makes sure a record exists */
    protected function requireExists(string $table, int $id, string $label): void
    {
        $count = (int) $this->db->fetchValue("SELECT COUNT(*) FROM $table WHERE id = ?", [$id]);
        if ($count === 0) {
            throw new ApiException("The selected $label does not exist.");
        }
    }
}
