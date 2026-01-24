# Config Pipeline Spec (PHP)

Dieses Repository enthaelt eine PHP-Implementierung einer Pipeline/Phase-basierten Config-Spec.
Es umfasst YAML-Laden, Manifest-Validierung und Policy-Pruefungen.

## Begriffe

- **Pipeline**: Projektfluss (z. B. `dev`, `smoketest`, `delivery`)
- **Phase**: Lebenszyklus-Schritt (z. B. `setup`, `build`, `runtime`, `deploy`)

## Config-Reihenfolge

1) `config/common.yaml` (optional)
2) `config/<PIPELINE>.yaml`
3) `.local/<PIPELINE>.yaml`
4) `config/<PIPELINE>-<PHASE>.yaml`
5) `.local/<PIPELINE>-<PHASE>.yaml`

## Manifest-Format

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

- `variables` gruppiert Keys und optionale `sources`-Regeln.
- `pipelines` definiert `allowed`/`required` pro Phase.
- `allowed` akzeptiert Gruppennamen oder einzelne Keys (Wildcards erlaubt).

## API (sprachunabhaengig)

- **Context**: `pipeline`, `phase`
- **YAML-Loader**: laedt kontextbezogene Config-Dateien
- **Manifest**: expandiert Gruppen/Wildcards
- **Policy**: prueft allowed/required und sources
- **Compiler**: erzeugt ein validiertes Config-Snapshot

## PHP-Beispiel

```php
use ConfigPipelineSpec\Config\ConfigCompiler;

$compiler = new ConfigCompiler($rootPath);
$context = $compiler->resolveContext([
    'pipeline' => 'dev',
    'phase' => 'runtime',
]);
$compiler->compile($context, false);
```
