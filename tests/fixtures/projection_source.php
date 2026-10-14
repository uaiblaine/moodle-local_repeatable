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

/**
 * A projection source for the engine's tests: canonical records in memory, rows in a temporary table.
 *
 * The engine owns no table, so the tests build a temporary projection table with the shape of
 * {profilefield_repeatable_data} (profilefield_repeatable's db/install.xml): the same columns, the
 * unique (dataid, set_id) index the PostgreSQL upsert of {@see projector} names in ON CONFLICT, and
 * the (fieldid, userid) index. A test creates it in setUp() and drops it in tearDown().
 *
 * The canonical records are fixture data held here; a test changes them the way a direct write to an
 * adapter's canonical store would, without calling the engine.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class test_source implements source {
    /** @var string The temporary projection table, without prefix or braces. */
    public const TABLE = 'local_repeatable_projtest';

    /** @var string The owner column, as in {profilefield_repeatable_data}. */
    public const OWNER = 'userid';

    /** @var \stdClass[] Canonical records {id, fieldid, ownerid, data} by id. */
    public array $records = [];

    /** @var schema[] Column schema by field id. */
    public array $schemas = [];

    /** @var int The id the next canonical record gets. */
    private int $nextid = 1;

    /**
     * The temporary projection table's definition.
     *
     * @return \xmldb_table
     */
    public static function table_definition(): \xmldb_table {
        $table = new \xmldb_table(self::TABLE);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('dataid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('fieldid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field(self::OWNER, XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('set_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('dataid_fk', XMLDB_KEY_FOREIGN, ['dataid'], 'user_info_data', ['id']);
        $table->add_index('dataid_setid_uix', XMLDB_INDEX_UNIQUE, ['dataid', 'set_id']);
        $table->add_index('fieldid_userid_ix', XMLDB_INDEX_NOTUNIQUE, ['fieldid', self::OWNER]);

        return $table;
    }

    /**
     * Create the temporary projection table.
     *
     * @return void
     */
    public static function create_table(): void {
        global $DB;

        $DB->get_manager()->create_temp_table(self::table_definition());
    }

    /**
     * Drop the temporary projection table when it exists.
     *
     * @return void
     */
    public static function drop_table(): void {
        global $DB;

        $table = self::table_definition();
        if ($DB->get_manager()->table_exists($table)) {
            $DB->get_manager()->drop_table($table);
        }
    }

    /**
     * Store one canonical record, as an adapter's own write would.
     *
     * @param int $fieldid
     * @param int $ownerid
     * @param string $data The raw payload.
     * @return int The new record's id.
     */
    public function add(int $fieldid, int $ownerid, string $data): int {
        $id = $this->nextid++;
        $this->records[$id] = (object)['id' => $id, 'fieldid' => $fieldid, 'ownerid' => $ownerid, 'data' => $data];

        return $id;
    }

    /**
     * The plugin that owns the data.
     *
     * @return string
     */
    public function component(): string {
        return 'local_repeatable';
    }

    /**
     * The temporary projection table, owned by userid.
     *
     * @return store
     */
    public function store(): store {
        return new store(self::TABLE, self::OWNER);
    }

    /**
     * The schema a test registered for a field.
     *
     * @param int $fieldid
     * @return schema|null
     */
    public function schema(int $fieldid): ?schema {
        return $this->schemas[$fieldid] ?? null;
    }

    /**
     * One canonical record, read as it is now.
     *
     * @param int $dataid
     * @return \stdClass|null {id, fieldid, ownerid, data}, or null when it is gone.
     */
    public function canonical(int $dataid): ?\stdClass {
        return isset($this->records[$dataid]) ? clone $this->records[$dataid] : null;
    }

    /**
     * Every canonical record of one field, in id order.
     *
     * @param int $fieldid
     * @return \moodle_recordset Records (id, fieldid, ownerid, data).
     */
    public function canonical_recordset(int $fieldid): \moodle_recordset {
        $records = [];
        foreach ($this->records as $id => $record) {
            if ((int)$record->fieldid === $fieldid) {
                $records[$id] = clone $record;
            }
        }
        ksort($records);

        return new class (array_values($records)) extends \moodle_recordset {
            /** @var \stdClass[] The records, in order. */
            private array $records;

            /** @var int Position of the current record. */
            private int $position = 0;

            /**
             * Constructor.
             *
             * @param \stdClass[] $records The records, in order.
             */
            public function __construct(array $records) {
                $this->records = $records;
            }

            /**
             * The current record.
             *
             * @return mixed
             */
            public function current(): mixed {
                return $this->records[$this->position];
            }

            /**
             * The current record's id.
             *
             * @return mixed
             */
            public function key(): mixed {
                return (int)$this->records[$this->position]->id;
            }

            /**
             * Move to the next record.
             *
             * @return void
             */
            public function next(): void {
                $this->position++;
            }

            /**
             * Whether a record is current.
             *
             * @return bool
             */
            public function valid(): bool {
                return isset($this->records[$this->position]);
            }

            /**
             * Release the records.
             *
             * @return void
             */
            public function close() {
                $this->records = [];
            }
        };
    }

    /**
     * The projection rows of one owner's value of one field.
     *
     * @param int $fieldid
     * @param int $ownerid
     * @return \stdClass[] Rows (id, set_id, data, timemodified) in set order.
     */
    public function projection_rows(int $fieldid, int $ownerid): array {
        global $DB;

        return array_values($DB->get_records(
            self::TABLE,
            ['fieldid' => $fieldid, self::OWNER => $ownerid],
            'set_id ASC, id ASC',
            'id, set_id, data, timemodified'
        ));
    }
}
