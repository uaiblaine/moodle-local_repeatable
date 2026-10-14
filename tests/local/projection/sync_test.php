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

namespace local_repeatable\local\projection;

use local_repeatable\local\sql;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/repeatable/tests/fixtures/projection_source.php');

/**
 * Tests for the projector's row writer on a temporary projection table.
 *
 * The engine owns no table, so these tests prove first that the writer works on the temporary
 * table of {@see test_source}: the {table} placeholders resolve to it, and the upsert keeps one row
 * per (dataid, set_id). PostgreSQL takes the ON CONFLICT branch, against the temporary table's unique
 * index; every other family takes the select-then-write branch. A run on each family covers both.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\projection\projector
 * @covers     \local_repeatable\local\projection\store
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sync_test extends \advanced_testcase {
    /**
     * Create the temporary projection table.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        test_source::create_table();
    }

    /**
     * Drop the temporary projection table.
     *
     * @return void
     */
    protected function tearDown(): void {
        test_source::drop_table();
        parent::tearDown();
    }

    /**
     * The rows of one canonical record, keyed by set_id.
     *
     * @param int $dataid
     * @return \stdClass[]
     */
    private function rows(int $dataid): array {
        global $DB;

        $rows = [];
        foreach ($DB->get_records(test_source::TABLE, ['dataid' => $dataid], 'set_id ASC') as $row) {
            $rows[(int)$row->set_id] = $row;
        }

        return $rows;
    }

    /**
     * A resync updates the rows in place, keeps timecreated, and deletes the rows of vanished sets.
     *
     * The second sync writes set 1 again, so on PostgreSQL the ON CONFLICT clause must find the
     * temporary table's unique (dataid, set_id) index, and elsewhere the select must find the row.
     */
    public function test_sync_upserts_rows_on_the_temporary_table(): void {
        global $DB;

        $source = new test_source();
        $dataid = $source->add(7, 42, '');
        $other = $source->add(7, 43, '');

        projector::sync($source, $dataid, 7, 42, ['@@ID#1' => ['name' => 'Alice'], '@@ID#2' => ['name' => 'Bob']]);
        projector::sync($source, $other, 7, 43, ['@@ID#1' => ['name' => 'Other']]);
        $first = $this->rows($dataid);
        $this->assertSame([1, 2], array_keys($first));
        $this->assertSame(['name' => 'Alice'], json_decode((string)$first[1]->data, true));
        $this->assertSame(7, (int)$first[1]->fieldid);
        $this->assertSame(42, (int)$first[1]->userid);
        $this->assertGreaterThan(0, (int)$first[1]->timecreated);
        $this->assertSame((int)$first[1]->timecreated, (int)$first[1]->timemodified);

        $DB->set_field(test_source::TABLE, 'timecreated', 1, ['dataid' => $dataid]);
        $DB->set_field(test_source::TABLE, 'timemodified', 1, ['dataid' => $dataid]);
        $reads = $DB->perf_get_reads();
        projector::sync($source, $dataid, 8, 44, ['@@ID#1' => ['name' => 'Alice Updated']]);
        // The ON CONFLICT branch reads nothing; the portable one selects the set's row first.
        $this->assertSame(sql::is_postgres($DB) ? 0 : 1, $DB->perf_get_reads() - $reads, 'Branch of ' . $DB->get_dbfamily());

        $second = $this->rows($dataid);
        $this->assertSame([1], array_keys($second), 'The vanished set 2 is deleted.');
        $this->assertSame((int)$first[1]->id, (int)$second[1]->id, 'Set 1 is updated in place.');
        $this->assertSame(['name' => 'Alice Updated'], json_decode((string)$second[1]->data, true));
        $this->assertSame(8, (int)$second[1]->fieldid);
        $this->assertSame(44, (int)$second[1]->userid);
        $this->assertSame(1, (int)$second[1]->timecreated, 'timecreated is kept on update.');
        $this->assertGreaterThan(1, (int)$second[1]->timemodified);
        $this->assertCount(1, $this->rows($other), 'Another record keeps its rows.');

        projector::sync($source, $dataid, 8, 44, []);
        $this->assertSame([], $this->rows($dataid), 'An empty payload deletes every row.');
        $this->assertCount(1, $this->rows($other));
    }

    /**
     * The temporary table carries the unique (dataid, set_id) index the upsert relies on.
     *
     * Control for the test above: without the index a second insert of a set would succeed, the
     * ON CONFLICT clause would have no arbiter, and the projection could hold one set twice.
     */
    public function test_temporary_table_refuses_a_second_row_for_a_set(): void {
        global $DB;

        $row = (object)[
            'dataid' => 1,
            'fieldid' => 7,
            'userid' => 42,
            'set_id' => 1,
            'data' => '{}',
            'timecreated' => 1,
            'timemodified' => 1,
        ];
        $DB->insert_record(test_source::TABLE, $row);
        $DB->insert_record(test_source::TABLE, (object)(['set_id' => 2] + (array)$row));

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record(test_source::TABLE, $row);
    }
}
