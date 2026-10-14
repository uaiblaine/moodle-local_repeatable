# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

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
    with its projection row through its own copy of the projector's `canonical_set()`, which has
    not moved yet.
  - The templates `edit_form`, `display_accordion` and `display_plain`, and `styles.css`, with every
    `profilefield-repeatable-*` class renamed `local-repeatable-*`.
  - The strings they print (`actioncolumn`, `addnewset`, `newcode`, `removeset`,
    `searchplaceholder`, `setcolumn`, `synchronisedat`), copied unchanged in English and Portuguese.
  - Their tests (`widget_test`, `column_editor_test`, `sets_renderer_test`, written against the
    engine classes from the field's form-level tests), `bootstrap_compat_test`, and the gates
    ES1-ES5, X1, D1, K1, B10, B11, B18, PT5, PT12 and PT13.
