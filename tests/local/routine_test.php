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
 * Tests of the routine codes: decoding, cleaning, checking and describing.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\local\routine
 */
#[\PHPUnit\Framework\Attributes\CoversClass(routine::class)]
final class routine_test extends \advanced_testcase {
    /** @var string The routine of the app (silla, puente, hombro), compressed. */
    private const DEFLATED = 'd.bc3BCoJAFIXhV4l_fQVnTIn7DO1aigvLiYRRY9QKxHcPlSCo3Vl8h3_igRrhXoYSBaFyKMex7peN7lPhtZInmggNmgoXlBLhim'
        . 'Jjm0XGRCZFcGg-UaP0tfcL6VErBPQgDGhuxYgtBI-2o_ezbPw-unZwm09Wb-LPwf473LrmHLrvQPYToHIBoUU51e2u6nwXmIv5DQ';

    /** @var string The same routine, as plain JSON. */
    private const PLAIN = 'j.eyJ2IjoxLCJwYXJhIjoiIiwiZGUiOiJMdWlzIiwiZCI6NDUsIngiOjEsInciOjMsIm0iOjUsImMiOiJhIiwiZiI6Ij'
        . 'IwMjYtMTEtMTUiLCJlIjpbeyJpIjoic2lsbGEiLCJzIjoyLCJyIjo4LCJ0IjpbMiwxLDJdLCJsIjpudWxsfSx7ImkiOiJwdWVudGUiLCJzIjoz'
        . 'LCJyIjoxMCwidCI6WzIsMiwyXSwibCI6bnVsbH0seyJpIjoiaG9tYnJvIiwicyI6MiwiciI6NiwidCI6WzIsMSwyXSwibCI6ImRlciIsIm4iOiJT'
        . 'aW4gZG9sb3IifV19';

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
     * A routine code as plain JSON.
     *
     * @param mixed $data The routine.
     * @return string
     */
    private static function plain($data): string {
        return 'j.' . rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }

    /**
     * Both forms of the code of the app decode to the same routine.
     */
    public function test_decode_fixtures(): void {
        $deflated = routine::parse(self::DEFLATED);
        $plain = routine::parse(self::PLAIN);
        $this->assertNotNull($deflated);
        $this->assertSame($deflated, $plain);
        $this->assertSame(['silla', 'puente', 'hombro'], array_column($deflated['e'], 'i'));
        $this->assertSame(45, $deflated['d']);
        $this->assertSame(3, $deflated['w']);
        $this->assertSame(5, $deflated['m']);
        $this->assertSame('a', $deflated['c']);
        $this->assertSame('2026-11-15', $deflated['f']);
        $this->assertSame('Luis', $deflated['de']);
        $this->assertSame(
            ['i' => 'hombro', 's' => 2, 'r' => 6, 't' => [2, 1, 2], 'l' => 'der', 'b' => null, 'n' => 'Sin dolor'],
            $deflated['e'][2]
        );
        $this->assertNull($deflated['e'][0]['l']);
    }

    /**
     * A whole link gives its code; so does the code alone.
     */
    public function test_code_from_link(): void {
        $this->assertSame(self::DEFLATED, routine::code_from('https://app.jepyso.com/#r=' . self::DEFLATED));
        $this->assertSame(self::DEFLATED, routine::code_from("  https://app.jepyso.com/?x=1#r=" . self::DEFLATED . "&v=2\n"));
        $this->assertSame(self::PLAIN, routine::code_from(' ' . self::PLAIN . ' '));
        $this->assertSame('', routine::code_from('   '));
        $this->assertNotNull(routine::parse(routine::code_from('https://app.jepyso.com/#r=' . self::DEFLATED)));
    }

    /**
     * What is not a routine does not decode.
     */
    public function test_broken_codes(): void {
        $this->assertNull(routine::parse(''));
        $this->assertNull(routine::parse('x.abc'));
        $this->assertNull(routine::parse('d.!!!'));
        $this->assertNull(routine::parse('d.YWJjZGVm'));
        $this->assertNull(routine::parse(substr(self::DEFLATED, 0, 60)));
        $this->assertNull(routine::parse(self::plain([1, 2])));
        $this->assertNull(routine::parse(self::plain(['v' => 1])));
        $this->assertNull(routine::parse(self::plain(['v' => 1, 'e' => 'silla'])));
        $this->assertNull(routine::parse('j.' . str_repeat('A', routine::MAXLENGTH)));
        $this->assertNull(routine::parse('https://app.jepyso.com/#r=' . self::DEFLATED));
    }

