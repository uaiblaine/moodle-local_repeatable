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
use local_repeatable\local\validator;

/**
 * Tests for the edit widget: its HTML, the columns its AMD module reads, the labels it preloads.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. Who may read a dictionary and whether the companions count as present are
 * the adapter's decisions: the tests hand the widget a closure and the two flags. Tests that need
 * local_dictionaries' lookup API or customfield_pattern skip without it.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\form\widget
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class widget_test extends \advanced_testcase {
    /** @var array The ids an adapter hands the widget. */
    private const IDS = [
        'wrapperid' => 'local-repeatable-test',
        'hiddenid' => 'local-repeatable-test-hidden',
        'errorid' => 'fgroup_id_error_test',
    ];

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
     * A widget for some columns.
     *
     * @param array $columns
     * @param callable|null $canread fn(string $domain): bool; null reads every domain.
     * @param bool $lookup Whether the companion offers what dictionary columns need.
     * @param bool $pattern Whether customfield_pattern counts as present.
     * @return widget
     */
    private function widget(array $columns, ?callable $canread = null, bool $lookup = true, bool $pattern = true): widget {
        return new widget($this->schema($columns), $canread ?? static fn(string $domain): bool => true, $lookup, $pattern);
    }

    /**
     * The columns the widget hands its AMD module.
     *
     * @param string $html
     * @return array
     */
    private function widget_columns(string $html): array {
        $this->assertSame(1, preg_match('~data-columns="([^"]*)"~', $html, $matches));
        return json_decode(html_entity_decode($matches[1]), true);
    }

    /**
     * The AMD calls the page has collected.
     *
     * @return string
     */
    private function amd_code(): string {
        global $PAGE;
        return implode("\n", (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires));
    }

    /**
     * The columns of the dictionary tests: strict language, open level, strict state, city depending
     * on the state, and an open column on the restricted cities.
     *
     * @return array
     */
    private function dictionary_columns(): array {
        return [
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'level', 'label' => 'Level', 'mode' => 'dictionary_open', 'domain' => 'langs'],
            ['key' => 'state', 'label' => 'State', 'mode' => 'dictionary', 'domain' => 'uf'],
            ['key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'domain' => 'cities', 'parent' => 'state'],
            ['key' => 'othercity', 'label' => 'Other city', 'mode' => 'dictionary_open', 'domain' => 'cities'],
        ];
    }

    /**
     * Skip without the companion's lookup API, then create the dictionaries.
     *
     * 'langs': pt, es, and xx (inactive). 'uf': SP, RJ. 'cities', whose items have their state in
     * 'uf' as parent: 3550308 (SP), 3304557 (RJ); 0000000 has no parent.
     *
     * @return void
     */
    private function create_dictionaries(): void {
        if (!dictionaries::lookup_available()) {
            $this->markTestSkipped('local_dictionaries with api::lookup() is not installed.');
        }
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
    }

    /**
     * The widget escapes the field and column names once: the template does it.
     */
    public function test_render_escapes_names_once(): void {
        $this->resetAfterTest();

        $html = $this->widget(['Item R&D <b>x</b>', 'role'])->render('Field R&D <b>x</b>', self::IDS);

        foreach (['Field', 'Item'] as $label) {
            $this->assertSame(1, substr_count($html, $label . ' R&amp;D'), $label . ' must appear escaped once');
        }
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('<b>', $html);
    }

    /**
     * The set and action headers print their sentence-case strings and read in capitals through the
     * caps class, which the column-name headers never carry: those keep the administrator's case.
     */
    public function test_only_the_widget_headers_are_set_in_capitals(): void {
        $this->resetAfterTest();

        $html = $this->widget(['Notes', 'Role'])->render('Repeatable test', self::IDS);

        $this->assertSame(4, preg_match_all('~<th\b[^>]*>.*?</th>~s', $html, $cells));
        $headers = [];
        foreach ($cells[0] as $cell) {
            $this->assertSame(1, preg_match('~^<th\b([^>]*)>(.*?)</th>$~s', $cell, $parts));
            $headers[] = [strip_tags($parts[2]), str_contains($parts[1], 'local-repeatable-head-caps')];
        }
        $this->assertSame([
            [get_string('setcolumn', 'local_repeatable'), true],
            ['Notes', false],
            ['Role', false],
            [get_string('actioncolumn', 'local_repeatable'), true],
        ], $headers);
        $this->assertSame(['Set', 'Action'], [get_string('setcolumn', 'local_repeatable'),
            get_string('actioncolumn', 'local_repeatable')]);
    }

    /**
     * The widget gives its module each column's key, plain label, required flag and dictionary
     * properties, and marks required headers.
     */
    public function test_render_hands_the_columns_to_the_module(): void {
        $this->resetAfterTest();

        $html = $this->widget([
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'iso639', 'required' => true],
            'Notes',
        ])->render('Repeatable test', self::IDS);

        $this->assertSame([
            [
                'key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'required' => true,
                'domain' => 'iso639', 'open' => false, 'autocomplete' => true,
            ],
            ['key' => 'notes', 'label' => 'Notes', 'mode' => 'free', 'required' => false],
        ], $this->widget_columns($html));
        $this->assertSame(1, substr_count($html, 'class="local-repeatable-required"'));
        $this->assertStringContainsString('aria-describedby="' . self::IDS['errorid'] . '"', $html);
    }

    /**
     * The module gets each dictionary column's domain, mode, parent, and whether it is searched.
     *
     * A domain the editing user may not read is typed as a code, because the search service
     * would refuse them.
     */
    public function test_columns_follow_the_domains_the_user_may_read(): void {
        $this->resetAfterTest();

        $plain = $this->widget_columns($this->widget(
            $this->dictionary_columns(),
            static fn(string $domain): bool => $domain !== 'cities'
        )->render('Field', self::IDS));
        $this->assertSame([
            'key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'required' => false,
            'domain' => 'langs', 'open' => false, 'autocomplete' => true,
        ], $plain[0]);
        $this->assertTrue($plain[1]['open']);
        $this->assertSame([
            'key' => 'city', 'label' => 'City', 'mode' => 'dictionary', 'required' => false,
            'domain' => 'cities', 'open' => false, 'autocomplete' => false,
            'parent' => 'state', 'parentdomain' => 'uf', 'parentlabel' => 'State',
        ], $plain[3]);

        $all = $this->widget_columns($this->widget($this->dictionary_columns())->render('Field', self::IDS));
        $this->assertTrue($all[3]['autocomplete'], 'A user who may read every domain searches the restricted one');
    }

    /**
     * A dependent column is not searched when its parent column's dictionary cannot be read.
     */
    public function test_a_dependent_column_needs_the_parent_domain_too(): void {
        $this->resetAfterTest();

        $columns = $this->widget_columns($this->widget([
            ['key' => 'zone', 'label' => 'Zone', 'mode' => 'dictionary', 'domain' => 'secret'],
            ['key' => 'town', 'label' => 'Town', 'mode' => 'dictionary_open', 'domain' => 'langs', 'parent' => 'zone'],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
        ], static fn(string $domain): bool => $domain !== 'secret')->render('Field', self::IDS));

        $this->assertSame([false, false, true], array_column($columns, 'autocomplete'));
    }

    /**
     * Without the companion the codes are text: no column is searched and no label is preloaded.
     */
    public function test_without_the_companion_codes_are_text(): void {
        $this->resetAfterTest();
        $widget = $this->widget($this->dictionary_columns(), static fn(string $domain): bool => false, false);

        $columns = $this->widget_columns($widget->render('Field', self::IDS));
        $this->assertSame([false, false, false, false, false], array_column($columns, 'autocomplete'));
        $this->assertSame([], $widget->labels('{"@@ID#1":{"lang":"pt","level":"","state":"","city":""}}', []));
    }

    /**
     * The labels of the codes a payload holds, so the widget shows them without a request.
     */
    public function test_labels_of_the_codes_a_payload_holds(): void {
        $this->resetAfterTest();
        $this->create_dictionaries();

        $labels = $this->widget($this->dictionary_columns())
            ->labels('{"@@ID#1":{"lang":"pt","level":"new1","state":"SP","city":"3550308"}}', []);

        $this->assertSame([
            'langs' => ['pt' => 'Portuguese'],
            'uf' => ['SP' => 'São Paulo'],
            'cities' => ['3550308' => 'São Paulo'],
        ], $labels);
    }

    /**
     * A user who may not read a dictionary gets no label for a code they submit, only for the ones stored.
     */
    public function test_restricted_domain_labels_only_stored_codes(): void {
        $this->resetAfterTest();
        $this->create_dictionaries();
        $schema = $this->schema($this->dictionary_columns());
        $canread = static fn(string $domain): bool => $domain !== 'cities';
        $stored = (new validator($schema, json_encode(['@@ID#1' => ['city' => '3550308']]), $canread, true, true))
            ->stored_codes();
        $submitted = json_encode([
            '@@ID#1' => ['state' => 'SP', 'city' => '3550308'],
            '@@ID#2' => ['state' => 'RJ', 'city' => '3304557', 'othercity' => '0000000'],
        ]);

        $this->assertSame([
            'uf' => ['SP' => 'São Paulo', 'RJ' => 'Rio de Janeiro'],
            'cities' => ['3550308' => 'São Paulo'],
        ], (new widget($schema, $canread, true, true))->labels($submitted, $stored));

        // Control: a user who may read the dictionary gets every label.
        $labels = (new widget($schema, static fn(string $domain): bool => true, true, true))
            ->labels(json_encode(['@@ID#1' => ['state' => 'RJ', 'city' => '3304557']]), $stored);
        $this->assertSame('Rio', $labels['cities']['3304557']);
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
     * Pattern inputs carry the plugin's attributes, and the page loads its formatter.
     */
    public function test_pattern_inputs_get_their_attributes(): void {
        if (!pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $this->resetAfterTest();
        $widget = $this->widget($this->pattern_columns());

        $columns = array_column($this->widget_columns($widget->render('Field', self::IDS)), null, 'key');
        $widget->init_js(self::IDS, false);

        $this->assertSame([
            'maxlength' => 14,
            'placeholder' => '999.999.999-99',
            'data-customfield-pattern-mask' => '999.999.999-99',
            'inputmode' => 'numeric',
        ], $columns['cpf']['inputattributes']);
        $this->assertArrayNotHasKey('data-customfield-pattern-mask', $columns['code']['inputattributes']);
        $this->assertArrayNotHasKey('inputattributes', $columns['name'], 'A free column');
        $this->assertStringContainsString("require(['customfield_pattern/mask']", $this->amd_code());
    }

    /**
     * Without the plugin a pattern column is a plain text input, and no formatter is loaded.
     */
    public function test_without_the_plugin_pattern_inputs_are_text(): void {
        $this->resetAfterTest();
        $widget = $this->widget($this->pattern_columns(), null, true, false);

        $columns = array_column($this->widget_columns($widget->render('Field', self::IDS)), null, 'key');
        $widget->init_js(self::IDS, false);

        $this->assertArrayNotHasKey('inputattributes', $columns['cpf']);
        $this->assertStringNotContainsString("require(['customfield_pattern/mask']", $this->amd_code());
        $this->assertStringContainsString('local_repeatable/repeatable', $this->amd_code());
    }
}
