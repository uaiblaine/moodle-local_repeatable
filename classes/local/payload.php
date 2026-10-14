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
 * The stored payload of a repeatable value: caps, normalisation and encoding.
 *
 * A payload is a JSON object of sets, each mapping a column key to its value. Every reader of a
 * stored payload goes through {@see self::normalise()}, so a payload not yet re-keyed from
 * version 1 labels reads like its re-keyed form ({@see schema::legacy_labels()}).
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class payload {
    /** Maximum number of sets accepted per field value. */
    public const MAX_SETS = 200;

    /** Maximum length of one sub-item value in characters. */
    public const MAX_VALUE_LENGTH = 1024;

    /**
     * Normalise a repeatable payload to the columns of a schema.
     *
     * Returns the sets keyed '@@ID#1', '@@ID#2', ... in input order, each holding every column key
     * as a string. A value is read under the column key, or, when the set has no such key, under
     * the column's legacy label ({@see schema::legacy_labels()}): a version 1 payload reads
     * the same as its re-keyed form. Silently drops sets with no non-blank value, keeps only the
     * first MAX_SETS remaining sets and truncates values to MAX_VALUE_LENGTH: callers that must
     * reject excess check the raw payload first
     * ({@see validator::raw_error()}).
     *
     * @param mixed $rawpayload JSON string, object or array; anything else yields [].
     * @param schema $schema
     * @return array
     */
    public static function normalise($rawpayload, schema $schema): array {
        $keys = $schema->keys();
        if (empty($keys)) {
            return [];
        }
        $legacy = $schema->legacy_labels();

        if (is_string($rawpayload)) {
            $rawpayload = trim($rawpayload);
            if ($rawpayload === '') {
                return [];
            }

            $decoded = json_decode($rawpayload, true);
        } else if (is_object($rawpayload)) {
            $decoded = json_decode(json_encode($rawpayload), true);
        } else if (is_array($rawpayload)) {
            $decoded = $rawpayload;
        } else {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalised = [];
        $counter = 1;

        foreach ($decoded as $setdata) {
            if ($counter > self::MAX_SETS) {
                break;
            }

            if (is_object($setdata)) {
                $setdata = json_decode(json_encode($setdata), true);
            }
            if (!is_array($setdata)) {
                continue;
            }

            $set = [];
            $hasvalue = false;
            foreach ($keys as $key) {
                $value = self::column_value($setdata, $key, $legacy[$key] ?? null) ?? '';
                if (!is_scalar($value)) {
                    $value = '';
                }

                $value = (string)$value;
                if (\core_text::strlen($value) > self::MAX_VALUE_LENGTH) {
                    $value = \core_text::substr($value, 0, self::MAX_VALUE_LENGTH);
                }
                if (trim($value) !== '') {
                    $hasvalue = true;
                }
                $set[$key] = $value;
            }

            if ($hasvalue) {
                $normalised['@@ID#' . $counter] = $set;
                $counter++;
            }
        }

        return $normalised;
    }

    /**
     * The raw value of one column in a decoded set: under the key, else under its legacy label.
     *
     * A JSON null counts as absent, as the SQL reader treats it ({@see sql::column_value()}:
     * PostgreSQL's ->> gives NULL for it, which COALESCE passes over).
     *
     * @param array $set Decoded set.
     * @param string $key Column key.
     * @param string|null $legacylabel The key's legacy label, if any.
     * @return mixed|null Null when the set holds no value for the column.
     */
    public static function column_value(array $set, string $key, ?string $legacylabel) {
        if (isset($set[$key])) {
            return $set[$key];
        }
        if ($legacylabel !== null && isset($set[$legacylabel])) {
            return $set[$legacylabel];
        }
        return null;
    }

    /**
     * Encode payload as strict JSON object.
     *
     * @param array $payload
     * @return string JSON, or '{}' when the payload is empty or cannot be encoded.
     */
    public static function encode(array $payload): string {
        if (empty($payload)) {
            return '{}';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
}
