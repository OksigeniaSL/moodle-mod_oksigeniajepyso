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

/**
 * Tests of the page of the player and of the builder, and of what the player that comes with the plugin has.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\output\player_page
 */
#[\PHPUnit\Framework\Attributes\CoversClass(player_page::class)]
final class player_page_test extends \advanced_testcase {
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
     * The page of an activity, for a student, a teacher and a guest.
     */
    public function test_activity(): void {
        global $DB, $PAGE;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $module = $generator->create_module('oksigeniajepyso', ['course' => $course->id, 'name' => 'Strength & <b>balance</b>']);
        [, $cm] = get_course_and_cm_from_cmid($module->cmid, 'oksigeniajepyso');
        $jepyso = $DB->get_record('oksigeniajepyso', ['id' => $module->id], '*', MUST_EXIST);
        $output = $PAGE->get_renderer('core');

        $student = $generator->create_and_enrol($course, 'student', ['firstname' => 'Ana']);
        $this->setUser($student);
        $data = player_page::for_activity($jepyso, $cm)->export_for_template($output);
        $this->assertSame([(string) $cm->id, 'Ana', true], [$data['cmid'], $data['firstname'], $data['cansubmit']]);
        $this->assertSame('Strength & balance', $data['title']);
        $this->assertSame(sesskey(), $data['sesskey']);
        $this->assertStringEndsWith('/lib/ajax/service.php', $data['service']);
        $this->assertStringEndsWith('/mod/oksigeniajepyso/view.php?id=' . $cm->id, $data['backurl']);
        $this->assertMatchesRegularExpression('/^[dj]\\.[A-Za-z0-9_-]+$/', $data['code']);

        $html = $output->render_from_template('mod_oksigeniajepyso/player', $data);
        $this->assertStringContainsString('data-cm="' . $cm->id . '"', $html);
        $this->assertStringContainsString('data-puede="1"', $html);
        $this->assertStringContainsString('<title>Strength &amp; balance</title>', $html);
        $this->assertStringContainsString("connect-src 'self';", $html);

        $this->setUser($generator->create_and_enrol($course, 'editingteacher'));
        $data = player_page::for_activity($jepyso, $cm)->export_for_template($output);
        $this->assertFalse($data['cansubmit']);
        $this->assertStringContainsString('data-puede=""', $output->render_from_template('mod_oksigeniajepyso/player', $data));

        $this->setGuestUser();
        $data = player_page::for_activity($jepyso, $cm)->export_for_template($output);
        $this->assertSame(['', false], [$data['firstname'], $data['cansubmit']]);
    }

    /**
     * The page of the builder has nothing of any activity.
     */
    public function test_builder(): void {
        global $PAGE;
        $this->setAdminUser();
        $data = player_page::for_builder()->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame(['', '', '', '', false], [
            $data['cmid'], $data['code'], $data['sesskey'], $data['backurl'], $data['cansubmit'],
        ]);
        $this->assertSame('Routine builder', $data['title']);

        // The page as it is sent, before anything else has been painted.
        ob_start();
        player_page::for_builder()->show();
        $html = ob_get_clean();
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($html));
        $this->assertStringContainsString('data-cm="" data-rutina="" data-sesskey=""', $html);
    }

    /**
     * The player has no page of its own, sells nothing, calls nobody outside the site and leads nowhere else.
     */
    public function test_player_stays_in_the_site(): void {
        $folder = catalog::player_dir();
        $this->assertFileDoesNotExist($folder . '/index.html');
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (!preg_match('/\\.(js|html|css)$/', $path) || strpos($path, '/libs/three/') !== false) {
                continue;
            }
            $text = file_get_contents($path);
            $pattern = '/licen[cs]i|pricing|precios|dropbox|onedrive|googleapis|accounts\\.google|wa\\.me|t\\.me\\/'
                . '|(jepyso|oksigenia)\\.com\\/|href=\\\\?"https?:/i';
            if (preg_match($pattern, $text, $match)) {
                $found[] = basename($path) . ': ' . $match[0];
            }
        }
        $this->assertSame([], $found);
    }
}
