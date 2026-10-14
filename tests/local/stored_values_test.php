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

namespace local_repeatable\local;

/**
 * Tests for the keys in use in a field's stored payloads.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\stored_values
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stored_values_test extends \advanced_testcase {
    /**
     * The schema of some columns ({@see \local_repeatable_generator::schema_json()}).
     *
     * @param array $columns
     * @return schema
     */
    private function schema(array $columns): schema {
        return schema::from_json($this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json($columns));
    }

    /**
     * Only keys with a non-blank value lock, in schema order; owners without a set are not counted.
     */
    public function test_keys_in_use_counts_only_non_blank_values(): void {
        $this->resetAfterTest();
        $schema = $this->schema(['Name', 'Role', 'Notes']);

        $usage = stored_values::keys_in_use([
            '{"@@ID#1":{"name":"Ann","role":" ","notes":null}}',
            '{}',
            '',
            '{"@@ID#1":{"role":"","notes":""}}',
        ], $schema);
        $this->assertSame(['users' => 1, 'keys' => ['name']], $usage);

        // Control: a non-blank value in a later payload locks its key too, in schema order.
        $usage = stored_values::keys_in_use([
            '{"@@ID#1":{"notes":"x"}}',
            '{"@@ID#1":{"name":"Ann","role":" "}}',
        ], $schema);
        $this->assertSame(['users' => 2, 'keys' => ['name', 'notes']], $usage);
    }

    /**
     * A value stored under a legacy label locks its key, as the readers see it there.
     */
    public function test_keys_in_use_reads_legacy_labels(): void {
        $this->resetAfterTest();
        $schema = $this->schema([['key' => 'lingua', 'label' => 'Língua'], 'Level'])->with_legacy_labels(['lingua' => 'Língua']);

        $usage = stored_values::keys_in_use((function () {
            yield '{"@@ID#1":{"Língua":"pt"}}';
        })(), $schema);

        $this->assertSame(['users' => 1, 'keys' => ['lingua']], $usage);
    }
}
