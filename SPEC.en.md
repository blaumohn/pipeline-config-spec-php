# Pipeline Config Spec

[Deutsch](SPEC.md) | English

This spec describes the rules of the `pipeline-config-spec-php` library:
how configuration values are resolved, merged, and validated.
The PHP library is the authoritative implementation of this spec.

---

## Resolution Rules

### R-01 File Structure

YAML configuration files are group-based. The top level contains groups
from `variable-groups`; variables are nested beneath them.

```yaml
smtp:
  SMTP_FROM_EMAIL: contact@example.test
sftp:
  SFTP_PORT: "22"
```

### R-02 Resolution Order

Four layers are merged in ascending priority. Higher layers override lower ones.

| Priority       | Layer                            | Source Type |
|----------------|----------------------------------|-------------|
| 1 (lowest)     | Manifest default                 | `default`   |
| 2              | `pipeline-config/<pipeline>.yaml` | `file`     |
| 3              | `.local/<pipeline>.yaml`         | `local`     |
| 4 (highest)    | CLI overrides                    | `cli`       |

### R-03 Merge Rule

Per variable, the value from the last applied layer wins.
The source entry is carried over together with the value.

### R-04 Missing File

If `pipeline-config/<pipeline>.yaml` or `.local/<pipeline>.yaml` is absent,
the file is silently skipped. No error is raised.
Resolution continues with the remaining layers.

### R-05 Source Type Detection

The stored source value is classified after the fact:

| Value                        | Detected Type |
|------------------------------|---------------|
| `'cli'`                      | `cli`         |
| `'default'`                  | `default`     |
| Path containing `/.local/`   | `local`       |
| Any other non-empty path     | `file`        |
| Empty string                 | `unknown`     |

---

## Validation Rules

Validation inspects the fully resolved snapshot of all phases of a pipeline
before any value is returned or any file is written.

### V-01 Required Presence

Every variable required by any phase of the pipeline must be present
in the snapshot.

Error: `Fehlende Pflicht-Variable: <VAR>`

### V-02 No Empty String

A present value must not be the empty string `""`.

Error: `Leerer Wert nicht erlaubt: <VAR>`

### V-03 Source Policy

If `sources` is set for a variable, the detected source type of the
provided value must appear in the allowed list.

Error: `Variable in falscher Quelle: <VAR> (<source>, erlaubt: <policy>)`

### V-04 No Superfluous Variables

The snapshot must not contain variables that are not defined for the
pipeline in the manifest.

Error: `Überflüssige Variable: <VAR>`

### V-05 Manifest Default Bypasses Source Policy

A value filled in by a manifest default (source type `default`) is
always valid — regardless of any `sources` restriction.
Defaults are considered authorised by the manifest.

---

## Manifest Rules

### M-01 Required Sections

`manifest.yaml` must contain the three sections `variable-groups`,
`phases`, and `pipelines`.

### M-02 Group Structure

Each variable in a group may have optional fields:

- `sources` — allowed source types: `cli`, `local`, `file`
- `default` — fallback value (source type `default`)
- `meta` — documentation field, has no effect on resolution:
  - `desc` — short description of the variable
  - `notes` — extended notes
  - `example` — example value

### M-03 Phase Rules and Pipeline Overrides

Phase assignments determine which variables of a group are active:

- `*` — all variables in the group
- `[VAR1, VAR2]` — explicit subset

Pipeline-specific phase rules under `pipelines.<pipeline>.<phase>`
add to the shared rules from `phases.<phase>` additively.
The variable sets of both levels are united.

### M-04 Disjointness

The same variable must not appear simultaneously in `phases.<phase>`
and `pipelines.<pipeline>.<phase>`. Overlaps are detected during resolution.

Error: `Disjunktheitsverletzung: <VAR> in common.<phase> und <pipeline>.<phase>`

### M-05 Pipeline Name `common` Forbidden

The name `common` is reserved as an internal identifier and must not
be used as a pipeline name in the manifest.
