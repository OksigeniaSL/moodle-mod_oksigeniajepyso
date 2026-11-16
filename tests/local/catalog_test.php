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
 * Tests of the catalogue in the user's language: Spanish for the languages of Spain and of Paraguay, English for the
 * rest, as the player decides; an exercise without its English shows in Spanish.
 *
 * @package    mod_oksigeniajepyso
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_oksigeniajepyso\local\catalog
 */
#[\PHPUnit\Framework\Attributes\CoversClass(catalog::class)]
final class catalog_test extends \advanced_testcase {
    /**
     * Forgets the catalogue of the tests.
     */
    protected function tearDown(): void {
        global $SESSION;
        unset($SESSION->lang);
        catalog::use_file(null);
        parent::tearDown();
    }

    /**
     * The name, position and material follow the user's language.
     */
    public function test_names_in_the_users_language(): void {
        global $SESSION;
        $this->resetAfterTest();

        $SESSION->lang = 'en';
        catalog::use_file(__DIR__ . '/../fixtures/catalogo-en.json');
        $silla = catalog::get('silla');
        $this->assertSame('Sit to stand', $silla['name']);
        $this->assertSame('Sitting', $silla['position']);
        $this->assertSame('Sturdy chair', $silla['material']);
        $this->assertSame('Puente de glúteo', catalog::get('puente')['name']);

        $SESSION->lang = 'es_mx';
        catalog::use_file(__DIR__ . '/../fixtures/catalogo-en.json');
        $silla = catalog::get('silla');
        $this->assertSame('Levantarse de la silla', $silla['name']);
        $this->assertSame('Sentado', $silla['position']);

        $SESSION->lang = 'fr';
        catalog::use_file(__DIR__ . '/../fixtures/catalogo-en.json');
        $this->assertSame('Sit to stand', catalog::get('silla')['name']);
    }
}
