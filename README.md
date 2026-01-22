# Config Pipeline Spec (PHP)

This repository provides a PHP implementation of a pipeline/phase based config spec.
It includes a Dotenv loader, manifest validation, and runtime policy checks.

## Concepts

- **Pipeline**: project flow (e.g. `dev`, `smoketest`, `delivery`)
- **Phase**: lifecycle step (e.g. `setup`, `build`, `runtime`, `deploy`)

## Dotenv file order

1) `.env`
2) `.env.local`
3) `.env.<PIPELINE>`
4) `.env.<PIPELINE>.local`
5) `.env.<PIPELINE>.<PHASE>`
6) `.env.<PIPELINE>.<PHASE>.local`

## Manifest format

`config/env.manifest.yaml`:

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

- **Context**: `pipeline`, `phase`
- **Dotenv loader**: loads context-scoped config files
- **Manifest**: parses and expands groups/wildcards
- **Policy**: validates allowed/required and source rules
- **Compiler**: produces a validated config snapshot

## PHP usage

```php
use ConfigPipelineSpec\Config\ConfigCompiler;

$compiler = new ConfigCompiler($rootPath);
$context = $compiler->resolveContext([
    'pipeline' => 'dev',
    'phase' => 'runtime',
]);
$compiler->compile($context, false);
```
