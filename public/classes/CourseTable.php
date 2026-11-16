<?php
require_once __DIR__ . '/Table.php';

class CourseTable extends Table
{
    protected string $table = 'courses';
    protected string $label = 'Course';

    public function all(): array
    {
        return $this->db->fetchAll('SELECT id, code, title, credits FROM courses ORDER BY code');
    }

    public function create(array $input): array
    {
        $c = $this->validate($input);
        $this->db->execute(
            'INSERT INTO courses (code, title, credits) VALUES (?, ?, ?)',
            [$c['code'], $c['title'], $c['credits']]
        );
        return $this->find($this->db->lastInsertId());
    }

    public function update(int $id, array $input): array
    {
        $this->find($id);
        $c = $this->validate($input, $id);
        $this->db->execute(
            'UPDATE courses SET code = ?, title = ?, credits = ? WHERE id = ?',
            [$c['code'], $c['title'], $c['credits'], $id]
        );
        return $this->find($id);
    }

    /** Rule 2: a course used in any prerequisite, or with enrollments, can't be deleted */
    protected function checkCanDelete(int $id): void
    {
        $links = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM course_prerequisites WHERE course_id = ? OR prereq_id = ?',
            [$id, $id]
        );
        if ($links > 0) {
            throw new ApiException('This course is part of a prerequisite chain. Remove its prerequisite links first.', 409);
        }
        $enrolled = (int) $this->db->fetchValue('SELECT COUNT(*) FROM enrollments WHERE course_id = ?', [$id]);
        if ($enrolled > 0) {
            throw new ApiException("This course has $enrolled enrollment(s). Delete those enrollments first.", 409);
        }
    }

    /** @param int $id the course being edited (0 when creating) */
    private function validate(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $course = [
            'code' => strtoupper($v->text('code', 'Course code', 20)),
            'title' => $v->text('title', 'Title', 200),
            'credits' => $v->integer('credits', 'Credits', 1, 6),
        ];
        $taken = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM courses WHERE UPPER(code) = ? AND id <> ?',
            [$course['code'], $id]
        );
        if ($taken > 0) {
            throw new ApiException("A course with the code {$course['code']} already exists.", 409);
        }
        return $course;
    }
}
