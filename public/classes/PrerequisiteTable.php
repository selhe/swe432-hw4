<?php
require_once __DIR__ . '/Table.php';

class PrerequisiteTable extends Table
{
    protected string $table = 'course_prerequisites';
    protected string $label = 'Prerequisite';

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.course_id, p.prereq_id,
                    c.code AS course_code, c.title AS course_title,
                    r.code AS prereq_code, r.title AS prereq_title
             FROM course_prerequisites p
             JOIN courses c ON c.id = p.course_id
             JOIN courses r ON r.id = p.prereq_id
             ORDER BY c.code, r.code'
        );
    }

    public function create(array $input): array
    {
        [$courseId, $prereqId] = $this->validate($input);
        $this->db->execute(
            'INSERT INTO course_prerequisites (course_id, prereq_id) VALUES (?, ?)',
            [$courseId, $prereqId]
        );
        return $this->find($this->db->lastInsertId());
    }

    public function update(int $id, array $input): array
    {
        $this->find($id);
        [$courseId, $prereqId] = $this->validate($input, $id);
        $this->db->execute(
            'UPDATE course_prerequisites SET course_id = ?, prereq_id = ? WHERE id = ?',
            [$courseId, $prereqId, $id]
        );
        return $this->find($id);
    }

    /**
     * no self-prerequisite, no duplicate, and no loop.
     */
    private function validate(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $courseId = $v->integer('course_id', 'Course');
        $prereqId = $v->integer('prereq_id', 'Prerequisite');
        $this->requireExists('courses', $courseId, 'course');
        $this->requireExists('courses', $prereqId, 'prerequisite course');

        if ($courseId === $prereqId) {
            throw new ApiException('A course cannot be its own prerequisite.');
        }

        $duplicate = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM course_prerequisites WHERE course_id = ? AND prereq_id = ? AND id <> ?',
            [$courseId, $prereqId, $id]
        );
        if ($duplicate > 0) {
            throw new ApiException("{$this->code($courseId)} already requires {$this->code($prereqId)}.", 409);
        }

        $path = $this->findPath($prereqId, $courseId, $id);
        if ($path !== null) {
            $loop = implode(' → ', array_map(fn($courseOnPath) => $this->code($courseOnPath), array_merge([$courseId], $path)));
            throw new ApiException(
                "{$this->code($courseId)} cannot require {$this->code($prereqId)}: "
                . "{$this->code($prereqId)} already requires {$this->code($courseId)}, which would create the loop $loop.",
                409
            );
        }
        return [$courseId, $prereqId];
    }

    /**
     * Bfs along requires links
     */
    private function findPath(int $from, int $to, int $ignoreLinkId): ?array
    {
        $requires = [];
        $links = $this->db->fetchAll(
            'SELECT course_id, prereq_id FROM course_prerequisites WHERE id <> ?',
            [$ignoreLinkId]
        );
        foreach ($links as $link) {
            $requires[$link['course_id']][] = (int) $link['prereq_id'];
        }

        $cameFrom = [$from => null];  
        $queue = [$from];
        while (count($queue) > 0) {
            $current = array_shift($queue);
            if ($current === $to) {
                $path = [];
                for ($step = $to; $step !== null; $step = $cameFrom[$step]) {
                    array_unshift($path, $step);
                }
                return $path;
            }
            foreach ($requires[$current] ?? [] as $next) {
                if (!array_key_exists($next, $cameFrom)) {
                    $cameFrom[$next] = $current;
                    $queue[] = $next;
                }
            }
        }
        return null;
    }

    private function code(int $courseId): string
    {
        return (string) $this->db->fetchValue('SELECT code FROM courses WHERE id = ?', [$courseId]);
    }
}
