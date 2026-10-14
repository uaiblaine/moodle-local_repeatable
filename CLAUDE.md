# Claude instructions for `local_repeatable`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin, as rules
with a one-line reason each; keep it under ~250 lines, because every session
and every subagent pays for it on every cache write. Measurements, incidents
and the long version of a gotcha go to `docs/` (export-ignored) with a pointer
from the rule.

Plugin context: a Moodle **local** plugin ("Repeatable sets engine"), the library behind
"repeatable sets" values (any number of sets, each a row of typed columns). It owns **no tables,
settings, capabilities, events, tasks or web services**, and its privacy provider is a
`null_provider`. The plugins that store values are adapters: `profilefield_repeatable` today,
`customfield_repeatable` later. The extraction plan, stage by stage, is
`moodle-profilefield_repeatable/docs/engine-extraction-design.md`; this repository holds what has
moved so far (stages B1-B2: schema, payload, SQL, bridges, stored values, privacy exporter, validator;
C1-C2: edit widget, column editor, display renderer, templates, styles, AMD modules; C3: projection
writer, Report Builder entity). **One branch per Moodle version**: `MOODLE_405_STABLE`, `MOODLE_501_STABLE`,
`MOODLE_502_STABLE` (= `main`) and `MOODLE_503_STABLE`, MATURITY_STABLE, releases
`v<major>.<minor>-r<N>`; the other three live in worktrees `~/dev/moodle-local_repeatable-405`,
`-501` and `-503`. Each branch's `version.php` sets `requires` and `supported` to its own Moodle
version, and its `ci.yml` has one moodle-an-hochschulen job for that core branch (full PHP x DB
matrix) with the companions of the same branch — **keep `version.php` and `ci.yml` per branch on a
merge**. Development happens on `main` and the m502 stack. Mounted at `local/repeatable` on the stacks `$plugin->supported` names
(`~/dev/moodle-dev/plugins.conf`, `auto`).

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-local_repeatable --branch MOODLE_502_STABLE --php 8.4 --only phpcs,phpdoc   # quick gate
mdl ci moodle-local_repeatable --matrix                 # every CI leg locally: the pre-merge gate
mdl phpunit m502 local_repeatable                       # targeted tests
# Mutation sweep: absolute spec path, and BOTH database families.
mdl mutate moodle-local_repeatable /Users/uaiblaine/dev/moodle-local_repeatable/mutations/gates.conf --fast --db pgsql
mdl mutate moodle-local_repeatable /Users/uaiblaine/dev/moodle-local_repeatable/mutations/gates.conf --fast --db mariadb
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs `mdl ci moodle-local_repeatable --matrix` before merging: push a branch, open
the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

`mutations/gates.conf` breaks one guard per gate and names the test that must redden. The tags are
the ones the gates had in `profilefield_repeatable`, so sweep logs of the two repositories compare.

## Code layout

```
classes/local/schema.php           column schema v2: parse, validate(), keys, report identifiers, accessors
classes/local/payload.php          MAX_SETS, MAX_VALUE_LENGTH, normalise(), column_value(), encode()
classes/local/sql.php              is_postgres(), subitem_value(), column_value() (COALESCE over a legacy label)
classes/local/stored_values.php    keys_in_use() over the raw payloads an adapter hands in
classes/local/validator.php        raw_error(), error(), for_storage(), for_editing(); stored payload + can-read closure + companion flags come from the adapter
classes/local/bridge/dictionaries.php  soft dependency on local_dictionaries (probes, domains, fold_code)
classes/local/bridge/pattern.php   soft dependency on customfield_pattern (the one door to its api)
classes/privacy/set_exporter.php   one stored payload as a privacy export writes it
classes/privacy/provider.php       null_provider
classes/form/widget.php            edit widget: template context, columns JSON, labels for stored codes, js_call_amd
classes/form/column_editor.php     definition editor: textarea attributes, canonical JSON, schema errors, locked keys
classes/output/sets_renderer.php   display: accordion or plain list, label prefetch, pattern formatting
classes/local/projection/          store (adapter's table + owner column), source (adapter contract), projector (sync, is_current, reproject_*)
classes/reportbuilder/local/entities/sets.php  abstract entity: one row per set; subclass gives table, field id, schema, availability;
                                                an adapter whose projection rows span several areas narrows filter options in filter_options_scope()
templates/                         edit_form, display_accordion, display_plain (double stashes)
styles.css                         every rule under a local-repeatable-* class (no Bootstrap 5 utility)
amd/src/                           repeatable.js (widget), markup.js, dictionary_transport.js, columneditor.js, displayaccordion.js
tests/generator/lib.php            schema_json(): a v2 schema from labels or column arrays
tests/fixtures/projection_source.php  test_source: in-memory canonical records + a temporary projection table
```

