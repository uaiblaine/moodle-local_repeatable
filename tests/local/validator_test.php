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
 * Tests for the validator: raw limits, required columns and the adapter's pattern seam.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. The dictionary and pattern rules have their own test classes, which skip
 * without the companions.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\validator
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator_test extends \advanced_testcase {
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
     * A validator for a schema, with the companions counted as absent unless the test says otherwise.
     *
     * @param schema $schema
     * @return validator
     */
    private function validator(schema $schema): validator {
        return new validator($schema, '', static fn(string $domain): bool => false, false, false);
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
     * Strict JSON object requirement: an array, a scalar and broken JSON are refused, a blank value is an empty object.
     */
    public function test_raw_error_refuses_a_payload_that_is_not_a_json_object(): void {
        $validator = $this->validator($this->schema(['name', 'role']));
        $message = get_string('errorinvalidjson', 'local_repeatable');

        $this->assertSame($message, $validator->raw_error('[]'));
        $this->assertSame($message, $validator->raw_error('"text"'));
        $this->assertSame($message, $validator->raw_error('{broken'));
        $this->assertNull($validator->raw_error('{}'));
        $this->assertNull($validator->raw_error('   '), 'A blank value counts as an empty object');
    }

    /**
     * A required field asks for at least one set; a set with an empty optional column passes.
     */
    public function test_error_asks_for_a_set_when_the_field_is_required(): void {
        $schema = $this->schema(['name', 'role']);
        $validator = $this->validator($schema);

        $this->assertSame(get_string('required'), $validator->error([], true));
        $this->assertNull($validator->error([], false));
        $payload = payload::normalise('{"@@ID#1":{"name":"Alice","role":""}}', $schema);
        $this->assertNull($validator->error($payload, true));
    }

    /**
     * More sets than the maximum are refused on the raw payload.
     */
    public function test_raw_error_rejects_too_many_sets(): void {
        $validator = $this->validator($this->schema(['name', 'role']));

        $this->assertNull($validator->raw_error($this->build_payload_with_sets(payload::MAX_SETS)));
        $this->assertSame(
            get_string('errortoomanysets', 'local_repeatable', payload::MAX_SETS),
            $validator->raw_error($this->build_payload_with_sets(payload::MAX_SETS + 1))
        );
    }

    /**
     * A value longer than the maximum is refused on the raw payload; one at the maximum passes.
     */
    public function test_raw_error_rejects_oversized_value(): void {
        $validator = $this->validator($this->schema(['name', 'role']));

        $atmax = str_repeat('a', payload::MAX_VALUE_LENGTH);
        $this->assertNull($validator->raw_error(json_encode(['@@ID#1' => ['name' => $atmax, 'role' => 'X']])));
        $this->assertSame(
            get_string('errorvaluetoolong', 'local_repeatable', payload::MAX_VALUE_LENGTH),
            $validator->raw_error(json_encode(['@@ID#1' => ['name' => $atmax . 'a', 'role' => 'X']]))
        );
    }

    /**
     * The raw caps see a value under its legacy label too, and only while the legacy labels exist.
     */
    public function test_raw_caps_see_a_value_under_its_legacy_label(): void {
        $schema = $this->schema([
            ['key' => 'full_name', 'label' => 'Full name'],
            'role',
            ['label' => 'Required x', 'required' => true],
        ]);
        $legacy = $schema->with_legacy_labels(['full_name' => 'Name (old)', 'required_x' => 'Req']);
        $oversized = json_encode(['@@ID#1' => ['Req' => str_repeat('a', payload::MAX_VALUE_LENGTH + 1)]]);

        $this->assertSame(
            get_string('errorvaluetoolong', 'local_repeatable', payload::MAX_VALUE_LENGTH),
            $this->validator($legacy)->raw_error($oversized)
        );
        // Control: without the legacy labels the label is no column, and the value is not read.
        $this->assertNull($this->validator($schema)->raw_error($oversized));
    }

    /**
     * Every set with a value must fill the required columns; an empty optional field passes.
     */
    public function test_error_required_columns(): void {
        $schema = $this->schema(['name', ['label' => 'Role', 'required' => true]]);
        $validator = $this->validator($schema);
        $validate = fn(string $raw): ?string => $validator->error(payload::normalise($raw, $schema), false);

        $this->assertNull($validate('{}'), 'The field itself is optional');
        $this->assertNull($validate('{"@@ID#1":{"name":"Alice","role":"Lead"},"@@ID#2":{"name":"","role":"Lead"}}'));

        $this->assertSame(
            get_string('errorcolumnrequired', 'local_repeatable', (object)['set' => 2, 'column' => 'Role']),
            $validate('{"@@ID#1":{"name":"Alice","role":"Lead"},"@@ID#2":{"name":"","role":""},'
                . '"@@ID#3":{"name":"Bob","role":"  "}}'),
            'The emptied set is dropped, so Bob is set 2'
        );
    }

    /**
     * The column name in the required error is escaped once.
     */
    public function test_required_error_escapes_the_column_name_once(): void {
        $schema = $this->schema(['name', ['key' => 'role', 'label' => 'R&D', 'required' => true]]);

        $error = $this->validator($schema)->error(payload::normalise('{"@@ID#1":{"name":"A","role":""}}', $schema), false);

        $this->assertSame(
            get_string('errorcolumnrequired', 'local_repeatable', (object)['set' => 1, 'column' => 'R&amp;D']),
            $error
        );
    }

    /**
     * When the adapter says customfield_pattern is absent, pattern columns are plain text: nothing refused, changed or formatted.
     */
    public function test_without_the_plugin_pattern_values_are_kept_as_typed(): void {
        $schema = $this->schema([
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
            ['key' => 'code', 'label' => 'Code', 'mode' => 'pattern', 'pattern' => ['regex' => '[A-Z]{3}']],
        ]);
        $validator = $this->validator($schema);
        $payload = payload::normalise('{"@@ID#1":{"name":"A","cpf":"529.982.247-24","code":"abc"}}', $schema);

        $this->assertNull($validator->error($payload, false));
        $this->assertSame($payload, $validator->for_storage($payload));
        $this->assertSame($payload, $validator->for_editing($payload));
    }

    /**
     * Without dictionary columns the stored codes are none, and the stored payload is read through the schema.
     */
    public function test_stored_codes_are_folded_per_column(): void {
        $schema = $this->schema([
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'note', 'label' => 'Note'],
        ]);
        $validator = new validator(
            $schema,
            '{"@@ID#1":{"lang":" Pá ","note":""},"@@ID#2":{"lang":"","note":"x"}}',
            static fn(string $domain): bool => true,
            true,
            false
        );

        $this->assertSame(['lang' => ['pa' => true], 'note' => ['x' => true]], $validator->stored_codes());
    }
}
