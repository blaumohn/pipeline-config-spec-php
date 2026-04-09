# Config Pipeline Spec (PHP)

This repository provides a PHP implementation of a pipeline/phase based config spec.
It includes a YAML loader, manifest validation, and config-source checks.

## Concepts

- **Pipeline**: project flow (e.g. `dev`, `smoketest`, `delivery`)
- **Phase**: lifecycle step (e.g. `setup`, `build`, `runtime`, `deploy`)

## Config file order

1) `config/common.yaml` (optional)
2) `config/<PIPELINE>.yaml`
3) `.local/<PIPELINE>.yaml`
4) `config/<PIPELINE>-<PHASE>.yaml`
5) `.local/<PIPELINE>-<PHASE>.yaml`

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
- `PIPELINE` and `PHASE` are injected internally by the library and do not
  belong in the app manifest.

## API (language-agnostic)

- **Inputs**: `pipeline`, `phase`
- **YAML loader**: loads context-scoped config files
- **Manifest**: parses and expands group references
- **Validation**: checks phase rules, disjointness, and sources
- **Compiler**: produces a validated config snapshot

## PHP usage

```php
use PipelineConfigSpec\PipelineConfigService;

$configService = new PipelineConfigService($rootPath);
$configService->compile('dev', 'runtime');
```

Optional: custom config dir (default is `config/`):

```php
$configService = new PipelineConfigService($rootPath, 'src/resources/config');
```
