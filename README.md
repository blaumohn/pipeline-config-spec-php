# Config Pipeline Spec (PHP)

This repository provides a PHP implementation of a pipeline/phase based config spec.
It includes a YAML loader, manifest validation, and config-source checks.

## Concepts

- **Pipeline**: project flow (e.g. `dev`, `smoketest`, `delivery`)
- **Phase**: lifecycle step (e.g. `setup`, `build`, `runtime`, `deploy`)
- **Pipeline phase**: the pair of `pipeline` and `phase`, for example
  `dev/runtime`
- **Config variable**: functional key within a pipeline phase, for example
  `APP_URL`

## Config file order

1) `config/<PHASE>.yaml`
2) `.local/<PHASE>.yaml`
3) `config/<PIPELINE>-<PHASE>.yaml`
4) `.local/<PIPELINE>-<PHASE>.yaml`

## Manifest format

`config/config.manifest.yaml`:

```yaml
variable-groups:
  app:
    APP_URL:
      meta:
        desc: "Base URL of the application"
        example: "https://example.invalid"
  mail:
    SMTP_PASS:
      sources: [system, local]

phases:
  setup: {}

  runtime:
    app:
      - APP_URL
    mail: "*"

pipelines:
  dev: {}
```

- `variable-groups` defines groups, keys, `meta`, and optional `sources`.
- `phases` defines valid phase names and shared group references.
- `pipelines` defines valid pipeline names and pipeline-specific additions.
- A group reference uses `group: "*"` for the whole group or
  `group: [KEY, ...]` for an explicit subset.
- A known empty phase such as `setup` is valid without variables.
- `meta.notes` may document functional dependencies between variables.
- `PIPELINE` and `PHASE` are derived internally from the pipeline phase and do
  not belong in the app manifest.

## API (language-agnostic)

- **Inputs**: `pipeline`, `phase`
- **Pipeline-phase validation**: `pipeline` must exist in `pipelines`,
  `phase` must exist in `phases`
- **YAML loader**: loads context-scoped config files
- **Manifest**: parses and expands group references for a valid pipeline phase
- **Validation**: checks config variables of the valid pipeline phase,
  disjointness, and `sources`
- **Compiler**: produces a validated config snapshot
- **Compiled output**: writes a structured `config.php` with
  `pipeline_phase` and `values`

CLI overrides are treated like regular config variables. An override is only
valid when the key exists for the current pipeline phase and its `sources`
allow CLI sources.

## PHP usage

```php
use PipelineConfigSpec\PipelineConfigService;

$configService = new PipelineConfigService($rootPath);
$configService->compile('dev', 'runtime');
```

`compile()` writes a structured payload:

```php
return [
    'pipeline_phase' => [
        'pipeline' => 'dev',
        'phase' => 'runtime',
    ],
    'values' => [
        'APP_URL' => 'https://example.test',
    ],
];
```

This keeps the pipeline phase clearly separated from config variables inside
one compiled file. `describe()` still uses the report key `context`.

Optional: custom config dir (default is `config/`):

```php
$configService = new PipelineConfigService($rootPath, 'src/resources/config');
```
