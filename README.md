# Config Pipeline Spec (PHP)

This repository provides a PHP implementation of a pipeline/phase based config spec.
It includes a YAML loader, manifest validation, and runtime policy checks.

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
variables:
  app:
    APP_URL: {}
    APP_ENV: {}
  secrets:
    IP_SALT:
      sources: [system, local]

pipelines:
  common:
    runtime:
      required: [PIPELINE, PHASE, APP_ENV, IP_SALT]
      allowed: [app, secrets]
  dev:
    runtime:
      required: []
      allowed: []
```

- `variables` groups keys and optional `sources` policies.
- `pipelines` defines `allowed`/`required` per phase.
- `allowed` accepts group names or literal keys (wildcards allowed).

## API (language-agnostic)

- **Inputs**: `pipeline`, `phase`
- **YAML loader**: loads context-scoped config files
- **Manifest**: parses and expands groups/wildcards
- **Policy**: validates allowed/required and source rules
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
