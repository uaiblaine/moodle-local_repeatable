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

namespace local_repeatable\local\bridge;

/**
 * The probes of the companion dictionary plugin, local_dictionaries, a soft dependency.
 *
 * The engine declares no dependency on it: every touchpoint guards with class_exists() or
 * method_exists(), so the engine installs, validates and renders without the companion, and
 * only its public API is read, never its tables.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class dictionaries {
    /**
     * Fallback domain shortname pattern of dictionary columns.
     *
     * Used only while the companion `local_dictionaries` is absent; {@see self::domain_pattern()} reads the
     * companion's own pattern when it is installed, and a test pins the two to the same value.
     */
    public const DOMAIN_PATTERN = '/^[a-z0-9_]+$/';

    /**
     * The domain shortname pattern in force: the companion's when installed, else the fallback.
     *
     * @return string A preg pattern.
     */
    public static function domain_pattern(): string {
        if (class_exists('\local_dictionaries\resolver')) {
            return \local_dictionaries\resolver::DOMAIN_PATTERN;
        }
        return self::DOMAIN_PATTERN;
    }

    /**
     * The companion's domains for the column editor, or null when the companion is absent.
     *
     * Read through \local_dictionaries\api::list_domains(), which runs no capability check: only
     * the adapters' definition forms, each behind its own configuration capability, call this. A
     * companion too old to have that method, or one that fails, gives an empty list; the editor
     * then asks for the shortname as text.
     *
     * @return array|null List of ['shortname', 'name' (plain), 'itemcount', 'visibility', 'parentdomain'].
     */
    public static function domains(): ?array {
        if (!class_exists('\local_dictionaries\resolver')) {
            return null;
        }
        if (!method_exists('\local_dictionaries\api', 'list_domains')) {
            return [];
        }

        try {
            $records = \local_dictionaries\api::list_domains();
        } catch (\Throwable $e) {
            return [];
        }

        $context = \core\context\system::instance();
        $domains = [];
        foreach ($records as $record) {
            $record = (array)$record;
            $domains[] = [
                'shortname' => (string)($record['shortname'] ?? ''),
                'name' => format_string((string)($record['name'] ?? ''), true, ['context' => $context, 'escape' => false]),
                'itemcount' => (int)($record['itemcount'] ?? 0),
                'visibility' => (int)($record['visibility'] ?? 0),
                'parentdomain' => isset($record['parentdomain']) ? (string)$record['parentdomain'] : null,
            ];
        }

        return $domains;
    }

    /**
     * Whether the companion offers what dictionary columns need on the edit form.
     *
     * Stage B of the schema needs \local_dictionaries\api::lookup() (server-side validation),
     * api::can_read() (who may search a dictionary) and the search service behind its autocomplete
     * transport. Without them
     * dictionary columns are typed as text and their codes are not checked.
     *
     * @return bool
     */
    public static function lookup_available(): bool {
        return class_exists('\local_dictionaries\api')
            && method_exists('\local_dictionaries\api', 'lookup')
            && method_exists('\local_dictionaries\api', 'can_read');
    }

    /**
     * Fold a code for comparison: trimmed, accents removed, lower case.
     *
     * Mirrors the companion's code key (\local_dictionaries\local\keys::code_key(), internal to
     * it), so two spellings the companion reads as one code compare equal here too.
     *
     * @param string $code
     * @return string
     */
    public static function fold_code(string $code): string {
        $code = trim($code);
        $decomposed = \Normalizer::normalize($code, \Normalizer::FORM_D);
        if (is_string($decomposed)) {
            $code = preg_replace('/\p{Mn}+/u', '', $decomposed) ?? $decomposed;
        }
        return \core_text::strtolower($code);
    }
}
