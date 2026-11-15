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

use mod_oksigeniajepyso\local\catalog;
use mod_oksigeniajepyso\local\template;

/**
 * Tests of the page of the activity in the Moodle app.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\output\mobile
 * @covers     \mod_oksigeniajepyso\local\template
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mobile::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(template::class)]
final class mobile_test extends \advanced_testcase {
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
     * The page opens the player in the browser of the app with the session, and nothing written by people compiles.
     */
    public function test_page(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $note = 'j.' . rtrim(strtr(base64_encode(json_encode(['e' => [
            ['i' => 'silla', 's' => 2, 'r' => 8, 'n' => '{{ constructor }} &#123;&#123;x&#125;&#125; @if (x) {'],
        ]])), '+/', '-_'), '=');
        $jepyso = $generator->create_module('oksigeniajepyso', [
            'course' => $course->id,
            'routine' => $note,
            'sessions' => 2,
            'intro' => '<p>{{ x }}</p>',
        ]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = mobile::mobile_course_view(['cmid' => $jepyso->cmid, 'courseid' => $course->id]);
        $html = $result['templates'][0]['html'];
        $url = (new \moodle_url('/mod/oksigeniajepyso/player.php', ['id' => $jepyso->cmid]))->out(false);
        $this->assertStringContainsString('core-link inApp="true" autoLogin="yes" href="' . s($url) . '"', $html);
        $this->assertStringContainsString('Levantarse de la silla', $html);
        $this->assertStringContainsString('2 sets of 8', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertStringNotContainsString('&#123;', $html);
        $this->assertStringNotContainsString('@if', $html);
        // Only the fixed lines that ask for the page again when the in-app browser closes.
        $this->assertStringContainsString('CoreEventsProvider.IAB_EXIT', $result['javascript']);
        $this->assertStringContainsString('refreshContent(false)', $result['javascript']);
        $this->assertStringNotContainsString('oksigeniajepyso', $result['javascript']);
        // The description goes as data, for the app to show; it never enters the template.
        $this->assertStringContainsString('{{ x }}', $result['otherdata']['intro']);
        $this->assertStringNotContainsString('{{ x }}', $html);
    }
}
