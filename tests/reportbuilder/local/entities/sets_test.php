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

namespace local_repeatable\reportbuilder\local\entities;

use core_reportbuilder\local\report\column;
use local_repeatable\local\bridge\pattern;
use local_repeatable\local\projection\test_source;
use local_repeatable\local\schema;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/repeatable/tests/fixtures/projection_source.php');

/**
 * Tests for the abstract Report Builder entity, on its column and filter objects.
 *
 * The adapter's datasource and reports are the adapter's to test; here a test subclass gives the
 * schema and the availability, and a column's callbacks are read through
 * {@see column::format_value()} with a row keyed by the column's own field alias. The name filter's
 * options are the one path that runs SQL: they read the temporary projection table of
 * {@see test_source}.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\reportbuilder\local\entities\sets
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sets_test extends \advanced_testcase {
    /** @var int Field id the test entities represent. */
    private const FIELDID = 7;

    /** @var int The canonical record id of the last stored projection row. */
    private int $lastdataid = 0;

    /**
     * Create the temporary projection table and forget memoised labels.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        test_source::create_table();
        sets::reset_label_memo();
    }

    /**
     * Drop the temporary projection table and forget memoised labels.
     *
     * @return void
     */
    protected function tearDown(): void {
        sets::reset_label_memo();
        test_source::drop_table();
        parent::tearDown();
    }

    /**
     * The fixture schema: a dictionary column "diretoria" and a free column "escola".
     *
     * @param array $extra Further columns.
     * @return schema
     */
    private function schema(array $extra = []): schema {
        $json = $this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json(array_merge([
            ['key' => 'diretoria', 'label' => 'Diretoria', 'mode' => 'dictionary', 'domain' => 'diretorias'],
            ['key' => 'escola', 'label' => 'Escola'],
        ], $extra));

        return schema::from_json($json);
    }

    /**
     * An initialised entity over the temporary table, with the schema and availability given.
     *
     * The subclass answers dictionary lookups from the map passed in, as the companion would.
     *
     * @param schema $schema
     * @param bool $available What the adapter decides for the current user.
     * @param array $labels Label by "domain|code".
     * @return sets
     */
    private function entity(schema $schema, bool $available, array $labels = []): sets {
        $entity = new class (test_source::TABLE, self::FIELDID, $schema, $available) extends sets {
            /** @var array Label by "domain|code", answered by lookup_label(). */
            public static array $labels = [];

            /** @var schema The schema the adapter reads. */
            private schema $fixtureschema;

            /** @var bool The availability the adapter decides. */
            private bool $available;

            /**
             * Constructor.
             *
             * @param string $table
             * @param int $fieldid
             * @param schema $schema
             * @param bool $available
             */
            public function __construct(string $table, int $fieldid, schema $schema, bool $available) {
                parent::__construct($table, $fieldid);
                $this->fixtureschema = $schema;
                $this->available = $available;
            }

            /**
             * The fixture schema.
             *
             * @return schema
             */
            protected function get_schema(): schema {
                return $this->fixtureschema;
            }

            /**
             * The fixture availability.
             *
             * @return bool
             */
            protected function is_available(): bool {
                return $this->available;
            }

            /**
             * The fixture label of a code.
             *
             * @param string $domain
             * @param string $code
             * @return string|null
             */
            protected static function lookup_label(string $domain, string $code): ?string {
                return self::$labels[$domain . '|' . $code] ?? null;
            }
        };
        $entity::$labels = $labels;
        $entity->set_entity_name('sets');
        $entity->initialise();

        return $entity;
    }

    /**
     * What a column shows for one stored value: its callbacks over a row keyed by its field alias.
     *
     * @param column $column
     * @param string|null $value
     * @return mixed
     */
    private function format(column $column, ?string $value) {
        $fields = $column->get_fields();
        $this->assertCount(1, $fields);
        $this->assertSame(1, preg_match('/ AS (\w+)$/', $fields[0], $matches));

        return $column->format_value([$matches[1] => $value]);
    }

    /**
     * Store one projection row of the fixture field.
     *
     * @param int $fieldid
     * @param array $set
     * @return void
     */
    private function store_set(int $fieldid, array $set): void {
        global $DB;

        $DB->insert_record(test_source::TABLE, (object) [
            'dataid' => ++$this->lastdataid,
            'fieldid' => $fieldid,
            'userid' => 2,
            'set_id' => 1,
            'data' => json_encode($set),
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
    }

    /**
     * Every column and filter follows the availability the adapter decides.
     *
     * @dataProvider availability_provider
     * @param bool $available
     */
    public function test_columns_and_filters_follow_availability(bool $available): void {
        $entity = $this->entity($this->schema(), $available);

        $names = ['setnumber', 'diretoria_name', 'diretoria_code', 'escola'];
        $this->assertSame($names, array_keys($entity->get_columns()));
        $this->assertSame($names, array_keys($entity->get_filters()));
        foreach ($names as $name) {
            $this->assertSame($available, $entity->get_column($name)->get_is_available(), "Column {$name}");
            $this->assertSame($available, $entity->get_filter($name)->get_is_available(), "Filter {$name}");
        }
    }

    /**
     * Both decisions an adapter can take.
     *
     * @return array
     */
    public static function availability_provider(): array {
        return [
            'available' => [true],
            'not available' => [false],
        ];
    }

    /**
     * Column and filter names follow the column keys, as saved reports persist them.
     *
     * The same list as profilefield_repeatable's saved-report pin test, without the entity prefix: a
     * free, a dictionary, an any-code dictionary and, with customfield_pattern, a pattern column.
     */
    public function test_column_and_filter_names_follow_the_keys(): void {
        $columns = [
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'lang', 'label' => 'Language', 'mode' => 'dictionary', 'domain' => 'langs'],
            ['key' => 'level', 'label' => 'Level', 'mode' => 'dictionary_open', 'domain' => 'langs'],
        ];
        if (pattern::available()) {
            $columns[] = ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf']];
        }
        $schema = schema::from_json($this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json($columns));
        $entity = $this->entity($schema, true);

        $expected = ['setnumber', 'name', 'lang_name', 'lang_code', 'level_name', 'level_code'];
        if (pattern::available()) {
            $expected[] = 'cpf';
        }
        $this->assertSame($expected, array_keys($entity->get_columns()), 'Columns');
        $this->assertSame($expected, array_keys($entity->get_filters()), 'Filters');
    }

    /**
     * Value, code and resolved-name columns escape stored text and resolved labels.
     */
    public function test_value_columns_escape_stored_text(): void {
        $entity = $this->entity($this->schema(), true, ['diretorias|16' => '<b>Centro</b>']);
        $img = '<img src=x onerror=alert(1)>';

        $name = $entity->get_column('diretoria_name');
        $code = $entity->get_column('diretoria_code');
        $value = $entity->get_column('escola');

        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $this->format($name, $img), 'Name column, unresolved code');
        $this->assertSame('&lt;b&gt;Centro&lt;/b&gt;', $this->format($name, '16'), 'Name column, resolved label');
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $this->format($code, $img), 'Code column');
        $this->assertSame('&lt;i&gt;45&lt;/i&gt;', $this->format($value, '<i>45</i>'), 'Value column');
        // Control: plain text passes through unchanged.
        $this->assertSame('45', $this->format($value, '45'));
    }

    /**
     * Name filter options keep the raw code as key, escape the label core prints unescaped, and
     * list only the codes of this field.
     */
    public function test_name_filter_options_escape_labels(): void {
        $code = '<img src=x onerror=alert(1)>';
        $this->store_set(self::FIELDID, ['diretoria' => $code, 'escola' => '1']);
        $this->store_set(self::FIELDID, ['diretoria' => ' ', 'escola' => '2']);
        $this->store_set(self::FIELDID + 1, ['diretoria' => 'otherfield', 'escola' => '3']);
        $entity = $this->entity($this->schema(), true);

        $this->assertSame([$code => s($code)], $entity->get_filter('diretoria_name')->get_options());
    }

    /**
     * The options query reads only the rows an adapter's scope admits; the default scope admits every row of the field.
     */
    public function test_name_filter_options_follow_the_adapters_scope(): void {
        global $DB;

        $this->store_set(self::FIELDID, ['diretoria' => 'in-area', 'escola' => '1']);
        $inarea = (int)$DB->get_field(test_source::TABLE, 'MAX(dataid)', [], MUST_EXIST);
        $this->store_set(self::FIELDID, ['diretoria' => 'other-area', 'escola' => '2']);
        $schema = $this->schema();

        $scoped = new class (test_source::TABLE, self::FIELDID, $schema, $inarea) extends sets {
            /** @var schema The schema the adapter reads. */
            private schema $fixtureschema;

            /** @var int The only data id the scope admits. */
            private int $dataid;

            /**
             * Constructor.
             *
             * @param string $table
             * @param int $fieldid
             * @param schema $schema
             * @param int $dataid
             */
            public function __construct(string $table, int $fieldid, schema $schema, int $dataid) {
                parent::__construct($table, $fieldid);
                $this->fixtureschema = $schema;
                $this->dataid = $dataid;
            }

            /**
             * The fixture schema.
             *
             * @return schema
             */
            protected function get_schema(): schema {
                return $this->fixtureschema;
            }

            /**
             * Always available.
             *
             * @return bool
             */
            protected function is_available(): bool {
                return true;
            }

            /**
             * Admit one data id, with a bound parameter.
             *
             * @param string $alias
             * @return array
             */
            protected function filter_options_scope(string $alias): array {
                return ["{$alias}.dataid = :scopedataid", ['scopedataid' => $this->dataid]];
            }
        };
        $scoped->set_entity_name('sets');
        $scoped->initialise();

        $this->assertSame(['in-area' => 'in-area'], $scoped->get_filter('diretoria_name')->get_options());

        $default = $this->entity($schema, true);
        $this->assertSame(
            ['in-area' => 'in-area', 'other-area' => 'other-area'],
            $default->get_filter('diretoria_name')->get_options()
        );
    }

    /**
     * A code costs one resolver lookup however many rows show it, and an unresolved code too.
     */
    public function test_resolved_name_lookups_are_memoised_including_misses(): void {
        $probe = new class (test_source::TABLE, self::FIELDID) extends sets {
            /** @var string[] Domain and code of every lookup that reached the resolver. */
            public static array $lookups = [];

            /**
             * Unused: the test calls the resolver entry point only.
             *
             * @return schema
             */
            protected function get_schema(): schema {
                return schema::from_columns([]);
            }

            /**
             * Unused: the test calls the resolver entry point only.
             *
             * @return bool
             */
            protected function is_available(): bool {
                return true;
            }

            /**
             * Record the lookup; only code 16 has a label.
             *
             * @param string $domain
             * @param string $code
             * @return string|null
             */
            protected static function lookup_label(string $domain, string $code): ?string {
                self::$lookups[] = $domain . '|' . $code;
                return $code === '16' ? 'Diretoria Centro' : null;
            }

            /**
             * Expose the protected resolver entry point.
             *
             * @param string $domain
             * @param string|null $code
             * @return string
             */
            public static function label(string $domain, ?string $code): string {
                return static::resolve_label($domain, $code);
            }
        };

        for ($row = 0; $row < 3; $row++) {
            $this->assertSame('Diretoria Centro', $probe::label('diretorias', '16'));
            $this->assertSame('99', $probe::label('diretorias', '99'));
            $this->assertSame('99', $probe::label('escolas', ' 99 '));
            $this->assertSame('', $probe::label('diretorias', ' '));
        }

        $this->assertSame(['diretorias|16', 'diretorias|99', 'escolas|99'], $probe::$lookups);
    }

    /**
     * The schema of a pattern column holding a CPF stored raw.
     *
     * @return schema
     */
    private function pattern_schema(): schema {
        return schema::from_json($this->getDataGenerator()->get_plugin_generator('local_repeatable')->schema_json([
            ['key' => 'name', 'label' => 'Name', 'title' => true],
            ['key' => 'cpf', 'label' => 'CPF', 'mode' => 'pattern', 'pattern' => ['preset' => 'cpf', 'storeformat' => 'raw']],
        ]));
    }

    /**
     * A pattern column shows its values as customfield_pattern formats them, escaped.
     */
    public function test_pattern_column_is_formatted_and_escaped(): void {
        if (!pattern::available()) {
            $this->markTestSkipped('customfield_pattern with its public API is not installed (Moodle 5.1 or later).');
        }
        $column = $this->entity($this->pattern_schema(), true)->get_column('cpf');

        $this->assertSame('529.982.247-25', $this->format($column, '52998224725'));
        // Written directly, as a CSV upload or a sync would: nothing validated it.
        $this->assertSame('&lt;b&gt;1&lt;/b&gt;', $this->format($column, '<b>1</b>'));
    }

    /**
     * Without customfield_pattern a pattern column shows its values as stored, escaped.
     */
    public function test_pattern_column_without_the_plugin_shows_stored_values(): void {
        if (pattern::available()) {
            $this->markTestSkipped('customfield_pattern is installed; its absence is tested where it is not.');
        }
        $column = $this->entity($this->pattern_schema(), true)->get_column('cpf');

        $this->assertSame('52998224725', $this->format($column, '52998224725'));
        $this->assertSame('&lt;b&gt;1&lt;/b&gt;', $this->format($column, '<b>1</b>'));
    }
}
