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
 * Tests for the payload reader and writer.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\payload
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class payload_test extends \advanced_testcase {
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
     * Build a JSON payload with the given number of sets.
     *
     * @param int $count
     * @return string
     */
    private function build_payload_with_sets(int $count): string {
        $sets = [];
        for ($i = 1; $i <= $count; $i++) {
            $sets['@@ID#' . $i] = ['name' => 'N' . $i, 'role' => 'R' . $i];
        }

        return json_encode($sets);
    }

    /**
     * Ensure normalisation removes empty sets after completing missing sub-items.
     */
    public function test_normalise_discards_empty_sets_after_completion(): void {
        $this->resetAfterTest();

        $payload = payload::normalise(
            '{"@@ID#10":{"diretoria":"","escola":"","turma":"","disciplina":""},' .
            '"@@ID#20":{"diretoria":"16154","escola":"45","turma":"3"}}',
            $this->schema(['diretoria', 'escola', 'turma', 'disciplina'])
        );

        $this->assertSame([
            '@@ID#1' => [
                'diretoria' => '16154',
                'escola' => '45',
                'turma' => '3',
                'disciplina' => '',
            ],
        ], $payload);
    }

    /**
     * Normalisation must defensively cap set count and value length on non-form paths.
     */
    public function test_normalise_enforces_caps(): void {
        $this->resetAfterTest();
        $subitems = $this->schema(['name', 'role']);

        $payload = json_decode($this->build_payload_with_sets(payload::MAX_SETS + 5), true);
        $normalised = payload::normalise($payload, $subitems);
        $this->assertCount(payload::MAX_SETS, $normalised);

        $oversized = str_repeat('b', payload::MAX_VALUE_LENGTH + 50);
        $normalised = payload::normalise(
            ['@@ID#1' => ['name' => $oversized, 'role' => '']],
            $subitems
        );
        $first = reset($normalised);
        $this->assertSame(payload::MAX_VALUE_LENGTH, \core_text::strlen($first['name']));
    }

    /**
     * A payload still keyed by version 1 labels reads like its re-keyed form, through the legacy labels.
     */
    public function test_normalise_reads_label_keyed_sets_while_legacy_labels_exist(): void {
        $this->resetAfterTest();
        $schema = $this->schema([
            ['key' => 'full_name', 'label' => 'Full name'],
            'role',
            ['label' => 'Required x', 'required' => true],
        ]);
        $legacy = $schema->with_legacy_labels(['full_name' => 'Name (old)', 'required_x' => 'Req']);

        // Set 1 has the version 1 shape; set 2 was saved after the conversion, keyed by key.
        $raw = '{"@@ID#1":{"Name (old)":"Alice","role":"Lead","Req":"yes"},'
            . '"@@ID#2":{"full_name":"Bob","role":"","required_x":"no","Name (old)":"ignored"}}';

        $this->assertSame([
            '@@ID#1' => ['full_name' => 'Alice', 'role' => 'Lead', 'required_x' => 'yes'],
            '@@ID#2' => ['full_name' => 'Bob', 'role' => '', 'required_x' => 'no'],
        ], payload::normalise($raw, $legacy));

        // Without the legacy labels, the version 1 set is only "role".
        $this->assertSame(
            ['full_name' => '', 'role' => 'Lead', 'required_x' => ''],
            payload::normalise($raw, $schema)['@@ID#1']
        );
    }

    /**
     * A JSON null under a key counts as absent, so the legacy label is read, as the SQL reader does.
     */
    public function test_null_under_a_key_falls_back_to_the_legacy_label(): void {
        $this->resetAfterTest();
        $schema = $this->schema([['key' => 'name', 'label' => 'Name'], 'role'])->with_legacy_labels(['name' => 'Old name']);

        $this->assertSame(
            ['@@ID#1' => ['name' => 'Alice', 'role' => '']],
            payload::normalise('{"@@ID#1":{"name":null,"Old name":"Alice","role":null}}', $schema)
        );
        $this->assertNull(payload::column_value(['name' => null], 'name', null));
    }

    /**
     * Encoding writes a JSON object without escaped slashes or Unicode, and '{}' for nothing.
     */
    public function test_encode(): void {
        $this->assertSame('{}', payload::encode([]));
        $this->assertSame(
            '{"@@ID#1":{"name":"Língua/Idioma"}}',
            payload::encode(['@@ID#1' => ['name' => 'Língua/Idioma']])
        );
        $this->assertSame('{}', payload::encode(['@@ID#1' => ['name' => "\xB1\x31"]]), 'Invalid UTF-8 cannot be encoded');
    }
}
