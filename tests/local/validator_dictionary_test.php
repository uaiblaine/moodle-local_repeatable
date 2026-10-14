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

use local_repeatable\local\bridge\dictionaries;

/**
 * Tests for the validator's dictionary code checks, including the restricted-dictionary rules.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. Every test needs the companion's lookup API and skips without it. Who may
 * read a dictionary is the adapter's decision: the tests hand the validator a closure, and a
 * domain it refuses is never looked up.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\validator
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validator_dictionary_test extends \advanced_testcase {
    /** @var schema The schema of the validator the test built last. */
    private schema $schema;

    /**
     * Skip without the companion's lookup API, then create the dictionaries.
     *
     * 'langs' (open to logged-in users): pt, es, and xx (inactive). 'uf' (open): SP, RJ.
     * 'cities' (restricted to local/dictionaries:view), whose items have their state in 'uf' as
     * parent: 3550308 (SP), 3304557 (RJ); 0000000 has no parent. The tests run as the admin
     * and decide who may read a domain through the validator's closure.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        if (!dictionaries::lookup_available()) {
            $this->markTestSkipped('local_dictionaries with api::lookup() is not installed.');
        }
        $this->resetAfterTest();

        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'langs', 'visibility' => 1]);
        $generator->create_item(['domain' => 'langs', 'code' => 'pt', 'label' => 'Portuguese']);
        $generator->create_item(['domain' => 'langs', 'code' => 'es', 'label' => 'Spanish']);
        $generator->create_item(['domain' => 'langs', 'code' => 'xx', 'label' => 'Retired', 'active' => 0]);
        $generator->create_domain(['shortname' => 'uf', 'visibility' => 1]);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'São Paulo']);
        $generator->create_item(['domain' => 'uf', 'code' => 'RJ', 'label' => 'Rio de Janeiro']);
        $generator->create_domain(['shortname' => 'cities', 'visibility' => 0]);
        $generator->create_item(['domain' => 'cities', 'code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'SP',
            'parentdomain' => 'uf']);
        $generator->create_item(['domain' => 'cities', 'code' => '3304557', 'label' => 'Rio', 'parentcode' => 'RJ',
            'parentdomain' => 'uf']);
        $generator->create_item(['domain' => 'cities', 'code' => '0000000', 'label' => 'Nowhere']);
        $this->setAdminUser();
    }

    /**
     * The columns of the test schema: strict language, open level, strict state, city depending on
     * the state, and an open column on the restricted cities.
     *
     * @return array
     */
    private function columns(): array {
        return [
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'level', 'label' => 'Level', 'mode' => 'dictionary_open', 'domain' => 'langs'],
            ['key' => 'state', 'label' => 'State', 'mode' => 'dictionary', 'domain' => 'uf'],
            ['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'parent' => 'state'],
            ['key' => 'othercity', 'label' => 'Other city', 'mode' => 'dictionary_open', 'domain' => 'cities'],
        ];
    }

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
     * A validator for the test schema (or another), with the lookup API present.
     *
     * @param string $stored The owner's stored payload (raw JSON).
     * @param string[] $unreadable Domains the editing user may not read.
     * @param array|null $columns Columns of another schema.
     * @param bool $lookup Whether the companion offers what code checks need.
     * @return validator
     */
    private function validator(
        string $stored = '',
        array $unreadable = [],
        ?array $columns = null,
        bool $lookup = true
    ): validator {
        $this->schema = $this->schema($columns ?? $this->columns());
        return new validator(
            $this->schema,
            $stored,
            static fn(string $domain): bool => !in_array($domain, $unreadable, true),
            $lookup,
            false
        );
    }

    /**
     * Validate some sets with the validator built last.
     *
     * @param validator $validator
     * @param array $sets List of sets (key => code).
     * @return string|null The error of validator::error().
     */
    private function error(validator $validator, array $sets): ?string {
        $payload = [];
        foreach (array_values($sets) as $index => $set) {
            $payload['@@ID#' . ($index + 1)] = $set;
        }
        return $validator->error(payload::normalise(json_encode($payload), $this->schema), false);
    }

    /**
     * The expected error of one code.
     *
     * @param string $identifier
     * @param int $set
     * @param string $column
     * @param string $code
     * @param array $extra More placeholders (parent, parentcode).
     * @return string
     */
    private function code_error(string $identifier, int $set, string $column, string $code, array $extra = []): string {
        return get_string($identifier, 'local_repeatable', (object)([
            'set' => $set,
            'column' => $column,
            'code' => $code,
        ] + $extra));
    }

    /**
     * Store a payload for an owner, as their saved value.
     *
     * @param array $set One set: key => code.
     * @return string The raw stored JSON.
     */
    private function stored(array $set): string {
        return json_encode(['@@ID#1' => $set]);
    }

    /**
     * A codes-only column refuses an unknown code and accepts a known one in any case.
     */
    public function test_strict_column_refuses_an_unknown_code(): void {
        $validator = $this->validator();

        $this->assertNull($this->error($validator, [['lang' => 'PT'], ['lang' => 'es']]), 'Codes fold case');
        $this->assertSame(
            $this->code_error('errorcodeunknown', 2, 'Language', 'zz'),
            $this->error($validator, [['lang' => 'pt'], ['lang' => 'zz']])
        );
    }

    /**
     * A codes-only column refuses a deactivated code the owner does not have.
     */
    public function test_strict_column_refuses_an_inactive_code(): void {
        $this->assertSame(
            $this->code_error('errorcodeinactive', 1, 'Language', 'xx'),
            $this->error($this->validator(), [['lang' => 'xx']])
        );
    }

    /**
     * A deactivated code the owner already has stored under that column stays accepted; only there.
     */
    public function test_stored_inactive_code_is_kept(): void {
        $validator = $this->validator('{"@@ID#1":{"lang":"XX","level":"","state":"","city":""}}');

        $this->assertNull($this->error($validator, [['lang' => 'xx'], ['lang' => 'pt']]));
        // Control: the stored code does not open the column to other inactive or unknown codes.
        $this->assertSame(
            $this->code_error('errorcodeunknown', 2, 'Language', 'zz'),
            $this->error($validator, [['lang' => 'xx'], ['lang' => 'zz']])
        );
        // Control: another owner holding nothing is refused the same code.
        $this->assertSame(
            $this->code_error('errorcodeinactive', 1, 'Language', 'xx'),
            $this->error($this->validator(), [['lang' => 'xx']])
        );
    }

    /**
     * A stored code is exempt under its own column only, not under another column of the same dictionary.
     */
    public function test_stored_code_is_exempt_under_its_own_column_only(): void {
        $validator = $this->validator($this->stored(['lang' => 'xx']));

        $this->assertNull($this->error($validator, [['lang' => 'xx']]));
        $this->assertSame(
            $this->code_error('errorcodeunknown', 1, 'State', 'xx'),
            $this->error($validator, [['state' => 'xx']]),
            'xx is stored under Language, not under State'
        );
    }

    /**
     * An any-code column accepts a code no dictionary item has.
     */
    public function test_open_column_accepts_a_new_code(): void {
        $this->assertNull($this->error($this->validator(), [['level' => 'Z9 new'], ['level' => 'xx']]));
    }

    /**
     * A dependent column refuses an item of another parent code or parent domain, or one whose
     * parent is blank, and accepts a matching one or an item without a parent.
     */
    public function test_dependent_column_checks_the_parent_code(): void {
        $validator = $this->validator();

        $this->assertNull($this->error($validator, [['state' => 'sp', 'city' => '3550308']]), 'Parent codes fold case');
        $this->assertNull($this->error($validator, [['city' => '0000000']]), 'An item without a parent needs none');
        $this->assertSame(
            $this->code_error('errorcodeparentmissing', 1, 'City', '3304557', ['parent' => 'State']),
            $this->error($validator, [['city' => '3304557']])
        );
        // The parent code matches, but it is the SP of another dictionary.
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'regions', 'visibility' => 1]);
        $generator->create_item(['domain' => 'regions', 'code' => 'SP', 'label' => 'South Pole']);
        $generator->create_item(['domain' => 'cities', 'code' => 'ice', 'label' => 'Ice city', 'parentcode' => 'SP',
            'parentdomain' => 'regions']);
        $this->assertSame(
            $this->code_error('errorcodeparent', 1, 'City', 'ice', ['parent' => 'State', 'parentcode' => 'SP']),
            $this->error($validator, [['state' => 'SP', 'city' => 'ice']])
        );
        $this->assertSame(
            $this->code_error('errorcodeparent', 1, 'City', '3304557', ['parent' => 'State', 'parentcode' => 'SP']),
            $this->error($validator, [['state' => 'SP', 'city' => '3304557']])
        );
    }

    /**
     * The submitted code is escaped in the message.
     */
    public function test_error_escapes_the_code(): void {
        $error = $this->error($this->validator(), [['lang' => '<b>x</b>']]);

        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $error);
        $this->assertStringNotContainsString('<b>', $error);
    }

    /**
     * Without the companion the codes are text: nothing is checked.
     */
    public function test_without_the_companion_codes_are_text(): void {
        $validator = $this->validator('', ['langs', 'uf', 'cities'], null, false);

        $this->assertNull($this->error($validator, [['lang' => 'zz', 'state' => 'SP', 'city' => '3304557']]));
    }

    /**
     * A user who may not read a dictionary cannot tell its codes apart: every code not stored gets one message.
     */
    public function test_restricted_domain_refuses_every_unstored_code_alike(): void {
        $stored = $this->stored(['city' => '3550308']);
        $validator = $this->validator($stored, ['cities']);

        $expected = $this->code_error('errorcoderestricted', 1, 'City', '');
        $this->assertSame($expected, $this->error($validator, [['state' => 'RJ', 'city' => '3304557']]), 'An existing code');
        $this->assertSame($expected, $this->error($validator, [['state' => 'RJ', 'city' => 'nope']]), 'An unknown code');
        $this->assertSame($expected, $this->error($validator, [['state' => 'RJ', 'city' => '0000000']]), 'A code with no parent');
        // The stored code stays, and no parent check reaches the user: RJ is not its state.
        $this->assertNull($this->error($validator, [['state' => 'RJ', 'city' => '3550308']]));
        // An open column on the restricted dictionary takes any code as text.
        $this->assertNull($this->error($validator, [['othercity' => 'anything']]));

        // Control: a user who may read the dictionary gets its answers.
        $this->assertSame(
            $this->code_error('errorcodeunknown', 1, 'City', 'nope'),
            $this->error($this->validator($stored), [['state' => 'RJ', 'city' => 'nope']])
        );
    }

    /**
     * A dependent column is not checked against its parent when the parent column's dictionary cannot be read.
     */
    public function test_parent_check_needs_the_parent_domain_readable(): void {
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'secret', 'visibility' => 0]);
        $generator->create_item(['domain' => 'secret', 'code' => 'Z1', 'label' => 'Zone one']);
        $generator->create_item(['domain' => 'langs', 'code' => 'tw', 'label' => 'Town', 'parentcode' => 'Z1',
            'parentdomain' => 'secret']);
        $columns = [
            ['key' => 'zone', 'label' => 'Zone', 'mode' => 'dictionary', 'domain' => 'secret'],
            ['key' => 'town', 'label' => 'Town', 'mode' => 'dictionary_open', 'domain' => 'langs', 'parent' => 'zone'],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
        ];

        // A blank parent would tell that the item has one: nothing is said when the parent is unreadable.
        $this->assertNull($this->error($this->validator('', ['secret'], $columns), [['town' => 'tw']]));
        // Control: when the parent dictionary is readable the blank parent is refused.
        $this->assertSame(
            $this->code_error('errorcodeparentmissing', 1, 'Town', 'tw', ['parent' => 'Zone']),
            $this->error($this->validator('', [], $columns), [['town' => 'tw']])
        );
    }

    /**
     * A stored code removed from the dictionary since stays accepted for its owner, and only for them.
     */
    public function test_stored_removed_code_is_kept(): void {
        global $DB;

        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_item(['domain' => 'langs', 'code' => 'gone', 'label' => 'Gone']);
        $DB->delete_records('local_dictionaries_item', ['codekey' => 'gone']);

        $this->assertNull($this->error($this->validator($this->stored(['lang' => 'gone'])), [['lang' => 'gone']]));
        $this->assertSame(
            $this->code_error('errorcodeunknown', 1, 'Language', 'gone'),
            $this->error($this->validator(), [['lang' => 'gone']])
        );
    }
}
