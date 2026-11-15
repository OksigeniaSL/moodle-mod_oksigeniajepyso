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

namespace mod_oksigeniajepyso\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\session_cleaner;
use mod_oksigeniajepyso\local\sessions;

/**
 * Tests of the privacy provider.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass[] Two activities in one course. */
    private $activities = [];

    /** @var \stdClass[] Two students. */
    private $students = [];

    /**
     * Two activities, two students, sessions of both in both.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        catalog::use_file(__DIR__ . '/../fixtures/catalogo.json');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        foreach ([1, 2] as $n) {
            $this->activities[$n] = $generator->create_module('oksigeniajepyso', ['course' => $course->id]);
            $this->students[$n] = $generator->create_and_enrol($course, 'student');
        }
        foreach ($this->activities as $activity) {
            foreach ($this->students as $student) {
                $json = '{"f":"2026-11-15","h":"18:00","e":[{"i":"silla","p":[8,8],"n":"Bien"}]}';
                $session = session_cleaner::clean(json_decode($json));
                sessions::upsert((int) $activity->id, (int) $student->id, $session, 5);
            }
        }
    }

    /**
     * Back to the catalogue of the player.
     */
    public function tearDown(): void {
        catalog::use_file(null);
        parent::tearDown();
    }

    /**
     * The context of an activity.
     *
     * @param int $n Which one.
     * @return \context_module
     */
    private function context(int $n): \context_module {
        return \context_module::instance($this->activities[$n]->cmid);
    }

    /**
     * How many sessions an activity keeps.
     *
     * @param int $n Which one.
     * @return int
     */
    private function kept(int $n): int {
        global $DB;
        return $DB->count_records('oksigeniajepyso_session', ['jepysoid' => $this->activities[$n]->id]);
    }

    /**
     * What is kept is declared.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('mod_oksigeniajepyso'))->get_collection();
        $this->assertCount(1, $items);
        $this->assertSame('oksigeniajepyso_session', reset($items)->get_name());
    }

    /**
     * The contexts of a user, and the users of a context.
     */
    public function test_contexts_and_users(): void {
        $contexts = provider::get_contexts_for_userid($this->students[1]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->context(1)->id, $this->context(2)->id], array_map('intval', $contexts));
        $userlist = new userlist($this->context(1), 'mod_oksigeniajepyso');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->students[1]->id, $this->students[2]->id], $userlist->get_userids());
    }

    /**
     * The sessions of a user are exported with their activity.
     */
    public function test_export_user_data(): void {
        $user = $this->students[1];
        $this->export_context_data_for_user($user->id, $this->context(1), 'mod_oksigeniajepyso');
        $writer = writer::with_context($this->context(1));
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([get_string('privacy:sessions', 'mod_oksigeniajepyso')]);
        $this->assertCount(1, $data->sessions);
        $this->assertSame('2026-11-15', $data->sessions[0]->day);
        $this->assertSame('Bien', $data->sessions[0]->record->e[0]->n);
    }

    /**
     * Deleting for everyone in a context leaves the other activity untouched.
     */
    public function test_delete_for_context(): void {
        provider::delete_data_for_all_users_in_context($this->context(1));
        $this->assertSame(0, $this->kept(1));
        $this->assertSame(2, $this->kept(2));
    }

    /**
     * Deleting for a user takes only theirs, in the contexts approved.
     */
    public function test_delete_for_user(): void {
        $list = new approved_contextlist($this->students[1], 'mod_oksigeniajepyso', [$this->context(1)->id]);
        provider::delete_data_for_user($list);
        $this->assertSame(1, $this->kept(1));
        $this->assertSame(2, $this->kept(2));
    }

    /**
     * Deleting for some users of a context.
     */
    public function test_delete_for_users(): void {
        $list = new approved_userlist($this->context(2), 'mod_oksigeniajepyso', [$this->students[2]->id]);
        provider::delete_data_for_users($list);
        $this->assertSame(2, $this->kept(1));
        $this->assertSame(1, $this->kept(2));
    }
}
