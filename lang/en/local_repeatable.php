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
 * Strings for component 'local_repeatable'.
 *
 * @package    local_repeatable
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['errorcolumnslimit'] = 'A field can have at most {$a} columns.';
$string['errorcolumnsrequired'] = 'Add at least one column.';
$string['errordomaininvalid'] = 'Column "{$a}": choose a dictionary (lowercase letters, digits and underscore).';
$string['errordomainmissing'] = 'Dictionary not found in local_dictionaries: {$a}.';
$string['errorkeyconflict'] = 'Two columns produce the report column "{$a}": change one of the keys.';
$string['errorkeyduplicate'] = 'Key used by two columns: {$a}.';
$string['errorkeyinvalid'] =
    'Invalid key "{$a}": start with a lowercase letter, then up to 39 lowercase letters, digits or underscores.';
$string['errorkeylocked'] = 'Columns holding stored values cannot be removed and their keys cannot change: {$a}.';
$string['errorlabelduplicate'] = 'Column name used twice: {$a}.';
$string['errorlabelrequired'] = 'Column {$a} has no name.';
$string['errormodeinvalid'] = 'Column "{$a}": choose free text or a dictionary mode.';
$string['errorparentinvalid'] = 'Column "{$a}" can only depend on an earlier dictionary column.';
$string['errorparentmismatch'] = 'Column "{$a->column}": the parent column does not use "{$a->parentdomain}", the dictionary holding the parents of "{$a->domain}".';
$string['errorpatternconfig'] = 'Column "{$a->column}": {$a->error}';
$string['errorpatternunavailable'] =
    'Column "{$a}": pattern columns need the customfield_pattern plugin (Moodle 5.1 or later). Without it, a stored pattern column is kept unchanged and no new one can be added.';
$string['errorschemainvalid'] = 'The columns are not a valid version 2 schema.';
$string['errorsublinelimit'] = 'At most {$a} columns can be shown in the subline.';
$string['errorsublinetitle'] = 'Column "{$a}" is the title and cannot also be in the subline.';
$string['errortitlecount'] = 'Mark exactly one column as the title of the set.';
$string['pluginname'] = 'Repeatable sets engine';
$string['privacy:metadata'] = 'The Repeatable sets engine stores no personal data: each plugin that uses it stores, exports and deletes its own.';
$string['repeatableset'] = 'Set {$a}';
