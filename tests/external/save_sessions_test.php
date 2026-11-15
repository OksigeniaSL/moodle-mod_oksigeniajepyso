<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_oksigeniajepyso\external;

use core_external\external_api;
use mod_oksigeniajepyso\local\catalog;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Tests of the web services the player calls: who can save, and what saving changes in completion and grades.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\external\save_sessions
 * @covers     \mod_oksigeniajepyso\external\get_sessions
 * @covers     \mod_oksigeniajepyso\completion\custom_completion
 */
#[\PHPUnit\Framework\Attributes\CoversClass(save_sessions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_sessions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_oksigeniajepyso\completion\custom_completion::class)]
final class save_sessions_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    private $course;

    /** @var \stdClass The activity. */
    private $jepyso;

    /** @var \stdClass A student of the course. */
    private $student;

    /** @var \stdClass A teacher of the course, who does not keep sessions. */
    private $teacher;

    /**
     * A course with an activity of three sessions, completion by sessions and a grade of 10.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        catalog::use_file(__DIR__ . '/../fixtures/catalogo.json');
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['enablecompletion' => 1]);
        $this->jepyso = $generator->create_module('oksigeniajepyso', [
            'course' => $this->course->id,
            'sessions' => 3,
            'grade' => 10,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsessions' => 1,
        ]);
        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');
    }

    /**
     * Back to the catalogue of the player.
     */
    protected function tearDown(): void {
        catalog::use_file(null);
        parent::tearDown();
    }

    /**
     * Sessions as the player sends them, one per day, each with some repetitions.
     *
     * @param string[] $days The days.
     * @return string
     */
    private static function sessions(array $days): string {
        return json_encode(array_map(fn($day) => [
            'f' => $day,
            'h' => '18:00',
            'e' => [['i' => 'silla', 's' => 2, 'r' => 8, 'p' => [8, 8], 'm' => 2]],
        ], $days));
    }

    /**
     * Saves as the current user.
     *
     * @param string $sessions The sessions, as JSON.
     * @return array
     */
    private function save(string $sessions): array {
        $result = save_sessions::execute($this->jepyso->cmid, $sessions);
        return external_api::clean_returnvalue(save_sessions::execute_returns(), $result);
    }

    /**
     * The grade of the student in the gradebook.
     *
     * @return float|null
     */
    private function grade(): ?float {
        $grades = grade_get_grades($this->course->id, 'mod', 'oksigeniajepyso', $this->jepyso->id, $this->student->id);
        $grade = $grades->items[0]->grades[$this->student->id]->grade;
        return $grade === null ? null : (float) $grade;
    }

    /**
     * The completion state of the student.
     *
     * @return int
     */
    private function completion(): int {
        $cm = get_fast_modinfo($this->course)->get_cm($this->jepyso->cmid);
        return (int) (new \completion_info($this->course))->get_data($cm, false, $this->student->id)->completionstate;
    }

    /**
     * A student saves; completion and grade follow the sessions done.
     */
    public function test_student_saves(): void {
        $this->setUser($this->student);
        $sink = $this->redirectEvents();
        $result = $this->save(self::sessions(['2026-11-10', '2026-11-12']));
        $this->assertSame(['saved' => 2, 'done' => 2, 'target' => 3, 'completed' => false], $result);
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \mod_oksigeniajepyso\event\session_saved);
        $this->assertCount(1, $events);
        $this->assertSame(2, reset($events)->other['saved']);
        $sink->close();
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion());
        $this->assertEqualsWithDelta(10 * 2 / 3, $this->grade(), 0.001);

        // The same days again change nothing; a third one completes the activity.
        $this->assertSame(0, $this->save(self::sessions(['2026-11-10', '2026-11-12']))['saved']);
        $result = $this->save(self::sessions(['2026-11-10', '2026-11-12', '2026-11-14']));
        $this->assertSame(['saved' => 1, 'done' => 3, 'target' => 3, 'completed' => true], $result);
        $this->assertSame(COMPLETION_COMPLETE, $this->completion());
        $this->assertEqualsWithDelta(10.0, $this->grade(), 0.001);

        // More sessions than asked for never give more than the maximum grade.
        $this->save(self::sessions(['2026-11-16']));
        $this->assertEqualsWithDelta(10.0, $this->grade(), 0.001);

        $sessions = json_decode(get_sessions::execute($this->jepyso->cmid)['sessions']);
        $this->assertCount(4, $sessions);
        $this->assertSame('2026-11-10', $sessions[0]->f);
    }

    /**
     * Records that are not sessions are left out; the call fails when it is not a list or goes over the limits.
     */
    public function test_wrong_records(): void {
        $this->setUser($this->student);
        $result = $this->save(json_encode([['f' => 'mañana', 'e' => []], 'x', ['f' => '2026-11-10', 'e' => []]]));
        $this->assertSame(1, $result['saved']);
        $this->assertSame(0, $result['done']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->save(json_encode(array_fill(0, 401, ['f' => '2026-11-10', 'e' => []])));
    }

    /**
     * Who cannot do the routine cannot save: a teacher trying it out.
     */
    public function test_teacher_refused(): void {
        $this->setUser($this->teacher);
        $this->expectException(\required_capability_exception::class);
        $this->save(self::sessions(['2026-11-10']));
    }

    /**
     * A student of another course cannot save here.
     */
    public function test_other_course_refused(): void {
        $other = $this->getDataGenerator()->create_course();
        $outsider = $this->getDataGenerator()->create_and_enrol($other, 'student');
        $this->setUser($outsider);
        $this->expectException(\require_login_exception::class);
        $this->save(self::sessions(['2026-11-10']));
    }

    /**
     * Each user gets only their own sessions.
     */
    public function test_get_own_sessions(): void {
        $this->setUser($this->student);
        $this->save(self::sessions(['2026-11-10']));
        $this->setUser($this->teacher);
        $result = external_api::clean_returnvalue(get_sessions::execute_returns(), get_sessions::execute($this->jepyso->cmid));
        $this->assertSame('[]', $result['sessions']);
    }

    /**
     * Without a number of sessions, there is no automatic grade and no completion by sessions.
     */
    public function test_without_target(): void {
        global $DB;
        $DB->set_field('oksigeniajepyso', 'sessions', 0, ['id' => $this->jepyso->id]);
        $this->setUser($this->student);
        $result = $this->save(self::sessions(['2026-11-10']));
        $this->assertSame(['saved' => 1, 'done' => 1, 'target' => 0, 'completed' => false], $result);
        $this->assertNull($this->grade());
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion());
    }
}
