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
 * Tests for the SQL builders, run against a temporary table with a text JSON column.
 *
 * The engine owns no table, so each test creates a temporary one shaped like an adapter's
 * projection column (text, read as JSON).
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\sql
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sql_test extends \advanced_testcase {
    /** @var string Name of the temporary table. */
    private const TABLE = 'local_repeatable_sqltest';

    /** @var \xmldb_table|null The temporary table, while it exists. */
    private ?\xmldb_table $table = null;

    /**
     * Create the temporary table.
     *
     * @return void
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $table = new \xmldb_table(self::TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $DB->get_manager()->create_temp_table($table);
        $this->table = $table;
    }

    /**
     * Drop the temporary table.
     *
     * @return void
     */
    protected function tearDown(): void {
        global $DB;

        if ($this->table !== null) {
            $DB->get_manager()->drop_table($this->table);
            $this->table = null;
        }
        parent::tearDown();
    }

    /**
     * Insert one row and return its id.
     *
     * @param string $data JSON payload for one set.
     * @return int
     */
    private function insert_set_row(string $data): int {
        global $DB;

        return (int)$DB->insert_record(self::TABLE, (object)['data' => $data]);
    }

    /**
     * Read one sub-item value back through the generated SQL expression.
     *
     * @param int $rowid Row id.
     * @param string $subitem Sub-item key to extract.
     * @return mixed
     */
    private function read_subitem(int $rowid, string $subitem) {
        global $DB;

        [$sql, $params] = sql::subitem_value($DB, 'data', $subitem, 'k');
        $params['id'] = $rowid;

        return $DB->get_field_sql(
            "SELECT {$sql} FROM {" . self::TABLE . "} WHERE id = :id",
            $params
        );
    }

    /**
     * The generated expression extracts a present sub-item value on every DB family.
     */
    public function test_subitem_value_extracts_present_value(): void {
        $rowid = $this->insert_set_row('{"diretoria":"16154","escola":"45"}');

        $this->assertSame('16154', $this->read_subitem($rowid, 'diretoria'));
        $this->assertSame('45', $this->read_subitem($rowid, 'escola'));
    }

    /**
     * A missing sub-item key resolves to an empty value rather than erroring.
     */
    public function test_subitem_value_missing_key_is_empty(): void {
        $rowid = $this->insert_set_row('{"diretoria":"16154"}');

        $this->assertEmpty($this->read_subitem($rowid, 'escola'));
    }

    /**
     * Keys containing spaces and unicode are handled through bound parameters.
     */
    public function test_subitem_value_key_with_space_and_unicode(): void {
        $rowid = $this->insert_set_row('{"Spoken language":"Português"}');

        $this->assertSame('Português', $this->read_subitem($rowid, 'Spoken language'));
    }

    /**
     * A column with a legacy label is read under its key, else under the label; without one, under its key only.
     */
    public function test_column_value_reads_the_legacy_label(): void {
        global $DB;

        $schema = schema::from_columns([['key' => 'name', 'label' => 'Name', 'title' => true]]);
        $labelled = $this->insert_set_row('{"Old name":"Alice"}');
        $both = $this->insert_set_row('{"name":"Bob","Old name":"ignored"}');
        $read = function (schema $schema, int $rowid) use ($DB) {
            [$sql, $params] = sql::column_value($DB, 'data', $schema, 'name', 'k', 'l');
            return $DB->get_field_sql("SELECT {$sql} FROM {" . self::TABLE . "} WHERE id = :id", $params + ['id' => $rowid]);
        };

        $legacy = $schema->with_legacy_labels(['name' => 'Old name']);
        $this->assertSame('Alice', $read($legacy, $labelled));
        $this->assertSame('Bob', $read($legacy, $both));

        // Control: without the legacy label nothing is read under it.
        $this->assertEmpty($read($schema, $labelled));
        $this->assertSame('Bob', $read($schema, $both));
    }

    /**
     * Identifiers are double-quoted with embedded quotes doubled; a NUL byte is refused.
     */
    public function test_quote_identifier(): void {
        $this->assertSame('"pfrd_f1_x"', sql::quote_identifier('pfrd_f1_x'));
        $this->assertSame('"a""b"', sql::quote_identifier('a"b'));

        $this->expectException(\coding_exception::class);
        sql::quote_identifier("a\0b");
    }
}
