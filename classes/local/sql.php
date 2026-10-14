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
 * SQL builders reading a set's JSON, and the database-family helpers they need.
 *
 * Only PostgreSQL and MySQL/MariaDB have a form here: a Report Builder source built on these
 * builders limits itself to those two families.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sql {
    /**
     * Check if the active DB family is PostgreSQL.
     *
     * @param \moodle_database $db
     * @return bool
     */
    public static function is_postgres(\moodle_database $db): bool {
        return $db->get_dbfamily() === 'postgres';
    }

    /**
     * Build a cross-DB SQL expression extracting one column's text value from a set's JSON.
     *
     * PostgreSQL casts the text column to jsonb and reads the value with the ->> operator; MySQL/MariaDB use
     * JSON_UNQUOTE(JSON_EXTRACT(...)) with a quoted JSON path. The key is bound as a parameter
     * on both drivers.
     *
     * @param \moodle_database $db
     * @param string $columnsql Aliased reference to the JSON column, e.g. "alias.data".
     * @param string $subitem Column key to extract.
     * @param string $paramname Unique bound-parameter name to use for the key.
     * @return array Two-element list: the SQL expression string and its bound parameters.
     */
    public static function subitem_value(
        \moodle_database $db,
        string $columnsql,
        string $subitem,
        string $paramname
    ): array {
        if (static::is_postgres($db)) {
            return ["(($columnsql)::jsonb ->> :$paramname)", [$paramname => $subitem]];
        }

        $path = '$."' . str_replace(['\\', '"'], ['\\\\', '\\"'], $subitem) . '"';
        return ["JSON_UNQUOTE(JSON_EXTRACT($columnsql, :$paramname))", [$paramname => $path]];
    }

    /**
     * SQL reading one column of a set's JSON, also under its legacy label while it has one.
     *
     * A field whose payloads are still being re-keyed has projection rows keyed by label
     * ({@see schema::legacy_labels()}); COALESCE reads either shape, as the PHP readers do.
     *
     * @param \moodle_database $db
     * @param string $columnsql Aliased reference to the JSON column, e.g. "alias.data".
     * @param schema $schema
     * @param string $key Column key.
     * @param string $paramname Bound-parameter name for the key.
     * @param string $legacyparamname Bound-parameter name for the legacy label, used only when there is one.
     * @return array Two-element list: the SQL expression string and its bound parameters.
     */
    public static function column_value(
        \moodle_database $db,
        string $columnsql,
        schema $schema,
        string $key,
        string $paramname,
        string $legacyparamname
    ): array {
        [$sql, $params] = self::subitem_value($db, $columnsql, $key, $paramname);
        $legacy = $schema->legacy_labels()[$key] ?? null;
        if ($legacy === null) {
            return [$sql, $params];
        }

        [$legacysql, $legacyparams] = self::subitem_value($db, $columnsql, $legacy, $legacyparamname);
        return ["COALESCE({$sql}, {$legacysql})", $params + $legacyparams];
    }

    /**
     * Quote a SQL identifier for PostgreSQL.
     *
     * For DDL, which takes no bound parameters: the profile adapter's upgrade step that removes its
     * r1 index layer is the one caller. DML stays on placeholders.
     *
     * @param string $identifier
     * @return string
     * @throws \coding_exception When the identifier contains a NUL byte.
     */
    public static function quote_identifier(string $identifier): string {
        if (strpos($identifier, "\0") !== false) {
            throw new \coding_exception('Invalid SQL identifier provided.');
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
