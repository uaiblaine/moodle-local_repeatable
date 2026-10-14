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

namespace local_repeatable\output;

use local_repeatable\form\column_editor;
use local_repeatable\local\bridge\pattern;
use local_repeatable\local\schema;

/**
 * Tests for the sets renderer: the plain list and the accordion of one stored payload.
 *
 * Metadata stays in docblocks: the engine will support Moodle 4.5, whose moodle-cs cannot read
 * PHPUnit attributes. The layout, the accordion's id and the projection rows that date each set
 * are the adapter's: the tests pass them in. Tests that need a companion skip without it, and
 * the one describing customfield_pattern's absence skips with it.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\output\sets_renderer
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sets_renderer_test extends \advanced_testcase {
    /** @var string The accordion id an adapter would build. */
    private const ACCORDIONID = 'local-repeatable-display-1-2-field';

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
     * Render a payload.
     *
     * @param schema $schema
     * @param string $payload Raw stored payload.
     * @param string $layout {@see sets_renderer::LAYOUT_ACCORDION} or {@see sets_renderer::LAYOUT_PLAIN}.
     * @param array $rows The projection rows (set_id, data, timemodified) of the value.
     * @param string $fieldname The field's name as stored.
     * @return string
     */
    private function render(
        schema $schema,
        string $payload,
        string $layout,
        array $rows = [],
        string $fieldname = 'Repeatable test'
    ): string {
        $renderer = new sets_renderer($schema, $payload, $fieldname, self::ACCORDIONID, fn(): array => $rows);
        return $renderer->render($layout);
    }

    /**
     * A free "codigo_diretoria" column on the "diretoria" dictionary, then a free "codigo_escola".
     *
     * @return schema
     */
    private function dictionary_schema(): schema {
        return $this->schema([
            ['label' => 'codigo_diretoria', 'mode' => 'dictionary', 'domain' => 'diretoria'],
            'codigo_escola',
        ]);
    }

    /**
     * Create the "diretoria" dictionary with code 16, or skip without the companion.
     *
     * @return void
     */
    private function create_diretoria(): void {
        if (!class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries plugin is not available.');
        }
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'diretoria', 'name' => 'Diretoria']);
        $generator->create_item(['domain' => 'diretoria', 'code' => '16', 'label' => 'Sao Paulo']);
    }

    /**
     * The plain layout is a list labelled "Set N", never the accordion.
     */
    public function test_plain_layout(): void {
        $this->resetAfterTest();

        $output = $this->render(
            $this->schema(['codigo_turma', 'codigo_diretoria', 'codigo_escola']),
            '{"@@ID#1":{"codigo_turma":"101-A","codigo_diretoria":"DIR","codigo_escola":"EE"}}',
            sets_renderer::LAYOUT_PLAIN
        );

        $this->assertStringContainsString('local-repeatable-display', $output);
        $this->assertStringNotContainsString('local-repeatable-display-accordion', $output);
        $this->assertStringContainsString(get_string('repeatableset', 'local_repeatable', 1), $output);
    }

    /**
     * The accordion titles each set with its title column and shows the sync time of its projection row.
     */
    public function test_accordion_with_sync_timestamp(): void {
        global $PAGE;
        $this->resetAfterTest();
        $timemodified = time();

        $output = $this->render(
            $this->schema(['codigo_turma', 'codigo_diretoria', 'codigo_escola']),
            '{"@@ID#1":{"codigo_turma":"101-A","codigo_diretoria":"DIR-SP","codigo_escola":"EE Alpha"}}',
            sets_renderer::LAYOUT_ACCORDION,
            [(object)[
                'set_id' => 1,
                'data' => '{"codigo_turma":"101-A","codigo_diretoria":"DIR-SP","codigo_escola":"EE Alpha"}',
                'timemodified' => $timemodified,
            ]]
        );

        $this->assertStringContainsString('local-repeatable-display-accordion', $output);
        $this->assertStringContainsString('<details class="local-repeatable-item" open>', $output);
        $this->assertStringContainsString('local-repeatable-summary', $output);
        $this->assertStringContainsString('101-A', $output);
        $this->assertStringContainsString(userdate($timemodified, get_string('strftimedatetimeshort', 'langconfig')), $output);
        $this->assertSame(0, substr_count($output, 'codigo_turma'), 'The title column is not a row');
        $this->assertSame(1, substr_count($output, 'codigo_diretoria'));
        $amdjscode = (new \ReflectionProperty($PAGE->requires, 'amdjscode'))->getValue($PAGE->requires);
        $this->assertStringContainsString('local_repeatable/displayaccordion', implode("\n", $amdjscode));
    }

    /**
     * Both layouts escape the names once.
     */
    public function test_escapes_names_once(): void {
        $this->resetAfterTest();
        $schema = $this->schema(['role', 'Item R&D <b>x</b>']);
        $payload = '{"@@ID#1":{"role":"first","item_r_d_b_x_b":"second"}}';

        $plain = $this->render($schema, $payload, sets_renderer::LAYOUT_PLAIN, [], 'Field R&D <b>x</b>');
        $accordion = $this->render($schema, $payload, sets_renderer::LAYOUT_ACCORDION, [], 'Field R&D <b>x</b>');

        // The plain list prints the label of each filled column; the accordion titles the set with the title
        // column's value, labels the other rows with their names and puts the field name in its aria-label.
        $this->assertStringContainsString('local-repeatable-display-accordion', $accordion);
        $this->assertSame(1, substr_count($plain, 'Item R&amp;D'));
        $this->assertSame(1, substr_count($accordion, 'Item R&amp;D'));
        $this->assertStringNotContainsString('&amp;amp;', $plain);
        $this->assertStringNotContainsString('<b>', $plain);
        $this->assertSame(1, substr_count($accordion, 'aria-label="Field R&amp;D'));
        $this->assertStringNotContainsString('&amp;amp;', $accordion);
        $this->assertStringNotContainsString('<b>', $accordion);
    }

    /**
     * The accordion shows no sync footer without a timestamp.
     */
    public function test_accordion_hides_sync_footer_without_timestamp(): void {
        $this->resetAfterTest();

        $output = $this->render(
            $this->schema(['codigo_turma', 'codigo_diretoria', 'codigo_escola']),
            '{"@@ID#1":{"codigo_turma":"101-A","codigo_diretoria":"DIR","codigo_escola":"EE"}}',
            sets_renderer::LAYOUT_ACCORDION
        );

        $this->assertStringContainsString('local-repeatable-display-accordion', $output);
        $this->assertStringNotContainsString('local-repeatable-sync', $output);
    }

    /**
     * The accordion titles a set "Set N" when its title column is empty.
     */
    public function test_accordion_falls_back_to_set_title_when_the_title_is_empty(): void {
        $this->resetAfterTest();

        $output = $this->render(
            $this->schema(['codigo_turma', 'codigo_diretoria', 'codigo_escola']),
            '{"@@ID#1":{"codigo_turma":"","codigo_diretoria":"DIR","codigo_escola":"EE"}}',
            sets_renderer::LAYOUT_ACCORDION
        );

        $this->assertStringContainsString(get_string('repeatableset', 'local_repeatable', 1), $output);
    }

    /**
     * Both layouts show the code when the dictionary has no label for it.
     */
    public function test_keeps_code_when_reference_not_found(): void {
        $this->resetAfterTest();
        $payload = '{"@@ID#1":{"codigo_diretoria":"16","codigo_escola":"45"}}';

        foreach ([sets_renderer::LAYOUT_ACCORDION, sets_renderer::LAYOUT_PLAIN] as $layout) {
            $this->assertStringContainsString('16', $this->render($this->dictionary_schema(), $payload, $layout), $layout);
        }
    }

    /**
     * The accordion shows the label of a code the dictionary has.
     */
    public function test_accordion_resolves_reference_label(): void {
        $this->resetAfterTest();
        $this->create_diretoria();

        $output = $this->render(
            $this->dictionary_schema(),
            '{"@@ID#1":{"codigo_diretoria":"16","codigo_escola":"45"}}',
            sets_renderer::LAYOUT_ACCORDION
        );

        $this->assertStringContainsString('Sao Paulo', $output);
    }

    /**
     * The plain list shows the label of a code the dictionary has, instead of the code.
     */
    public function test_plain_resolves_reference_label(): void {
        $this->resetAfterTest();
        $this->create_diretoria();

        $output = $this->render(
            $this->dictionary_schema(),
            '{"@@ID#1":{"codigo_diretoria":"16","codigo_escola":"45"}}',
            sets_renderer::LAYOUT_PLAIN
        );

        $this->assertStringContainsString('Sao Paulo', $output);
        $this->assertStringNotContainsString('>16<', $output);
    }

    /**
     * The plain list follows the schema's column order, not the payload's key order.
     */
    public function test_plain_respects_column_order(): void {
        $this->resetAfterTest();

        $output = $this->render(
            $this->schema(['codigo_turma', 'codigo_diretoria', 'codigo_escola']),
            '{"@@ID#1":{"codigo_escola":"EE","codigo_diretoria":"DIR","codigo_turma":"101"}}',
            sets_renderer::LAYOUT_PLAIN
        );

        $posturma = strpos($output, '101');
        $posdiretoria = strpos($output, 'DIR');
        $posescola = strpos($output, 'EE');
        $this->assertNotFalse($posturma);
        $this->assertNotFalse($posdiretoria);
        $this->assertNotFalse($posescola);
        $this->assertLessThan($posdiretoria, $posturma);
        $this->assertLessThan($posescola, $posdiretoria);
    }

    /**
     * The accordion resolves labels of a schema saved canonically by the column editor, domains lower-cased.
     */
    public function test_accordion_resolves_reference_with_canonical_schema(): void {
        $this->resetAfterTest();
        if (!class_exists('\\local_dictionaries\\resolver')) {
            $this->markTestSkipped('local_dictionaries plugin is not available.');
        }
        $generator = $this->getDataGenerator()->get_plugin_generator('local_dictionaries');
        $generator->create_domain(['shortname' => 'diretorias', 'name' => 'Diretorias']);
        $generator->create_item(['domain' => 'diretorias', 'code' => '16', 'label' => 'Sao Paulo']);
        $generator->create_domain(['shortname' => 'escolas', 'name' => 'Escolas']);
        $generator->create_item(['domain' => 'escolas', 'code' => '45', 'label' => 'EE Alpha']);

        $canonical = column_editor::canonical($this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json([
            ['label' => 'Codigo_Diretoria', 'mode' => 'dictionary', 'domain' => 'Diretorias'],
            ['label' => 'Codigo_Escola', 'mode' => 'dictionary_open', 'domain' => 'ESCOLAS'],
        ]));
        $output = $this->render(
            schema::from_json($canonical),
            '{"@@ID#1":{"codigo_diretoria":"16","codigo_escola":"45"}}',
            sets_renderer::LAYOUT_ACCORDION
        );

        $this->assertStringContainsString('local-repeatable-display-accordion', $output);
        $this->assertStringContainsString('Sao Paulo', $output);
        $this->assertStringContainsString('EE Alpha', $output);
    }

    /**
     * A projection row lends its sync time only to the payload set it still mirrors.
     */
    public function test_takes_sync_time_from_matching_rows_only(): void {
        $this->resetAfterTest();
        $schema = $this->schema(['name', 'role']);
        $payload = '{"@@ID#1":{"name":"Alice","role":"Manager"}}';
        $synctime = 1700000000;
        $syncstring = userdate($synctime, get_string('strftimedatetimeshort', 'langconfig'));

        // Keys in another order, as PostgreSQL JSONB returns them, still match the set.
        $matching = [
            (object) ['id' => 1, 'set_id' => 1, 'data' => '{"role": "Manager", "name": "Alice"}', 'timemodified' => $synctime],
            (object) ['id' => 2, 'set_id' => 2, 'data' => '{"name": "Ghost", "role": "Stale"}', 'timemodified' => $synctime],
        ];
        $stale = [
            (object) ['id' => 1, 'set_id' => 1, 'data' => '{"name": "Old", "role": "Manager"}', 'timemodified' => $synctime],
        ];

        $output = $this->render($schema, $payload, sets_renderer::LAYOUT_ACCORDION, $matching);
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringNotContainsString('Ghost', $output);
        $this->assertStringContainsString($syncstring, $output);

        $output = $this->render($schema, $payload, sets_renderer::LAYOUT_ACCORDION, $stale);
        $this->assertStringContainsString('Alice', $output);
        $this->assertStringNotContainsString('Old', $output);
        $this->assertStringNotContainsString($syncstring, $output);
    }

    /**
     * The schema of the pattern tests: a free title, a CPF stored raw, a CPF stored masked, a regex.
     *
     * @return schema
     */
    private function pattern_schema(): schema {
        return $this->schema([
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
            ['key' => 'cpfmasked', 'label' => 'Masked CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf']],
            ['key' => 'code', 'label' => 'Code', 'mode' => 'pattern',
                'pattern' => ['regex' => '[A-Z]{3}', 'regexerror' => 'Three capitals & nothing else']],
        ]);
    }

    /**
     * A raw-stored pattern value is shown with its mask, escaped.
     */
    public function test_formats_pattern_values(): void {
        if (!pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $this->resetAfterTest();

        $output = $this->render(
            $this->pattern_schema(),
            json_encode(['@@ID#1' => ['name' => 'A', 'cpf' => '52998224725', 'code' => '<b>']]),
            sets_renderer::LAYOUT_PLAIN
        );

        $this->assertStringContainsString('529.982.247-25', $output);
        $this->assertStringNotContainsString('52998224725', $output);
        $this->assertStringContainsString('&lt;b&gt;', $output);
    }

    /**
     * Without the plugin a pattern value is shown as stored.
     */
    public function test_without_the_plugin_shows_the_stored_value(): void {
        if (pattern::available()) {
            $this->markTestSkipped('customfield_pattern is installed; its absence is tested on Moodle 4.5 and 5.0.');
        }
        $this->resetAfterTest();

        $output = $this->render(
            $this->pattern_schema(),
            json_encode(['@@ID#1' => ['name' => 'A', 'cpf' => '52998224725', 'code' => '<b>x</b>']]),
            sets_renderer::LAYOUT_PLAIN
        );

        $this->assertStringContainsString('52998224725', $output);
        $this->assertStringNotContainsString('529.982.247-25', $output);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $output);
        $this->assertStringNotContainsString('<b>x', $output);
    }
}
