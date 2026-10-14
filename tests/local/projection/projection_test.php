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

use local_repeatable\local\schema;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/repeatable/tests/fixtures/projection_source.php');

/**
 * Tests for the per-set projection comparison and the reprojection paths.
 *
 * Moved from profilefield_repeatable's tests/local/projection_test.php with the projector. The
 * canonical records are fixture data in {@see test_source} instead of {user_info_data} rows, and the
 * projection is its temporary table instead of {profilefield_repeatable_data}.
 *
 * @package    local_repeatable
 * @category   test
 * @covers     \local_repeatable\local\projection\projector
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class projection_test extends \advanced_testcase {
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
     * Build one stored row.
     *
     * @param int $setid
     * @param string $data
     * @return \stdClass
     */
    private function row(int $setid, string $data): \stdClass {
        return (object) ['set_id' => (string)$setid, 'data' => $data];
    }

    /**
     * The two-column schema of the fixture field.
     *
     * @return schema
     */
    private function schema(): schema {
        return schema::from_columns([['key' => 'name', 'label' => 'Name'], ['key' => 'role', 'label' => 'Role']]);
    }

    /**
     * A source holding one canonical record of field 7 whose payload has no projection rows yet.
     *
     * @param string $json Stored payload.
     * @return array [source, dataid]
     */
    private function source_with_payload(string $json): array {
        $source = new test_source();
        $source->schemas[7] = $this->schema();
        $dataid = $source->add(7, 42, $json);

        return [$source, $dataid];
    }

    /**
     * Rows mirror a payload whatever their key order or spacing.
     */
    public function test_is_current_ignores_key_order_and_spacing(): void {
        $payload = ['@@ID#1' => ['name' => 'Alice', 'role' => 'Manager'], '@@ID#2' => ['name' => 'Bob', 'role' => '']];

        $this->assertTrue(projector::is_current($payload, [
            $this->row(1, '{"role": "Manager", "name": "Alice"}'),
            $this->row(2, '{"name":"Bob","role":""}'),
        ]));
        $this->assertTrue(projector::is_current([], []));
    }

    /**
     * Any difference in count, set numbering or values makes the rows stale.
     */
    public function test_is_current_detects_every_difference(): void {
        $payload = ['@@ID#1' => ['name' => 'Alice', 'role' => 'Manager'], '@@ID#2' => ['name' => 'Bob', 'role' => '']];
        $alice = $this->row(1, '{"name":"Alice","role":"Manager"}');

        $this->assertFalse(projector::is_current($payload, [$alice]), 'Missing set');
        $this->assertFalse(projector::is_current([], [$alice]), 'Rows left after the payload emptied');
        $this->assertFalse(projector::is_current($payload, [$alice, $this->row(3, '{"name":"Bob","role":""}')]), 'Set gap');
        $this->assertFalse(projector::is_current($payload, [$alice, $this->row(2, '{"name":"Bob","role":"x"}')]), 'Value');
        $this->assertFalse(projector::is_current($payload, [$alice, $this->row(2, '{"name":"Bob"}')]), 'Missing key');
        $this->assertFalse(projector::is_current($payload, [$alice, $this->row(2, 'not json')]), 'Corrupt row');
        // Numeric-looking strings must not compare equal by value.
        $this->assertFalse(projector::is_current(
            ['@@ID#1' => ['name' => '01', 'role' => '']],
            [$this->row(1, '{"name":"1","role":""}')]
        ), 'Numeric string');
    }

    /**
     * The record is re-read inside the transaction, so a save that landed after the staleness
     * snapshot is projected and the snapshot's payload never overwrites it.
     *
     * The race itself cannot be interleaved from a single PHP process, so the test builds its
     * outcome: rows are stale against the older payload, the stored payload then changes, and the
     * per-record writer, which takes no payload from its caller, must write the newer one.
     */
    public function test_reproject_record_writes_the_payload_as_stored_now(): void {
        global $DB;

        $subitems = $this->schema();
        [$source, $dataid] = $this->source_with_payload('{"@@ID#1":{"name":"Old","role":""}}');
        $this->assertTrue(projector::reproject_record($source, $dataid, $subitems));

        // The newer save lands after the snapshot that found the rows stale.
        $source->records[$dataid]->data = '{"@@ID#1":{"name":"New","role":"Boss"},"@@ID#2":{"name":"Two","role":""}}';
        $this->assertTrue(projector::reproject_record($source, $dataid, $subitems));

        $rows = $DB->get_records(test_source::TABLE, ['dataid' => $dataid], 'set_id ASC');
        // Decoded: PostgreSQL JSONB does not keep the spacing of the JSON it was given.
        $this->assertSame(
            [['name' => 'New', 'role' => 'Boss'], ['name' => 'Two', 'role' => '']],
            array_map(fn($row) => json_decode((string)$row->data, true), array_values($rows))
        );
        $this->assertSame(42, (int)reset($rows)->userid);
        $this->assertSame(7, (int)reset($rows)->fieldid);
    }

    /**
     * A record whose rows already match its stored payload is skipped, and a vanished record is not an error.
     */
    public function test_reproject_record_skips_current_and_missing_records(): void {
        global $DB;

        $subitems = $this->schema();
        [$source, $dataid] = $this->source_with_payload('{"@@ID#1":{"name":"Same","role":""}}');

        $this->assertTrue(projector::reproject_record($source, $dataid, $subitems));
        $DB->set_field(test_source::TABLE, 'timemodified', 1, ['dataid' => $dataid]);

        $this->assertFalse(projector::reproject_record($source, $dataid, $subitems), 'Already current');
        $this->assertEquals(1, $DB->get_field(test_source::TABLE, 'timemodified', ['dataid' => $dataid]));
        $this->assertFalse(projector::reproject_record($source, $dataid + 1000, $subitems), 'Missing record');
    }

    /**
     * A vanished record is skipped before anything is written, even when rows of it remain.
     */
    public function test_reproject_record_leaves_orphan_rows_of_a_missing_record(): void {
        global $DB;

        $subitems = $this->schema();
        [$source, $dataid] = $this->source_with_payload('{"@@ID#1":{"name":"Same","role":""}}');
        $this->assertTrue(projector::reproject_record($source, $dataid, $subitems));
        $before = $DB->get_records(test_source::TABLE, ['dataid' => $dataid], 'id ASC');
        $this->assertCount(1, $before);

        // The record vanishes but its projection rows stay behind as orphans.
        unset($source->records[$dataid]);

        $this->assertFalse(projector::reproject_record($source, $dataid, $subitems));
        $this->assertEquals($before, $DB->get_records(test_source::TABLE, ['dataid' => $dataid], 'id ASC'));
    }

    /**
     * Rows of a vanished record are stepped over without re-reading every later record.
     *
     * The outcome is the same either way (reproject_record() re-checks inside its transaction), so
     * the test compares cost: the reads of a run with an orphan group must equal those of a run
     * without one. Both runs happen on the same database, because a recordset counts as a different
     * number of reads on PostgreSQL and MariaDB.
     */
    public function test_reproject_field_skips_orphan_rows_without_rereading_records(): void {
        global $DB;

        [$source, $firstid] = $this->source_with_payload('{"@@ID#1":{"name":"One","role":""}}');
        $secondid = $source->add(7, 43, '{"@@ID#1":{"name":"Two","role":""}}');
        $this->assertSame(2, projector::reproject_field($source, 7, $this->schema()));
        $this->assertTrue($DB->record_exists(test_source::TABLE, ['dataid' => $secondid]));

        // Control: every projection is current.
        $before = $DB->perf_get_reads();
        $this->assertSame(0, projector::reproject_field($source, 7, $this->schema()));
        $baseline = $DB->perf_get_reads() - $before;

        // The first record vanishes and leaves its rows behind.
        unset($source->records[$firstid]);
        $this->assertTrue($DB->record_exists(test_source::TABLE, ['dataid' => $firstid]));

        $before = $DB->perf_get_reads();
        $this->assertSame(0, projector::reproject_field($source, 7, $this->schema()));
        $this->assertSame($baseline, $DB->perf_get_reads() - $before, 'Orphan rows must not make later records stale.');
    }

    /**
     * A field run rebuilds stale and missing projections and leaves current ones and orphan rows alone.
     *
     * The engine side of profilefield_repeatable's reproject_sets_test: a payload changed by a direct
     * write, a payload with no rows (a restored record), and rows of a vanished record that sort first.
     */
    public function test_reproject_field_rebuilds_only_stale_records(): void {
        global $DB;

        $source = new test_source();
        $source->schemas[7] = $this->schema();
        $current = $source->add(7, 41, '{"@@ID#1":{"name":"Alice","role":"Manager"}}');
        $stale = $source->add(7, 42, '{"@@ID#1":{"name":"Alice","role":"Manager"}}');
        foreach ([$current, $stale] as $dataid) {
            projector::reproject_record($source, $dataid, $this->schema());
        }
        $DB->set_field(test_source::TABLE, 'timemodified', 1, ['dataid' => $current]);
        $source->records[$stale]->data = '{"@@ID#1":{"name":"Bob","role":"Teacher"}}';
        $restored = $source->add(7, 43, '{"@@ID#1":{"name":"Carol","role":""}}');
        // Rows of a vanished canonical record sort before the others and must be skipped.
        $DB->insert_record(test_source::TABLE, (object) [
            'dataid' => 0,
            'fieldid' => 7,
            'userid' => 43,
            'set_id' => 9,
            'data' => '{"name":"Orphan","role":""}',
            'timecreated' => 1,
            'timemodified' => 1,
        ]);
        // Another field's record is not this run's business.
        $otherfield = $source->add(8, 44, '{"@@ID#1":{"name":"Dan","role":""}}');

        $this->assertSame(2, projector::reproject_field($source, 7));

        $sets = static fn(int $dataid): array => array_map(
            static fn(\stdClass $row): array => json_decode((string)$row->data, true),
            array_values($DB->get_records(test_source::TABLE, ['dataid' => $dataid], 'set_id ASC'))
        );
        $this->assertSame([['name' => 'Bob', 'role' => 'Teacher']], $sets($stale));
        $this->assertSame([['name' => 'Carol', 'role' => '']], $sets($restored));
        $this->assertSame([['name' => 'Orphan', 'role' => '']], $sets(0));
        $this->assertSame([], $sets($otherfield));
        $this->assertSame(1, (int)$DB->get_field(test_source::TABLE, 'timemodified', ['dataid' => $current]));
        $this->assertSame(0, projector::reproject_field($source, 99), 'A field without a schema is skipped.');
    }
}
