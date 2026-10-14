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

/**
 * Data generator of local_repeatable: version 2 column schemas for tests.
 *
 * @package    local_repeatable
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_repeatable\local\schema;

/**
 * Builds column schemas; each adapter's own generator creates its fields from them.
 *
 * @package    local_repeatable
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_repeatable_generator extends component_generator_base {
    /**
     * The schema JSON of some columns.
     *
     * A column is a label (a free column) or an array of schema properties; a missing key is
     * generated from the label, a missing mode is "free", and the first column is the title
     * unless one is flagged.
     *
     * @param array $columns
     * @return string
     */
    public function schema_json(array $columns): string {
        $normalised = [];
        $taken = [];
        foreach ($columns as $column) {
            $column = is_string($column) ? ['label' => $column] : $column;
            $column += ['mode' => schema::MODE_FREE];
            if (!isset($column['key'])) {
                $column['key'] = schema::generate_key($column['label'], $taken);
            }
            $taken = array_merge($taken, schema::reserved_identifiers($column['key']));
            $normalised[] = $column;
        }
        if ($normalised && !array_filter(array_column($normalised, 'title'))) {
            $normalised[0]['title'] = true;
        }

        return schema::from_columns($normalised)->to_json();
    }
}
