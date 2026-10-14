moodle-local_repeatable
=======================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-local_repeatable/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-local_repeatable/actions/workflows/ci.yml?query=branch%3Amain)

The shared engine of "repeatable sets": a field value made of any number of sets, each set a row of
typed columns (free text, dictionary codes, masked patterns).

This plugin is a library. It has no pages, settings, capabilities, tables or tasks of its own, and
does nothing on a site by itself: its widgets render inside the pages of the plugins that store
repeatable values, which use it:

- `profilefield_repeatable` (the "Repeatable set" user profile field);
- `customfield_repeatable` (planned: the same value as a custom field of courses, cohorts and groups).

Each of those plugins is an *adapter*: it owns the stored values, decides who may see and edit them,
and calls the engine for everything that does not depend on where the values live. The engine owns:

- the column schema (version 2): columns with a key, a label and a mode, its validation rules and
  the Report Builder identifiers each column produces;
- the stored payload: its caps, the normalisation every reader goes through, and its encoding;
- the SQL that reads one column of a set's JSON on PostgreSQL and MySQL/MariaDB;
- the bridges to the two optional companion plugins below;
- the privacy export of one stored payload, which each adapter's provider writes;
- the edit widget (the table of sets and the labels of the codes it holds), the column editor of a
  field's definition, and the display of a stored value as an accordion or a plain list, with their
  templates, styles and JavaScript modules;
- the projection writer, which keeps an adapter's per-set rows (the table Report Builder lists) a
  projection of its canonical payloads, and the abstract Report Builder entity over those rows.


Requirements
------------

- Moodle 5.1 (branch `MOODLE_501_STABLE`; releases `v5.1-rN`), PHP 8.2 or later. Other Moodle
  versions have their own branches.
- PostgreSQL 15+, MySQL 8.4+ or MariaDB 10.11+, as Moodle 5.1 requires.
- Optional: [local_dictionaries](https://github.com/uaiblaine/moodle-local_dictionaries), for
  dictionary columns (code lists with labels). Without it, dictionary columns are typed as text and
  their codes are not checked.
- Optional: [customfield_pattern](https://github.com/uaiblaine/moodle-customfield_pattern) (Moodle 5.1
  or later), for pattern columns (masks and regular expressions). Without it, no new pattern column can
  be added and stored pattern values are kept and shown as typed.

Neither companion is a declared dependency: every call to them checks that they are installed.


Installation
------------

Install the plugin like any other plugin to folder `/local/repeatable`. Install it together with, or
before, a plugin that uses it.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

There is nothing to configure.


Privacy
-------

The engine stores no personal data. The plugins that use it declare, export and delete the values
they store.


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2026 Anderson Blaine