    /**
     * Values out of range are brought within it, as the app does.
     */
    public function test_clean_ranges(): void {
        $exercises = [
            ['i' => 'silla', 's' => 9, 'r' => 99, 't' => [0, 99, 20], 'l' => 'xx', 'b' => 'rosa', 'n' => str_repeat('a', 5000)],
            ['i' => 'hombro', 's' => 0, 'r' => 0, 'l' => 'xx', 'b' => 'roja'],
            ['i' => 'caminar', 'r' => 99],
            ['i' => 'Bad-Id'],
            'silla',
        ];
        $routine = routine::parse(self::plain(['d' => 50, 'x' => 9, 'w' => 8, 'm' => 12, 'c' => 'z', 'f' => '2026-02-30',
            'e' => $exercises]));
        $this->assertSame(45, $routine['d']);
        $this->assertSame(3, $routine['x']);
        $this->assertNull($routine['w']);
        $this->assertSame(9, $routine['m']);
        $this->assertSame('a', $routine['c']);
        $this->assertNull($routine['f']);
        $this->assertCount(3, $routine['e']);
        [$silla, $hombro, $caminar] = $routine['e'];
        $this->assertSame([6, 30, [1, 30, 12], null, null], [$silla['s'], $silla['r'], $silla['t'], $silla['l'], $silla['b']]);
        $this->assertSame(4000, \core_text::strlen($silla['n']));
        $this->assertSame([1, 1, 'der', 'roja'], [$hombro['s'], $hombro['r'], $hombro['l'], $hombro['b']]);
        $this->assertSame(60, $caminar['r']);

        $many = routine::parse(self::plain(['e' => array_fill(0, 45, ['i' => 'silla'])]));
        $this->assertCount(40, $many['e']);
        $this->assertSame(routine::LIMIT, routine::limit($many));
        $this->assertSame(routine::LIMIT, routine::limit(null));
    }

    /**
     * The check says what is wrong, and what the site cannot play.
     */
    public function test_check(): void {
        $ok = routine::check('https://app.jepyso.com/#r=' . self::DEFLATED);
        $this->assertSame(['code' => self::DEFLATED, 'error' => null, 'unavailable' => [], 'unknown' => []], $ok);

        $partial = routine::check(self::plain(['e' => [['i' => 'silla'], ['i' => 'plancha'], ['i' => 'nada']]]));
        $this->assertNull($partial['error']);
        $this->assertSame(['Plancha frontal'], $partial['unavailable']);
        $this->assertSame(['nada'], $partial['unknown']);
        $this->assertCount(2, routine::warnings($partial));

        $this->assertSame('routinenoexercises', routine::check(self::plain(['e' => [['i' => 'nada']]]))['error']);
        $this->assertSame('routineinvalid', routine::check('d.nothing')['error']);
        $this->assertSame('routineempty', routine::check('  ')['error']);

        catalog::use_file(__DIR__ . '/../fixtures/missing.json');
        $this->assertSame('noplayer', routine::check(self::DEFLATED)['error']);
        $this->assertSame([get_string('noplayer', 'mod_oksigeniajepyso')], routine::warnings_for(self::DEFLATED));
    }

    /**
     * The dose of each exercise, in words.
     */
    public function test_describe(): void {
        $routine = routine::parse(self::plain(['w' => 3, 'x' => 2, 'e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 't' => [2, 1, 2]],
            ['i' => 'hombro', 's' => 1, 'r' => 6, 't' => [3, 0, 3], 'l' => 'izq', 'b' => 'verde', 'n' => 'Despacio'],
            ['i' => 'caminar', 's' => 1, 'r' => 5],
            ['i' => 'plancha', 's' => 3, 'r' => 30],
            ['i' => 'nada', 's' => 3, 'r' => 30],
        ]]));
        $described = routine::describe($routine);
        $this->assertCount(4, $described['exercises']);
        [$silla, $hombro, $caminar, $plancha] = $described['exercises'];
        $this->assertSame('Levantarse de la silla', $silla['name']);
        $this->assertSame('2 sets of 8 · out 2 s, hold 1 s, back 2 s', $silla['dosage']);
        $this->assertSame('', $silla['side']);
        $this->assertSame('1 set of 6 · out 3 s, back 3 s', $hombro['dosage']);
        $this->assertSame('Left side', $hombro['side']);
        $this->assertTrue($hombro['mirrored']);
        $this->assertSame('Green band', $hombro['band']);
        $this->assertSame('Despacio', $hombro['note']);
        $this->assertSame('1 set of 5 min', $caminar['dosage']);
        $this->assertSame('3 sets of 30 s', $plancha['dosage']);
        $this->assertFalse($plancha['available']);
        $this->assertSame('Days a week: 3', $described['days']);
        $this->assertSame('2 times a day', $described['times']);
        $this->assertSame('Rest between sets: 45 s', $described['rest']);
        $this->assertSame('Acceptable discomfort: up to 5 out of 10', $described['limit']);
        $this->assertSame('1 step', routine::amount(1, 'pasos'));
        $this->assertSame('3 laps', routine::amount(3, 'vueltas'));
    }
}
