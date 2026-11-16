<?php
require_once __DIR__ . '/Table.php';

/** Each row means student_id took course_id and earned grade */
class EnrollmentTable extends Table
{
    public const GRADES = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F'];

    protected string $table = 'enrollments';
    protected string $label = 'Enrollment';

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT e.id, e.student_id, e.course_id, e.grade,
                    s.first, s.last, c.code AS course_code, c.title AS course_title
             FROM enrollments e
             JOIN students s ON s.id = e.student_id
             JOIN courses c ON c.id = e.course_id
             ORDER BY s.last, s.first, c.code'
        );
    }

    public function create(array $input): array
    {
        $e = $this->validate($input);
        $this->db->execute(
            'INSERT INTO enrollments (student_id, course_id, grade) VALUES (?, ?, ?)',
            [$e['student_id'], $e['course_id'], $e['grade']]
        );
        return $this->find($this->db->lastInsertId());
    }

    public function update(int $id, array $input): array
    {
        $this->find($id);
        $e = $this->validate($input, $id);
        $this->db->execute(
            'UPDATE enrollments SET student_id = ?, course_id = ?, grade = ? WHERE id = ?',
            [$e['student_id'], $e['course_id'], $e['grade'], $id]
        );
        return $this->find($id);
    }

    private function validate(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $enrollment = [
            'student_id' => $v->integer('student_id', 'Student'),
            'course_id' => $v->integer('course_id', 'Course'),
            'grade' => $v->oneOf('grade', 'Grade', self::GRADES),
        ];
        $this->requireExists('students', $enrollment['student_id'], 'student');
        $this->requireExists('courses', $enrollment['course_id'], 'course');

        $duplicate = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ? AND id <> ?',
            [$enrollment['student_id'], $enrollment['course_id'], $id]
        );
        if ($duplicate > 0) {
            throw new ApiException('This student already has an enrollment for this course.', 409);
        }
        return $enrollment;
    }
}
