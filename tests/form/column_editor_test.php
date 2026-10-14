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

namespace local_repeatable\form;

use local_repeatable\local\bridge\dictionaries;
use local_repeatable\local\bridge\pattern;
use local_repeatable\local\schema;

/**
 * Tests for the column editor: the textarea's data attributes, the submitted schema's shapes and its validation.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. What the adapter reads from the STORED field (the keys holding values, their
 * labels, the stored pattern objects) and whether customfield_pattern counts as present are passed
 * in, as an adapter does. Tests that need a companion skip without it.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\form\column_editor
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class column_editor_test extends \advanced_testcase {
    /**
     * The schema JSON of some columns ({@see \local_repeatable_generator::schema_json()}).
     *
     * @param array $columns
     * @return string
     */
    private function schema_json(array $columns): string {
        return $this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json($columns);
    }

    /**
     * The errors of a submitted schema, as the definition form asks for them.
     *
     * @param string $json The submitted text.
     * @param array $storedpatterns Key => pattern object of the stored field's pattern columns.
     * @param bool|null $patternavailable Whether customfield_pattern counts as present; null asks the bridge.
     * @return string[]
     */
    private function errors(string $json, array $storedpatterns = [], ?bool $patternavailable = null): array {
        return column_editor::errors(schema::decode($json), [], $storedpatterns, $patternavailable ?? pattern::available());
    }

    /**
     * Every broken rule is reported, in order.
     */
    public function test_errors_list_every_broken_rule(): void {
        $this->resetAfterTest();

        $errors = $this->errors(json_encode(['version' => 2, 'columns' => [
            ['key' => 'name', 'label' => 'Name', 'mode' => 'free'],
            ['key' => 'Bad', 'label' => 'Role', 'mode' => 'select'],
        ]]));

        $this->assertSame([
            get_string('errorkeyinvalid', 'local_repeatable', 'Bad'),
            get_string('errormodeinvalid', 'local_repeatable', 'Role'),
            get_string('errortitlecount', 'local_repeatable'),
        ], $errors);
    }

    /**
     * Version 1 text and an empty value are not a schema.
     */
    public function test_errors_refuse_text_that_is_not_a_schema(): void {
        $this->resetAfterTest();
        $expected = [get_string('errorschemainvalid', 'local_repeatable')];

        $this->assertSame($expected, $this->errors("name\nrole"));
        $this->assertSame($expected, $this->errors(''));
    }

    /**
     * A valid schema passes.
     */
    public function test_errors_accept_a_valid_schema(): void {
        $this->resetAfterTest();

        $this->assertSame([], $this->errors($this->schema_json(['Name', ['label' => 'Role', 'required' => true]])));
    }

    /**
     * With the companion installed, a dictionary column must name an existing domain.
     */
    public function test_errors_check_domains_with_the_companion(): void {
        if (!class_exists('\local_dictionaries\resolver')) {
            $this->markTestSkipped('local_dictionaries is not installed.');
        }
        $this->resetAfterTest();
        $this->getDataGenerator()->get_plugin_generator('local_dictionaries')
            ->create_domain(['shortname' => 'diretorias', 'name' => 'Diretorias']);
        $schema = fn(string $domain): string => $this->schema_json([
            ['label' => 'Diretoria', 'mode' => 'dictionary', 'domain' => $domain],
        ]);

        $this->assertSame([], $this->errors($schema('diretorias')));
        $this->assertSame([get_string('errordomainmissing', 'local_repeatable', 'escolas')], $this->errors($schema('escolas')));
    }

    /**
     * A submitted schema is saved as its canonical JSON.
     */
    public function test_canonical_rewrites_the_schema(): void {
        $canonical = column_editor::canonical(
            "{\n  \"version\": 2,\n  \"columns\": [{\"label\": \" Name \", \"key\": \"name\", \"title\": 1,"
                . " \"mode\": \"free\", \"domain\": \"x\"}]\n}"
        );

        $this->assertSame('{"version":2,"columns":[{"key":"name","label":"Name","mode":"free","title":true}]}', $canonical);
        $this->assertNull(column_editor::canonical('not json'));
    }

    /**
     * The editor learns which keys hold values and how many users have data, and the module starts.
     *
     * The adapter reads both from the stored field; the submitted schema renames a key, and the
     * lock still follows what the adapter passed.
     */
    public function test_attributes_lock_the_keys_holding_values(): void {
        global $PAGE;
        $this->resetAfterTest();

        $attributes = column_editor::attributes(['users' => 2, 'keys' => ['name', 'role']], false);
        $pretty = column_editor::pretty($this->schema_json([['key' => 'renamed', 'label' => 'Name']]));
        column_editor::init_js();

        $this->assertSame('["name","role"]', $attributes['data-lockedkeys']);
        $this->assertEquals(2, $attributes['data-users']);
        $this->assertStringContainsString("\n", $pretty, 'Pretty-printed for editing without JavaScript');
        $this->assertSame(['renamed'], schema::from_json($pretty)->keys());
        $amdjscode = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $this->assertStringContainsString('local_repeatable/columneditor', implode("\n", $amdjscode));
        $this->assertSame('local-repeatable-schema', column_editor::element_attributes()['data-region']);
    }

    /**
     * A new field has nothing locked, and text the editor cannot read is left as typed.
     */
    public function test_attributes_for_a_new_field(): void {
        $this->resetAfterTest();

        $attributes = column_editor::attributes(['users' => 0, 'keys' => []], false);

        $this->assertSame('[]', $attributes['data-lockedkeys']);
        $this->assertEquals(0, $attributes['data-users']);
        $this->assertNull(column_editor::pretty('not json'));
        $companion = class_exists('\local_dictionaries\resolver') ? '1' : '0';
        $this->assertSame($companion, (string)$attributes['data-companion']);
    }

    /**
     * With the companion installed, the editor receives its domains with their plain names.
     */
    public function test_attributes_list_the_companion_domains(): void {
        if (!method_exists('\local_dictionaries\api', 'list_domains')) {
            $this->markTestSkipped('local_dictionaries with api::list_domains() is not installed.');
        }
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'uf', 'name' => 'States & <b>regions</b>']);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'Sao Paulo']);

        $attributes = column_editor::attributes(['users' => 0, 'keys' => []], false);

        $domains = json_decode($attributes['data-domains'], true);
        $uf = array_values(array_filter($domains, static fn(array $domain): bool => $domain['shortname'] === 'uf'));
        $this->assertCount(1, $uf);
        $this->assertSame('States & regions', $uf[0]['name']);
        $this->assertSame(1, $uf[0]['itemcount']);
        $this->assertSame('1', (string)$attributes['data-companion']);
    }

    /**
     * The editor refuses a parent column on another dictionary than the one holding the parents.
     */
    public function test_errors_refuse_a_parent_on_the_wrong_dictionary(): void {
        if (!dictionaries::lookup_available()) {
            $this->markTestSkipped('local_dictionaries with api::lookup() is not installed.');
        }
        $this->resetAfterTest();
        /** @var \local_dictionaries_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'langs', 'visibility' => 1]);
        $generator->create_item(['domain' => 'langs', 'code' => 'pt', 'label' => 'Portuguese']);
        $generator->create_domain(['shortname' => 'uf', 'visibility' => 1]);
        $generator->create_item(['domain' => 'uf', 'code' => 'SP', 'label' => 'São Paulo']);
        $generator->create_domain(['shortname' => 'cities', 'visibility' => 0]);
        $generator->create_item(['domain' => 'cities', 'code' => '3550308', 'label' => 'São Paulo', 'parentcode' => 'SP',
            'parentdomain' => 'uf']);
        $schema = fn(string $cityparent): string => $this->schema_json([
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'level', 'label' => 'Level', 'mode' => 'dictionary_open', 'domain' => 'langs'],
            ['key' => 'state', 'label' => 'State', 'mode' => 'dictionary', 'domain' => 'uf'],
            ['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'parent' => $cityparent],
            ['key' => 'othercity', 'label' => 'Other city', 'mode' => 'dictionary_open', 'domain' => 'cities'],
        ]);

        $this->assertSame([], $this->errors($schema('state')));
        $this->assertSame([get_string('errorparentmismatch', 'local_repeatable', (object)[
            'column' => 'City',
            'domain' => 'cities',
            'parentdomain' => 'uf',
        ])], $this->errors($schema('lang')));
    }

    /**
     * The columns of the pattern tests: a free title, a CPF stored raw, a CPF stored masked, a regex.
     *
     * @return array
     */
    private function pattern_columns(): array {
        return [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
            ['key' => 'cpfmasked', 'label' => 'Masked CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf']],
            ['key' => 'code', 'label' => 'Code', 'mode' => 'pattern',
                'pattern' => ['regex' => '[A-Z]{3}', 'regexerror' => 'Three capitals & nothing else']],
        ];
    }

    /**
     * With the plugin the editor checks each pattern column's configuration.
     */
    public function test_errors_check_the_pattern_configuration_with_the_plugin(): void {
        if (!pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $this->resetAfterTest();
        $schema = fn(array $pattern): string => $this->schema_json(['Name', ['label' => 'Doc', 'mode' => 'pattern',
            'pattern' => $pattern]]);

        $this->assertSame([], $this->errors($schema(['preset' => 'cpf'])));
        $this->assertSame([get_string('errorpatternconfig', 'local_repeatable', (object)[
            'column' => 'Doc',
            'error' => get_string('errorconfigmaskrequired', 'customfield_pattern'),
        ])], $this->errors($schema(['preset' => 'custom'])));
    }

    /**
     * Without the plugin the editor keeps the stored pattern columns and refuses new ones.
     */
    public function test_errors_without_the_plugin(): void {
        $this->resetAfterTest();
        $stored = schema::from_json($this->schema_json($this->pattern_columns()))->pattern_objects();
        $errors = fn(array $columns, array $storedpatterns): array => $this->errors(
            $this->schema_json($columns),
            $storedpatterns,
            false
        );
        $columns = $this->pattern_columns();

        $this->assertSame([], $errors($columns, $stored), 'The stored schema, saved again');

        $columns[] = ['key' => 'cep', 'label' => 'CEP', 'mode' => 'pattern', 'pattern' => ['preset' => 'cep']];
        $this->assertSame(
            [get_string('errorpatternunavailable', 'local_repeatable', 'CEP')],
            $errors($columns, $stored),
            'A new pattern column'
        );
        $this->assertSame(
            array_map(
                static fn(string $name): string => get_string('errorpatternunavailable', 'local_repeatable', $name),
                ['CPF', 'Masked CPF', 'Code', 'CEP']
            ),
            $errors($columns, []),
            'A new field has no stored column'
        );

        $changed = $this->pattern_columns();
        $changed[1]['pattern']['storeformat'] = 'masked';
        $this->assertSame(
            [get_string('errorpatternunavailable', 'local_repeatable', 'CPF')],
            $errors($changed, $stored),
            'A changed configuration'
        );
    }

    /**
     * The editor is told whether pattern columns can be configured, and the presets.
     */
    public function test_attributes_tell_the_editor_about_patterns(): void {
        $this->resetAfterTest();
        $available = pattern::available();

        $attributes = column_editor::attributes(['users' => 0, 'keys' => []], $available);

        $this->assertSame($available ? '1' : '0', (string)$attributes['data-pattern']);
        $this->assertSame($available ? pattern::presets() : [], json_decode($attributes['data-presets'], true));
        // The stored configuration reaches the editor as it is.
        $this->assertSame(
            ['preset' => 'cpf', 'storeformat' => 'raw'],
            json_decode(column_editor::pretty($this->schema_json($this->pattern_columns())), true)['columns'][1]['pattern']
        );
    }
}
