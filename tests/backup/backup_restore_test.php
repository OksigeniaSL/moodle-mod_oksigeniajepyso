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

namespace mod_oksigeniajepyso\backup;

use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\session_cleaner;
use mod_oksigeniajepyso\local\sessions;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests of backup and restore: the activity comes back with its settings and, with users, the sessions.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_oksigeniajepyso_activity_task
 * @covers     \restore_oksigeniajepyso_activity_task
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_oksigeniajepyso_activity_task::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_oksigeniajepyso_activity_task::class)]
final class backup_restore_test extends \advanced_testcase {
    /**
     * Reads the catalogue of the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        catalog::use_file(__DIR__ . '/../fixtures/catalogo.json');
    }

    /**
     * Back to the catalogue of the player.
     */
    protected function tearDown(): void {
        catalog::use_file(null);
        parent::tearDown();
    }

    /**
     * A course backed up with users and restored as a new course.
     *
     * @param \stdClass $course The course.
     * @return int The new course.
     */
    private function backup_and_restore(\stdClass $course): int {
        global $CFG, $USER;
        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_2', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newid;
    }

    /**
     * The activity and the sessions of its students make the round trip.
     */
    public function test_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $jepyso = $generator->create_module('oksigeniajepyso', ['course' => $course->id, 'sessions' => 4, 'grade' => 10,
            'name' => 'Fuerza']);
        $student = $generator->create_and_enrol($course, 'student');
        $days = ['{"f":"2026-11-15","h":"18:00","e":[{"i":"silla","p":[8,8],"m":7}],"a":2}', '{"f":"2026-11-16","e":[]}'];
        foreach ($days as $json) {
            sessions::upsert((int) $jepyso->id, (int) $student->id, session_cleaner::clean(json_decode($json)), 5);
        }

        $newcourse = $this->backup_and_restore($course);

        $restored = $DB->get_record('oksigeniajepyso', ['course' => $newcourse], '*', MUST_EXIST);
        $this->assertSame('Fuerza', $restored->name);
        $this->assertSame($jepyso->routine, $restored->routine);
        $this->assertSame(4, (int) $restored->sessions);
        $this->assertSame(10, (int) $restored->grade);
        $records = $DB->get_records('oksigeniajepyso_session', ['jepysoid' => $restored->id], 'day ASC');
        $this->assertCount(2, $records);
        [$first, $second] = array_values($records);
        $this->assertSame((int) $student->id, (int) $first->userid);
        $this->assertSame(['2026-11-15', '18:00', 1, 1], [$first->day, $first->hm, (int) $first->done, (int) $first->flagged]);
        $this->assertSame(['2026-11-16', ''], [$second->day, (string) $second->hm]);
        $this->assertSame(2, json_decode($first->data)->a);
    }
}
