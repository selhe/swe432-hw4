CREATE TABLE IF NOT EXISTS students (
  id                 SERIAL PRIMARY KEY,
  first              VARCHAR(100) NOT NULL,
  last               VARCHAR(100) NOT NULL,
  email              VARCHAR(255) NOT NULL UNIQUE,
  birthdate          DATE NOT NULL,
  date_of_enrollment DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS courses (
  id      SERIAL PRIMARY KEY,
  code    VARCHAR(20)  NOT NULL UNIQUE,
  title   VARCHAR(200) NOT NULL,
  credits INTEGER      NOT NULL CHECK (credits BETWEEN 1 AND 6)
);

-- Each row means: course_id requires prereq_id.
-- The foreign keys (no ON DELETE CASCADE) are a safety net: the database refuses
-- to delete a course that is still referenced here. The app checks this first.
CREATE TABLE IF NOT EXISTS course_prerequisites (
  id        SERIAL PRIMARY KEY,
  course_id INTEGER NOT NULL REFERENCES courses (id),
  prereq_id INTEGER NOT NULL REFERENCES courses (id),
  UNIQUE (course_id, prereq_id),        -- the same prerequisite can't be added twice
  CHECK (course_id <> prereq_id)        -- a course can't be its own prerequisite
);

-- Each row means: student_id took course_id and earned grade.
CREATE TABLE IF NOT EXISTS enrollments (
  id         SERIAL PRIMARY KEY,
  student_id INTEGER    NOT NULL REFERENCES students (id),
  course_id  INTEGER    NOT NULL REFERENCES courses (id),
  grade      VARCHAR(2) NOT NULL
             CHECK (grade IN ('A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F')),
  UNIQUE (student_id, course_id)        -- a student can take a given course only once
);
