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

use local_repeatable\local\bridge\pattern;

/**
 * Tests for the validator's pattern columns: the plugin's checks, messages and stored shapes.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. Every test needs customfield_pattern and skips without it; the adapter's
 * "plugin absent" seam is tested in {@see validator_test}.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\validator
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator_pattern_test extends \advanced_testcase {
    /** @var string A valid CPF, masked. */
    private const CPF = '529.982.247-25';

    /** @var string The same CPF, digits only. */
    private const CPF_RAW = '52998224725';

    /** @var schema The schema of the test validator. */
    private schema $schema;

    /**
     * Skip without customfield_pattern, then build the schema: a free title, a CPF stored raw, a CPF
     * stored masked and a regex.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $this->resetAfterTest();
        $json = $this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json([
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
            ['key' => 'cpfmasked', 'label' => 'Masked CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf']],
            ['key' => 'code', 'label' => 'Code', 'mode' => 'pattern',
                'pattern' => ['regex' => '[A-Z]{3}', 'regexerror' => 'Three capitals & nothing else']],
        ]);
        $this->schema = schema::from_json($json);
    }

    /**
     * The validator, with customfield_pattern counted as present.
     *
     * @return validator
     */
    private function validator(): validator {
        return new validator($this->schema, '', static fn(string $domain): bool => false, false, true);
    }

    /**
     * Validate some sets.
     *
     * @param array $sets List of sets (key => value).
     * @return string|null The error of validator::error().
     */
    private function error(array $sets): ?string {
        return $this->validator()->error(payload::normalise($this->payload($sets), $this->schema), false);
    }

    /**
     * The JSON payload of some sets.
     *
     * @param array $sets List of sets (key => value).
     * @return string
     */
    private function payload(array $sets): string {
        $payload = [];
        foreach (array_values($sets) as $index => $set) {
            $payload['@@ID#' . ($index + 1)] = $set;
        }
        return json_encode($payload);
    }

    /**
     * A wrong check digit is refused, naming the set and the column.
     */
    public function test_wrong_check_digit_is_refused(): void {
        $this->assertNull($this->error([['cpf' => self::CPF_RAW], ['cpfmasked' => self::CPF, 'code' => 'ABC']]));
        $this->assertSame(get_string('errorpattern', 'local_repeatable', (object)[
            'set' => 2,
            'column' => 'CPF',
            'error' => get_string('errorcpf', 'customfield_pattern'),
        ]), $this->error([['cpf' => self::CPF_RAW], ['cpf' => '529.982.247-24']]));
        $this->assertSame(get_string('errorpattern', 'local_repeatable', (object)[
            'set' => 1,
            'column' => 'Masked CPF',
            'error' => get_string('errormask', 'customfield_pattern', '999.999.999-99'),
        ]), $this->error([['cpfmasked' => '1234']]));
    }

    /**
     * The plugin's message is already escaped: it reaches the form once escaped, never twice.
     */
    public function test_plugin_message_is_escaped_once(): void {
        $error = $this->error([['code' => 'abc']]);

        $this->assertStringContainsString('Three capitals &amp; nothing else', $error);
        $this->assertStringNotContainsString('&amp;amp;', $error);
    }

    /**
     * Values are stored as the column's "Store as" says, and edited with their mask.
     */
    public function test_values_are_stored_raw_or_masked_and_edited_with_their_mask(): void {
        $validator = $this->validator();
        $payload = payload::normalise(
            $this->payload([['name' => 'A', 'cpf' => self::CPF, 'cpfmasked' => ' ' . self::CPF_RAW]]),
            $this->schema
        );

        $stored = $validator->for_storage($payload);
        $this->assertSame(self::CPF_RAW, $stored['@@ID#1']['cpf']);
        $this->assertSame(self::CPF, $stored['@@ID#1']['cpfmasked']);

        // The edit form shows the raw value with its mask; saving it again stores it raw again.
        $edited = $validator->for_editing($stored);
        $this->assertSame(self::CPF, $edited['@@ID#1']['cpf']);
        $this->assertSame($stored, $validator->for_storage($edited));
    }

    /**
     * The stored shape is what the uniqueness check of the adapter compares: canonical JSON of the stored values.
     */
    public function test_for_storage_hands_the_stored_shape_to_the_adapter(): void {
        $payload = payload::normalise($this->payload([['cpf' => self::CPF]]), $this->schema);

        $stored = $this->validator()->for_storage($payload);

        $this->assertSame(self::CPF_RAW, json_decode(payload::encode($stored), true)['@@ID#1']['cpf']);
    }
}
