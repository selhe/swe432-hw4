<?php
require_once __DIR__ . '/Table.php';

/** Each row means "course_id requires prereq_id" */
class PrerequisiteTable extends Table
{
    protected string $table = 'course_prerequisites';
    protected string $label = 'Prerequisite';

    public function all(): array
    {
        // courses is joined twice: once for the course, once for its prerequisite
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
     * Rule 1: no self-prerequisite, no duplicate, and no loop.
     * @param int $id the link being edited (0 when creating); it is ignored in the
     *                checks because the edit replaces it
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
            // e.g. CS101 → CS301 → CS201 → CS101
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
     * Breadth-first search along "requires" links, starting at $from.
     * Returns the chain of course ids from $from to $to (e.g. [301, 201, 101]),
     * or null if $from does not require $to directly or indirectly.
     */
    private function findPath(int $from, int $to, int $ignoreLinkId): ?array
    {
        // Load every link once: $requires[course] = list of courses it requires
        $requires = [];
        $links = $this->db->fetchAll(
            'SELECT course_id, prereq_id FROM course_prerequisites WHERE id <> ?',
            [$ignoreLinkId]
        );
        foreach ($links as $link) {
            $requires[$link['course_id']][] = (int) $link['prereq_id'];
        }

        $cameFrom = [$from => null];   // visited courses, and how we reached each one
        $queue = [$from];
        while (count($queue) > 0) {
            $current = array_shift($queue);
            if ($current === $to) {
                // walk back through $cameFrom to rebuild the chain
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

    /** Course code for messages, e.g. 'CS101' */
    private function code(int $courseId): string
    {
        return (string) $this->db->fetchValue('SELECT code FROM courses WHERE id = ?', [$courseId]);
    }
}
