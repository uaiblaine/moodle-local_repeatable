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
 * What a field's stored payloads hold, read from the canonical payloads the adapter hands in.
 *
 * Never from the projection: a canonical payload can be written without rebuilding the
 * projection (core's profile_save_custom_fields() does so for profile fields), so a projection
 * can miss values that exist. The adapter reads its own canonical rows and passes their payloads.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stored_values {
    /**
     * Which column keys hold a non-blank value in some set of one field, and how many owners have sets.
     *
     * One pass over the field's payloads, each read as the field reads it
     * ({@see payload::normalise()}: under the key or its legacy label). The definition
     * form locks these keys in the editor and rejects a schema that drops one.
     *
     * @param iterable $payloads The field's raw stored payloads (JSON strings), one per owner; the
     *                           caller opens and closes any recordset behind them.
     * @param schema $schema The field's stored schema.
     * @return array ['users' => int owners with at least one set, 'keys' => string[] the keys in use, in schema order].
     */
    public static function keys_in_use(iterable $payloads, schema $schema): array {
        $inuse = [];
        $users = 0;
        foreach ($payloads as $rawpayload) {
            $payload = payload::normalise($rawpayload, $schema);
            if (!empty($payload)) {
                $users++;
            }
            foreach ($payload as $set) {
                foreach ($set as $key => $value) {
                    if (trim($value) !== '') {
                        $inuse[$key] = true;
                    }
                }
            }
        }

        return [
            'users' => $users,
            'keys' => array_values(array_filter($schema->keys(), static fn(string $key): bool => isset($inuse[$key]))),
        ];
    }
}
