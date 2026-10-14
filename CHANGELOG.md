# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [v5.3-r1] - 2026-10-14

Moodle 5.3 branch (`MOODLE_503_STABLE`): version `2026100500`, release `v5.3-r1`,
`MATURITY_STABLE`, `$plugin->supported = [503, 503]`, CI against core `MOODLE_503_STABLE`.

First release on the one-branch-per-Moodle-version layout and the first `MATURITY_STABLE` one:
`MOODLE_405_STABLE` (`v4.5-r1`), `MOODLE_501_STABLE` (`v5.1-r1`), `MOODLE_502_STABLE` (`v5.2-r1`,
also `main`) and `MOODLE_503_STABLE` (`v5.3-r1`). Each branch numbers itself in its Moodle version's
namespace (4.5 `20241007XX`, 5.1 `20251006XX`, 5.2 `20260420XX`, 5.3 `20261005XX`), declares
`$plugin->supported` for that version only, and runs CI against its own core branch with the full
PHP x database matrix, installing `local_dictionaries` from its matching branch and
`customfield_pattern` (Moodle 5.1 and later) from its one branch, `main`. The code is the same on
every branch. `main` keeps its development number, `2026042004`, which the adapters already
require; the other branches start at counter 00.

### Changed

- `reportbuilder\local\entities\sets`: the name-filter options are built by an instance method and
  read an overridable `filter_options_scope()` (default: no extra condition, same options as
  before), so an adapter whose projection rows span several areas lists only its own area's codes.

### Added

- The engine's library half, moved from `profilefield_repeatable` (stage B1 of that plugin's
  `docs/engine-extraction-design.md`) with only namespace, component and class renames:
  - `local\schema`: the version 2 column schema (parse, `validate()`, keys, report identifiers,
    accessors, legacy-label aliases).
  - `local\payload`: the caps, `normalise()`, `column_value()` and `encode()` of a stored payload.
  - `local\sql`: the cross-database JSON readers used by Report Builder, and `quote_identifier()`.
  - `local\bridge\dictionaries` and `local\bridge\pattern`: the soft dependencies on
    `local_dictionaries` and `customfield_pattern`, each guarded by `class_exists()`.
  - `local\stored_values::keys_in_use()`, now over the raw payloads its caller reads.
  - `privacy\set_exporter`: one stored payload as a privacy export writes it.
- The field's validator, moved from `profilefield_repeatable` (stage B2) with only namespace,
  component and class renames: `local\validator` (raw limits, required columns, dictionary codes
  with the restricted-dictionary rules, pattern values, and the stored/edited shapes of pattern
  values). The adapter passes the stored payload, a "may read this domain" closure and whether the
  companions count as present; the engine decides nothing from a profile-field API.
- Privacy `null_provider`: the engine stores no personal data.
- The test generator (`schema_json()`), the PHPUnit tests and the mutation gates of the moved code,
  validator included (`validator_test`, `validator_dictionary_test`, `validator_pattern_test`).
- The field's user interface, moved from `profilefield_repeatable` (stage C1) with only namespace,
  component, template, CSS class prefix and file-location renames:
  - `form\widget`: the edit table, the columns its AMD module reads and the labels of the codes a
    payload holds (labels of an unreadable domain only for the stored codes).
  - `form\column_editor`: the attributes, shapes and validation of a field definition's schema
    textarea.
  - `output\sets_renderer`: one stored payload as an accordion or a plain list. It compares a set
    with its projection row through the projector's `canonical_set()` (moved in stage C3).
  - The templates `edit_form`, `display_accordion` and `display_plain`, and `styles.css`, with every
    `profilefield-repeatable-*` class renamed `local-repeatable-*`.
  - The strings they print (`actioncolumn`, `addnewset`, `newcode`, `removeset`,
    `searchplaceholder`, `setcolumn`, `synchronisedat`), copied unchanged in English and Portuguese.
  - Their tests (`widget_test`, `column_editor_test`, `sets_renderer_test`, written against the
    engine classes from the field's form-level tests), `bootstrap_compat_test`, and the gates
    ES1-ES5, X1, D1, K1, B10, B11, B18, PT5, PT12 and PT13.
- The field's JavaScript, moved from `profilefield_repeatable` (stage C2) with only component,
  module path and CSS class prefix renames: `local_repeatable/repeatable` (the edit widget),
  `local_repeatable/markup`, `local_repeatable/dictionary_transport` (the only module that imports
  `local_dictionaries`, loaded by the widget only for a searched column),
  `local_repeatable/columneditor` (it enhances `textarea[data-region="local-repeatable-schema"]`)
  and `local_repeatable/displayaccordion`, built in `amd/build`. The strings they read (the column
  editor's and the dictionary search's) are copied unchanged in English and Portuguese.
- The field's projection writer and Report Builder entity, moved from `profilefield_repeatable`
  (stage C3) with only namespace and component renames:
  - `local\projection\store` (an adapter's projection table and owner column),
    `local\projection\source` (what an adapter provides: its canonical records, schema and projection
    rows) and `local\projection\projector` (`sync()`, `is_current()`, `canonical_set()`,
    `reproject_field()`, `write_stale()`, `reproject_record()`, and the row upsert: PostgreSQL
    `ON CONFLICT` on the unique (dataid, set_id) index, select-then-write elsewhere).
  - `reportbuilder\local\entities\sets`: the abstract entity of one field as one row per set; the
    adapter's subclass gives the projection table, the field id, the schema and the availability.
  - The strings the entity prints (`columnsubitem`, `columnsubitemcode`, `columnsubitemname`,
    `entityrepeatableset`, `setnumber`), copied unchanged in English and Portuguese.
  - Their tests: `projection_test` (the field's projector tests plus the engine side of its
    `reproject_sets_test`) and `sync_test`, on a temporary projection table with the shape of
    `{profilefield_repeatable_data}` and an in-memory test source (`tests/fixtures/projection_source.php`);
    `sets_test`, on the entity's column and filter objects. Gates J1-J3, J6-J9, E1-E6, R1 and PT6.
