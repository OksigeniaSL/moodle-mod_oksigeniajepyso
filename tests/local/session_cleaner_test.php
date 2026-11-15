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
 * Tests of the cleaning of a session, the port of limpiarDia of the app.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\local\session_cleaner
 * @covers     \mod_oksigeniajepyso\local\value
 */
#[\PHPUnit\Framework\Attributes\CoversClass(session_cleaner::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(value::class)]
final class session_cleaner_test extends \advanced_testcase {
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
     * A session cleaned from its JSON, decoded into objects as the web service does.
     *
     * @param string $json The session as JSON.
     * @return array|null
     */
    private static function clean(string $json): ?array {
        return session_cleaner::clean(json_decode($json));
    }

    /**
     * Integers out of range are brought within it; what is no integer takes the default.
     */
    public function test_clamps(): void {
        $session = self::clean('{"f":"2026-11-15","e":[{"i":"silla","s":9,"r":40,"p":[60,-3,2.0,2.5,"4",5,6,7],"m":12}],"a":-1}');
        $this->assertSame([
            'f' => '2026-11-15',
            'e' => [['i' => 'silla', 'l' => null, 's' => 6, 'r' => 30, 'p' => [50, 0, 2, 0, 0, 5], 'm' => 10]],
            'a' => 0,
        ], $session);

        $session = self::clean('{"f":"2026-11-15","e":[{"i":"puente","r":0,"p":[1,2,3]},{"i":"silla"}]}');
        $this->assertSame([3, 1, [1, 2, 3]], [$session['e'][0]['s'], $session['e'][0]['r'], $session['e'][0]['p']]);
        $this->assertSame([1, 1, []], [$session['e'][1]['s'], $session['e'][1]['r'], $session['e'][1]['p']]);
    }

    /**
     * Unknown keys and wrong values are dropped.
     */
    public function test_drops(): void {
        $long = str_repeat('é', 400);
        $json = json_encode([
            'f' => '2026-11-15', 'h' => '25:00', 'ed' => 'ayer', 'sinApp' => 'true', 'x' => 'y', 'e' => [
                ['i' => 'silla', 'p' => [8], 'c' => 'maybe', 'n' => "   \n ", 'z' => 1],
                ['i' => 'puente', 'p' => [8], 'c' => 'cuesta', 'n' => "  Me cuesta  "],
                ['i' => 'hombro', 'p' => [8], 'c' => 'no', 'n' => $long, 'l' => 'izq'],
                ['i' => 'hombro', 'p' => [8], 'l' => 'arriba'],
                ['i' => 'Bad-Id', 'p' => [8]],
                'silla',
            ],
        ]);
        $session = self::clean($json);
        $this->assertSame(['f', 'e'], array_keys($session));
        $this->assertCount(4, $session['e']);
        $this->assertSame(['i', 'l', 's', 'r', 'p'], array_keys($session['e'][0]));
        $this->assertSame(['cuesta', 'Me cuesta'], [$session['e'][1]['c'], $session['e'][1]['n']]);
        $this->assertSame('no', $session['e'][2]['c']);
        $this->assertSame(session_cleaner::NOTE, \core_text::strlen($session['e'][2]['n']));
        $this->assertSame('izq', $session['e'][2]['l']);
        // A side that is none: the right one for an exercise done on one side, none for the others.
        $this->assertSame('der', $session['e'][3]['l']);
        $this->assertNull($session['e'][0]['l']);
    }

    /**
     * The fields of the day pass only when they are right.
     */
    public function test_day_fields(): void {
        $this->assertNull(self::clean('{"f":"2026-02-30","e":[]}'));
        $this->assertNull(self::clean('{"f":"15/11/2026","e":[]}'));
        $this->assertNull(self::clean('{"e":[]}'));
        $this->assertNull(self::clean('{"f":"2026-11-15","e":{}}'));
        $this->assertNull(self::clean('[{"f":"2026-11-15","e":[]}]'));
        $this->assertNull(self::clean('"2026-11-15"'));

        $session = self::clean('{"f":"2026-11-15","h":"07:05","e":[],"a":4,"ed":"2026-11-16T08:00:00.000Z","sinApp":true}');
        $this->assertSame(
            ['f' => '2026-11-15', 'h' => '07:05', 'e' => [], 'a' => 4, 'ed' => '2026-11-16T08:00:00.000Z', 'sinApp' => true],
            $session
        );
        $toolong = self::clean('{"f":"2026-11-15","e":[],"ed":"2026-11-16T08:00:00.000000000+01:00' . str_repeat(' ', 10) . '"}');
        $this->assertArrayNotHasKey('ed', $toolong);
    }

    /**
     * A session has 40 exercises at most, and 6 sets each.
     */
    public function test_limits(): void {
        $session = session_cleaner::clean((object) [
            'f' => '2026-11-15',
            'e' => array_fill(0, 45, (object) ['i' => 'silla', 'p' => array_fill(0, 9, 3)]),
        ]);
        $this->assertCount(session_cleaner::EXERCISES, $session['e']);
        $this->assertCount(session_cleaner::SETS, $session['e'][0]['p']);
        $this->assertSame(6, $session['e'][0]['s']);
    }

    /**
     * Done: some repetition, or a day noted down without the player. Flagged: discomfort above the acceptable one.
     */
    public function test_done_and_flagged(): void {
        $done = self::clean('{"f":"2026-11-15","e":[{"i":"silla","p":[0,1],"m":5}],"a":5}');
        $this->assertTrue(session_cleaner::is_done($done));
        $this->assertFalse(session_cleaner::is_flagged($done, 5));
        $this->assertTrue(session_cleaner::is_flagged($done, 4));

        $nothing = self::clean('{"f":"2026-11-15","e":[{"i":"silla","p":[0,0],"m":6}]}');
        $this->assertFalse(session_cleaner::is_done($nothing));
        $this->assertTrue(session_cleaner::is_flagged($nothing, 5));
        $this->assertFalse(session_cleaner::is_flagged($nothing, 7));

        $noted = self::clean('{"f":"2026-11-15","e":[],"sinApp":true,"a":8}');
        $this->assertTrue(session_cleaner::is_done($noted));
        $this->assertTrue(session_cleaner::is_flagged($noted, 5));
    }
}
