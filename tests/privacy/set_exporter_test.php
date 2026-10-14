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

namespace local_repeatable\privacy;

/**
 * Tests for the export of one stored payload.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\privacy\set_exporter
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_exporter_test extends \advanced_testcase {
    /**
     * The schema of some columns ({@see \local_repeatable_generator::schema_json()}).
     *
     * @param array $columns
     * @return \local_repeatable\local\schema
     */
    private function schema(array $columns): \local_repeatable\local\schema {
        return \local_repeatable\local\schema::from_json(
            $this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json($columns)
        );
    }

    /**
     * The name of the Nth exported set.
     *
     * @param int $number
     * @return string
     */
    private function set(int $number): string {
        return get_string('repeatableset', 'local_repeatable', $number);
    }

    /**
     * Sets are numbered and keyed by plain column names; sets without a value are left out.
     */
    public function test_export_names_sets_and_columns(): void {
        $this->resetAfterTest();
        $schema = $this->schema([['key' => 'name', 'label' => 'Full <b>name</b>'], 'Role']);

        $this->assertSame([
            $this->set(1) => ['Full name' => 'Alice', 'Role' => 'Manager'],
            $this->set(2) => ['Full name' => '', 'Role' => 'Teacher'],
        ], set_exporter::export(
            $schema,
            '{"@@ID#1":{"name":"Alice","role":"Manager"},"@@ID#2":{"name":" "},"@@ID#3":{"role":"Teacher"},"@@ID#4":"x"}'
        ));
        $this->assertSame([], set_exporter::export($schema, 'not json'));
    }

    /**
     * A payload still keyed by version 1 labels exports the same sets.
     */
    public function test_export_reads_label_keyed_payloads(): void {
        $this->resetAfterTest();
        $schema = $this->schema([['key' => 'name', 'label' => 'Full name'], 'Role'])->with_legacy_labels(['name' => 'Old name']);

        $this->assertSame(
            [$this->set(1) => ['Full name' => 'Alice', 'Role' => 'Lead']],
            set_exporter::export($schema, '{"@@ID#1":{"Old name":"Alice","role":"Lead"}}')
        );
    }

    /**
     * Names that format to the same text stay apart, and values under no current column are exported raw.
     */
    public function test_export_keeps_colliding_names_and_leftover_values(): void {
        $this->resetAfterTest();
        $schema = $this->schema([
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'name_tagged', 'label' => '<b>Name</b>'],
        ]);

        $this->assertSame([$this->set(1) => [
            'Name' => 'one',
            'Name (name_tagged)' => 'two',
            'removed' => 'three',
            'Name (Name)' => 'four',
        ]], set_exporter::export($schema, '{"@@ID#1":{"name":"one","name_tagged":"two","removed":"three","Name":"four"}}'));
    }

    /**
     * Export a pattern column: a CPF stored raw, then text the mask does not fit.
     *
     * @return array The exported sets.
     */
    private function export_pattern_values(): array {
        $schema = $this->schema([
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
        ]);
        return set_exporter::export($schema, '{"@@ID#1":{"cpf":"52998224725"},"@@ID#2":{"cpf":"<b>1</b>"}}');
    }

    /**
     * A pattern value is exported as it is shown, as plain text.
     */
    public function test_export_formats_pattern_values(): void {
        if (!\local_repeatable\local\bridge\pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $this->resetAfterTest();

        $this->assertSame([
            $this->set(1) => ['CPF' => '529.982.247-25'],
            $this->set(2) => ['CPF' => '<b>1</b>'],
        ], $this->export_pattern_values());
    }

    /**
     * Without customfield_pattern a pattern value is exported as stored.
     */
    public function test_export_without_the_plugin_keeps_pattern_values(): void {
        if (\local_repeatable\local\bridge\pattern::available()) {
            $this->markTestSkipped('customfield_pattern is installed; its absence is tested on Moodle 4.5 and 5.0.');
        }
        $this->resetAfterTest();

        $this->assertSame([
            $this->set(1) => ['CPF' => '52998224725'],
            $this->set(2) => ['CPF' => '<b>1</b>'],
        ], $this->export_pattern_values());
    }
}
