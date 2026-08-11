<?php

require_once __DIR__ . '/../Core/Database.php';
require_once __DIR__ . '/../Models/Ava/CourseCategory.php';
require_once __DIR__ . '/../Models/Ava/Course.php';
require_once __DIR__ . '/../Models/Ava/Semester.php';
require_once __DIR__ . '/../Models/Ava/Discipline.php';
require_once __DIR__ . '/../Models/Ava/Module.php';
require_once __DIR__ . '/../Models/Ava/Lesson.php';

/**
 * EducaTudo - AvaCourseService
 *
 * Fachada de leitura/escrita da estrutura curricular do AVA:
 * Curso -> Semestre -> Disciplina -> Módulo -> Aula (+anexos).
 */
class AvaCourseService
{
    private CourseCategory $categories;
    private Course $courses;
    private Semester $semesters;
    private Discipline $disciplines;
    private Module $modules;
    private Lesson $lessons;

    public function __construct()
    {
        $this->categories = new CourseCategory();
        $this->courses = new Course();
        $this->semesters = new Semester();
        $this->disciplines = new Discipline();
        $this->modules = new Module();
        $this->lessons = new Lesson();
    }

    public function categoriesModel(): CourseCategory { return $this->categories; }
    public function coursesModel(): Course { return $this->courses; }
    public function semestersModel(): Semester { return $this->semesters; }
    public function disciplinesModel(): Discipline { return $this->disciplines; }
    public function modulesModel(): Module { return $this->modules; }
    public function lessonsModel(): Lesson { return $this->lessons; }

    /** @return list<array<string,mixed>> */
    public function listCourses(string $busca = '', string $status = ''): array
    {
        return $this->courses->all($busca, $status);
    }

    /** @return array<string,mixed>|null */
    public function getCourse(int $id): ?array
    {
        return $this->courses->find($id);
    }

    /**
     * Estrutura completa de uma disciplina: módulos com suas aulas.
     * @return list<array<string,mixed>>
     */
    public function disciplineOutline(int $disciplinaId): array
    {
        $modulos = $this->modules->byDiscipline($disciplinaId);
        foreach ($modulos as &$m) {
            $m['aulas'] = $this->lessons->byModule((int) $m['id']);
        }
        unset($m);
        return $modulos;
    }

    /**
     * Lista linear (ordenada) de aulas de uma disciplina, para navegação no player.
     * @return list<array<string,mixed>>
     */
    public function flatLessons(int $disciplinaId): array
    {
        $flat = [];
        foreach ($this->disciplineOutline($disciplinaId) as $modulo) {
            foreach ($modulo['aulas'] as $aula) {
                $aula['modulo_titulo'] = $modulo['titulo'];
                $flat[] = $aula;
            }
        }
        return $flat;
    }

    /** Conta total de aulas de uma disciplina. */
    public function countLessons(int $disciplinaId): int
    {
        return count($this->flatLessons($disciplinaId));
    }
}
