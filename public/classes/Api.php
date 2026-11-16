<?php
require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/StudentTable.php';
require_once __DIR__ . '/CourseTable.php';
require_once __DIR__ . '/PrerequisiteTable.php';
require_once __DIR__ . '/EnrollmentTable.php';

/**
 * Request handling: reads the HTTP method, query string and JSON body,
 * calls the right Table method, and always answers with JSON.
 *
 *   GET    api.php?resource=courses            list
 *   GET    api.php?resource=courses&id=3       one record
 *   POST   api.php?resource=courses            create (JSON body)
 *   PUT    api.php?resource=courses&id=3       update (JSON body)
 *   DELETE api.php?resource=courses&id=3       delete
 *   GET    api.php?resource=transcript&id=5    transcript of student 5
 */
class Api
{
    private const TABLES = [
        'students' => StudentTable::class,
        'courses' => CourseTable::class,
        'prerequisites' => PrerequisiteTable::class,
        'enrollments' => EnrollmentTable::class,
    ];

    public function handle(): void
    {
        try {
            [$status, $payload] = $this->dispatch();
            $this->respond($status, $payload);
        } catch (ApiException $e) {
            // expected problems: invalid input, missing record, blocked operation
            $this->respond($e->getStatus(), ['error' => $e->getMessage()]);
        } catch (PDOException $e) {
            error_log('Database error: ' . $e->getMessage());   // details stay in the server log
            // SQLSTATE class 23 = integrity constraint violation (standard SQL):
            // the schema's safety net caught something our own checks missed
            if (str_starts_with((string) $e->getCode(), '23')) {
                $this->respond(409, ['error' => 'This change conflicts with existing data.']);
            } else {
                $this->respond(500, ['error' => 'A database error occurred. Please try again.']);
            }
        } catch (Throwable $e) {
            error_log('Unexpected error: ' . $e);
            $this->respond(500, ['error' => 'Something went wrong on the server.']);
        }
    }

    /** @return array [HTTP status, response body] */
    private function dispatch(): array
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $resource = $_GET['resource'] ?? '';
        $id = isset($_GET['id']) ? $this->parseId($_GET['id']) : null;
        $db = new Database();

        if ($resource === 'transcript') {
            if ($method !== 'GET' || $id === null) {
                throw new ApiException('Use GET with a student id to view a transcript.');
            }
            return [200, ['data' => (new StudentTable($db))->transcript($id)]];
        }

        if (!isset(self::TABLES[$resource])) {
            throw new ApiException("Unknown resource \"$resource\".", 404);
        }
        $className = self::TABLES[$resource];
        $table = new $className($db);

        switch ($method) {
            case 'GET':
                return [200, ['data' => $id === null ? $table->all() : $table->find($id)]];
            case 'POST':
                return [201, ['data' => $table->create($this->body()), 'message' => 'Record created.']];
            case 'PUT':
                return [200, ['data' => $table->update($this->requireId($id), $this->body()), 'message' => 'Record updated.']];
            case 'DELETE':
                $table->delete($this->requireId($id));
                return [200, ['message' => 'Record deleted.']];
            default:
                throw new ApiException("Method $method is not allowed.", 405);
        }
    }

    /** Decodes the JSON request body into an array */
    private function body(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            throw new ApiException('The request body must be a JSON object.');
        }
        return $data;
    }

    private function parseId(string $raw): int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new ApiException('The id must be a positive whole number.');
        }
        return $id;
    }

    private function requireId(?int $id): int
    {
        if ($id === null) {
            throw new ApiException('An id is required for this operation.');
        }
        return $id;
    }

    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
