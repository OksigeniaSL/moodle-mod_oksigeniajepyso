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

namespace mod_oksigeniajepyso\local;

/**
 * Tests of how sessions are kept: one per day and time, corrections and the answer about the next morning.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\local\sessions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sessions::class)]
final class sessions_test extends \advanced_testcase {
    /**
     * Reads the catalogue of the tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * A session cleaned.
     *
     * @param array $session The session.
     * @return array
     */
    private static function session(array $session): array {
        return session_cleaner::clean(json_decode(json_encode($session)));
    }

    /**
     * Keeps a session of the student 3 in the activity 7, with an acceptable discomfort of 5.
     *
     * @param array $session The session, before cleaning.
     * @return bool Whether something was written.
     */
    private static function put(array $session): bool {
        return sessions::upsert(7, 3, self::session($session), 5);
    }

    /**
     * The record kept for a day.
     *
     * @param string $day The day.
     * @param string $hm The time.
     * @return \stdClass
     */
    private function kept(string $day, string $hm = ''): \stdClass {
        global $DB;
        $key = ['jepysoid' => 7, 'userid' => 3, 'day' => $day, 'hm' => $hm];
        $record = $DB->get_record('oksigeniajepyso_session', $key, '*', MUST_EXIST);
        $record->session = json_decode($record->data, true);
        return $record;
    }

    /**
     * A day is kept once, and a later or equal copy replaces it; an older one does not.
     */
    public function test_replace_by_correction(): void {
        global $DB;
        $first = self::session(['f' => '2026-11-15', 'h' => '18:00', 'e' => [['i' => 'silla', 'p' => [8, 8], 'm' => 2]]]);
        $this->assertTrue(sessions::upsert(7, 3, $first, 5));
        $this->assertFalse(sessions::upsert(7, 3, $first, 5), 'The same copy again changes nothing');

        $corrected = self::session(['f' => '2026-11-15', 'h' => '18:00', 'ed' => '2026-11-16T09:00:00.000Z',
            'e' => [['i' => 'silla', 'p' => [8, 6], 'm' => 7]]]);
        $this->assertTrue(sessions::upsert(7, 3, $corrected, 5));
        $this->assertSame([8, 6], $this->kept('2026-11-15', '18:00')->session['e'][0]['p']);
        $this->assertSame('1', (string) $this->kept('2026-11-15', '18:00')->flagged);

        // The copy before the correction comes again from another phone: the corrected day stays.
        $this->assertFalse(sessions::upsert(7, 3, $first, 5));
        $this->assertSame([8, 6], $this->kept('2026-11-15', '18:00')->session['e'][0]['p']);

        // Another time of the same day is another session.
        $this->assertTrue(sessions::upsert(7, 3, self::session(['f' => '2026-11-15', 'h' => '20:00', 'e' => []]), 5));
        $this->assertTrue(sessions::upsert(7, 3, self::session(['f' => '2026-11-15', 'e' => []]), 5));
        $this->assertSame(3, $DB->count_records('oksigeniajepyso_session', ['jepysoid' => 7, 'userid' => 3]));
        $this->assertSame('0', (string) $this->kept('2026-11-15', '20:00')->done);
        $this->assertSame(1, sessions::done(7, 3));
        $this->assertSame('2026-11-15', sessions::last_day(7, 3));
    }

    /**
     * The answer about the next morning is never lost.
     */
    public function test_keep_next_morning(): void {
        $day = ['f' => '2026-11-15', 'e' => [['i' => 'puente', 'p' => [10]]]];
        sessions::upsert(7, 3, self::session($day + ['a' => 3]), 5);
        // A copy without the answer, sent again: the answer stays.
        $this->assertTrue(self::put(['f' => '2026-11-15', 'e' => [['i' => 'puente', 'p' => [9]]]]));
        $kept = $this->kept('2026-11-15');
        $this->assertSame(3, $kept->session['a']);
        $this->assertSame([9], $kept->session['e'][0]['p']);

        // A day corrected afterwards, without the answer; then an older copy that brings one: only the answer is taken.
        self::put(['f' => '2026-11-14', 'ed' => '2026-11-15T10:00:00Z', 'e' => [['i' => 'silla', 'p' => [5]]]]);
        $this->assertTrue(self::put(['f' => '2026-11-14', 'a' => 7, 'e' => [['i' => 'silla', 'p' => [1]]]]));
        $kept = $this->kept('2026-11-14');
        $this->assertSame([7, [5]], [$kept->session['a'], $kept->session['e'][0]['p']]);
        $this->assertSame('1', (string) $kept->flagged);
    }

    /**
     * The calls bring a JSON list within limits.
     */
    public function test_decode_list(): void {
        $this->assertCount(2, sessions::decode_list('[{"f":"2026-11-15","e":[]},3]'));
        $this->assertSame([], sessions::decode_list('[]'));
        foreach (['{"f":"2026-11-15"}', 'nothing', json_encode(array_fill(0, sessions::RECORDS + 1, 1))] as $wrong) {
            try {
                sessions::decode_list($wrong);
                $this->fail('Accepted: ' . substr($wrong, 0, 40));
            } catch (\invalid_parameter_exception $e) {
                $this->assertInstanceOf(\invalid_parameter_exception::class, $e);
            }
        }
        $this->expectException(\invalid_parameter_exception::class);
        sessions::decode_list('["' . str_repeat('a', sessions::BYTES) . '"]');
    }

    /**
     * The summary of each student for the follow-up.
     */
    public function test_summaries(): void {
        self::put(['f' => '2026-11-13', 'e' => [['i' => 'silla', 'p' => [8], 'm' => 7, 'c' => 'cuesta', 'n' => 'Uf']]]);
        self::put(['f' => '2026-11-13', 'h' => '20:00', 'e' => [['i' => 'silla', 'p' => [8], 'm' => 8]]]);
        self::put(['f' => '2026-11-15', 'e' => [['i' => 'silla', 'p' => [0], 'c' => 'no']]]);
        $summaries = sessions::summaries(7, [3, 4]);
        $this->assertSame(
            ['done' => 2, 'last' => '2026-11-13', 'flagged' => 1, 'hard' => 1, 'couldnot' => 1, 'notes' => 1],
            $summaries[3]
        );
        $this->assertSame(0, $summaries[4]['done']);
        $this->assertNull($summaries[4]['last']);
        $this->assertCount(3, sessions::for_player(7, 3));
        $this->assertSame('2026-11-13', sessions::for_player(7, 3)[0]->f);
    }
}
