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

namespace mod_oksigeniajepyso\output;

/**
 * Tests of what the pages show beyond the words: the marks of the sessions, what there is to look at in a student and
 * the discomfort week by week.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\output\report_data
 * @covers     \mod_oksigeniajepyso\output\view_data
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report_data::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(view_data::class)]
final class report_data_test extends \advanced_testcase {
    /**
     * A mark for each planned session up to 60, filled as they are done; past that, a bar in twentieths.
     */
    public function test_tally(): void {
        $tally = view_data::tally(2, 6);
        $this->assertTrue($tally['hastally']);
        $this->assertSame([true, true, false, false, false, false], array_column($tally['tallymarks'], 'markdone'));
        $this->assertFalse($tally['hasbar']);

        $tally = view_data::tally(8, 6);
        $this->assertSame(6, count(array_filter(array_column($tally['tallymarks'], 'markdone'))));

        $tally = view_data::tally(37, 120);
        $this->assertFalse($tally['hastally']);
        $this->assertTrue($tally['hasbar']);
        $this->assertSame(6, $tally['twentieths']);

        $tally = view_data::tally(3, 0);
        $this->assertFalse($tally['hastally']);
        $this->assertFalse($tally['hasbar']);
    }

    /**
     * What there is to look at, in words and only when there is something, the days above the limit first.
     */
    public function test_alerts(): void {
        $alerts = report_data::alerts(['flagged' => 2, 'hard' => 1, 'couldnot' => 0, 'notes' => 3]);
        $this->assertSame([
            ['alerttext' => '2 days above the acceptable level', 'alertstrong' => true],
            ['alerttext' => 'Found it hard once', 'alertstrong' => false],
            ['alerttext' => '3 notes', 'alertstrong' => false],
        ], $alerts);
        $this->assertSame([], report_data::alerts(['flagged' => 0, 'hard' => 0, 'couldnot' => 0, 'notes' => 0]));
    }

    /**
     * Week by week, from Monday, oldest first: the sessions and the highest discomfort during the exercises and on
     * getting up, and whether either went up from the week before.
     */
    public function test_weeks(): void {
        $this->resetAfterTest();
        $record = fn(string $day, array $during, ?int $waking = null) => (object) [
            'day' => $day,
            'session' => ['f' => $day, 'e' => array_map(fn($m) => ['i' => 'silla', 'm' => $m], $during)]
                + ($waking === null ? [] : ['a' => $waking]),
        ];
        // In any order, as the follow-up has them, newest first.
        $weeks = report_data::weeks([
            $record('2026-11-23', [5]),
            $record('2026-11-22', []),
            $record('2026-11-16', [4], 4),
            $record('2026-11-11', [6, 1]),
            $record('2026-11-09', [3], 2),
        ], 5);

        $this->assertCount(3, $weeks);
        $this->assertSame([2, 2, 1], array_column($weeks, 'weeksessions'));
        $this->assertSame([false, true, true], array_column($weeks, 'weekup'));
        $this->assertSame(['hasvalue' => true, 'value' => 6, 'valueover' => true, 'nonzero' => true], $weeks[0]['during']);
        $this->assertSame(2, $weeks[0]['waking']['value']);
        $this->assertSame(4, $weeks[1]['during']['value']);
        $this->assertFalse($weeks[1]['during']['valueover']);
        $this->assertFalse($weeks[2]['waking']['hasvalue']);
        $this->assertStringContainsString('9', $weeks[0]['weekof']);
        $this->assertStringContainsString('16', $weeks[1]['weekof']);
    }
}
