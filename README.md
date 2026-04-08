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
  - key: app
    variables:
      - key: APP_URL
        meta:
          desc: "Base URL of the application"
          example: "https://example.invalid"
  - key: mail
    variables:
      - key: SMTP_PASS
        sources: [system, local]

pipelines:
  common:
    runtime:
      - group-key: app
        variables:
          - key: APP_URL
      - group-key: mail
        select: "*"
```

- `variable-groups` defines groups, keys, `meta`, and optional `sources`.
- `pipelines` defines group references per phase.
- Phase rules use `pipelines.<pipeline>.<phase>[]`.
- A phase entry references one group via `group-key`.
- A phase entry uses either `select: "*"` for the whole group or
  `variables` for an explicit subset.
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
