<?php
require_once __DIR__ . '/Table.php';

class StudentTable extends Table
{
    protected string $table = 'students';
    protected string $label = 'Student';

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT id, first, last, email, birthdate, date_of_enrollment
             FROM students ORDER BY last, first'
        );
    }

    public function create(array $input): array
    {
        $s = $this->validate($input);
        $this->db->execute(
            'INSERT INTO students (first, last, email, birthdate, date_of_enrollment) VALUES (?, ?, ?, ?, ?)',
            [$s['first'], $s['last'], $s['email'], $s['birthdate'], $s['date_of_enrollment']]
        );
        return $this->find($this->db->lastInsertId());
    }

    public function update(int $id, array $input): array
    {
        $this->find($id);
        $s = $this->validate($input, $id);
        $this->db->execute(
            'UPDATE students SET first = ?, last = ?, email = ?, birthdate = ?, date_of_enrollment = ? WHERE id = ?',
            [$s['first'], $s['last'], $s['email'], $s['birthdate'], $s['date_of_enrollment'], $id]
        );
        return $this->find($id);
    }

    /** Courses the student took, joined with the courses table, plus total credits */
    public function transcript(int $id): array
    {
        $student = $this->find($id);
        $courses = $this->db->fetchAll(
            'SELECT c.code, c.title, c.credits, e.grade
             FROM enrollments e
             JOIN courses c ON c.id = e.course_id
             WHERE e.student_id = ?
             ORDER BY c.code',
            [$id]
        );
        $total = 0;
        foreach ($courses as $course) {
            $total += (int) $course['credits'];
        }
        return ['student' => $student, 'courses' => $courses, 'total_credits' => $total];
    }

    /** Rule 2: a student with enrollments can't be deleted */
    protected function checkCanDelete(int $id): void
    {
        $count = (int) $this->db->fetchValue('SELECT COUNT(*) FROM enrollments WHERE student_id = ?', [$id]);
        if ($count > 0) {
            throw new ApiException("This student has $count enrollment(s). Delete those enrollments first.", 409);
        }
    }

    /** @param int $id the student being edited (0 when creating), so its own email isn't a "duplicate" */
    private function validate(array $input, int $id = 0): array
    {
        $v = new Validator($input);
        $student = [
            'first' => $v->text('first', 'First name', 100),
            'last' => $v->text('last', 'Last name', 100),
            'email' => $v->email('email', 'Email'),
            'birthdate' => $v->date('birthdate', 'Birthdate'),
            'date_of_enrollment' => $v->date('date_of_enrollment', 'Date of enrollment'),
        ];
        // YYYY-MM-DD strings compare correctly as text
        if ($student['birthdate'] >= $student['date_of_enrollment']) {
            throw new ApiException('Date of enrollment must be after the birthdate.');
        }
        $taken = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM students WHERE LOWER(email) = LOWER(?) AND id <> ?',
            [$student['email'], $id]
        );
        if ($taken > 0) {
            throw new ApiException("Another student already uses the email {$student['email']}.", 409);
        }
        return $student;
    }
}