## Versions

`main` numbers in the 5.2 namespace (`20260420XX`), counter per branch; the release branches start at
counter 00 of their own namespace (fleet rule). The engine has no upgrade steps and no `install.xml`.
Core installs `local` plugins after every other type, so no adapter's upgrade or install step may
rely on engine DB state (there is none) — only on engine classes, which autoload from disk.

## Architecture gotchas

### Engine and adapter boundary

- **The engine never calls profile-field or `core_customfield` APIs, never reads an adapter's
  table other than the projection table the adapter names (`projection\store`, the `sets`
  constructor), and never decides who may see or edit a value.** The adapter reads its own
  canonical rows and passes the payloads, the stored (never the submitted) payload and every
  authorisation decision as arguments: the security decisions stay next to the API that knows whose
  data it is.
- `stored_values::keys_in_use()` takes an iterable of raw payloads, never a field id: the adapter
  reads them from its canonical store (never the projection, which a direct write can leave stale)
  and closes its own recordset.
- A string an engine class prints comes from `local_repeatable`; an adapter's own strings
  (`pluginname`, privacy, tasks, datasources) stay in the adapter. A moved string keeps its key.

### UI

- The widget, column editor and renderer take every decision as an argument (can-read closure,
  stored codes, lookup and pattern availability, locked keys, the sync-rows callable): the adapter
  owns the profile, course or other context. `amd/build/**` ships with `amd/src/**` and a version
  bump; run `mdl grunt m502 local/repeatable` after every `amd/src` edit.
- `sets_renderer` dates a set through `projector::canonical_set()`, the string the projector compares
  rows by: a second copy would let the two drift and a set lose its sync time.
- Stylesheet rules sit under `local-repeatable-*` classes only, never a bare Bootstrap class, and
  use no Bootstrap 5 utility (`tests/bootstrap_compat_test.php` enforces it): the UI renders inside
  core pages on 4.5 and 5.x where the plugin owns no ancestor to gate on.

### Schema and payloads

- `schema::validate()` is the only gate; the constructor normalises and never rejects. A stored
  schema is re-checked on read: `from_json()` drops a column with a bad or colliding key.
- Payloads and Report Builder identifiers are keyed by column key, never by label. A key is
  `[a-z][a-z0-9_]{0,39}`; `schema::generate_key()` and the column editor's JS `generateKey()` must
  stay in step.
- Legacy labels (`with_legacy_labels()`) let a payload not yet re-keyed from version 1 read like its
  re-keyed form: `payload::normalise()` and `sql::column_value()` (COALESCE) read either shape, and a
  JSON null under a key counts as absent in both. Any new reader goes through these two.
- `payload::normalise()` truncates silently (`MAX_SETS`, `MAX_VALUE_LENGTH`): a caller that must
  reject excess checks the raw JSON first.
- `sql::subitem_value()` has forms for PostgreSQL and MySQL/MariaDB only: a Report Builder source
  built on it limits itself to those families.

### Soft dependencies

- No `dependencies` in `version.php`. Every call to `local_dictionaries` goes through
  `local\bridge\dictionaries` and every call to `customfield_pattern` through `local\bridge\pattern`,
  each guarded by `class_exists()`/`method_exists()`. `bridge\pattern::available()` also requires
  `api::CONFIG_VERSION === pattern::CONFIG_VERSION`, because another version may read a stored
  setting differently.
- `dictionaries::DOMAIN_PATTERN` is the fallback of the companion's `resolver::DOMAIN_PATTERN`, and a
  test pins them equal; `fold_code()` mirrors the companion's internal code key.
- Without customfield_pattern, `validate()` accepts a pattern column only if the stored field has it
  with the same `pattern` object (strict `===` after a recursive key sort, never `==`).

## Testing notes

- Schemas come from the generator's `schema_json()`. The engine owns no table: SQL tests run on a
  temporary table (`create_temp_table()`), dropped in `tearDown()`.
- Projector and entity tests use `tests/fixtures/projection_source.php`: `test_source` keeps the
  canonical records in memory and creates a temporary table with the shape of
  `{profilefield_repeatable_data}`, including the unique (dataid, set_id) index PostgreSQL's
  `ON CONFLICT` names. `sync_test` proves the upsert on it; each family runs one branch, so judge the
  projector from both. Entity gates are held on column and filter objects (`format_value()`,
  `get_is_available()`, `get_options()`); saved-report identifiers and the adapter's
  `is_available()` stay the adapter's tests.
- Tests that need a companion skip without it, and those describing its absence skip with it; CI
  installs both companions, so the absence tests run only on a stack without them.
- `tests/privacy/provider_test.php` asserts `component_is_compliant()`: plugin CI never runs core's
  compliance sweep.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
