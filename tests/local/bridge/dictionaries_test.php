<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_repeatable\local\bridge;

/**
 * Tests for the probes of the companion dictionary plugin.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. The tests that need local_dictionaries skip without it, and the ones that
 * describe its absence skip with it.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\bridge\dictionaries
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dictionaries_test extends \advanced_testcase {
    /**
     * The fallback domain pattern equals the companion's, so a drift in either turns this red.
     */
    public function test_domain_pattern_agrees_with_the_companion(): void {
        if (!class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries is not installed.');
        }

        $this->assertSame(\local_dictionaries\resolver::DOMAIN_PATTERN, dictionaries::DOMAIN_PATTERN);
        $this->assertSame(\local_dictionaries\resolver::DOMAIN_PATTERN, dictionaries::domain_pattern());
    }

    /**
     * Without the companion the engine's own pattern is the one in force.
     */
    public function test_domain_pattern_falls_back_to_the_own_constant(): void {
        if (class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries is installed.');
        }

        $this->assertSame(dictionaries::DOMAIN_PATTERN, dictionaries::domain_pattern());
    }

    /**
     * With the companion installed, its domains are listed with their plain names.
     */
    public function test_domains_lists_the_companion_domains(): void {
        if (!method_exists('\local_dictionaries\api', 'list_domains')) {
            $this->markTestSkipped('local_dictionaries with api::list_domains() is not installed.');
        }
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'uf', 'name' => 'States & <b>regions</b>']);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'Sao Paulo']);

        $uf = array_values(array_filter(
            dictionaries::domains(),
            static fn(array $domain): bool => $domain['shortname'] === 'uf'
        ));
        $this->assertCount(1, $uf);
        $this->assertSame('States & regions', $uf[0]['name']);
        $this->assertSame(1, $uf[0]['itemcount']);
        $this->assertTrue(dictionaries::lookup_available());
    }

    /**
     * Without the companion there is no domain list and no lookup.
     */
    public function test_domains_without_the_companion(): void {
        if (class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries is installed.');
        }

        $this->assertNull(dictionaries::domains());
        $this->assertFalse(dictionaries::lookup_available());
    }

    /**
     * Codes fold to a trimmed, accent-free, lower-case form.
     */
    public function test_fold_code(): void {
        $this->assertSame('sao paulo', dictionaries::fold_code('  São Paulo '));
        $this->assertSame('cao', dictionaries::fold_code('ÇÃO'));
        $this->assertSame('pt_br', dictionaries::fold_code('PT_BR'));
    }
}
