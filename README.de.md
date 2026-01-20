# Config Pipeline Spec (PHP)

Dieses Repository enthaelt eine PHP-Implementierung einer Pipeline/Phase-basierten Config-Spec.
Es umfasst Dotenv-Laden, Manifest-Validierung und Policy-Pruefungen.

## Begriffe

- **Pipeline**: Projektfluss (z. B. `dev`, `smoketest`, `delivery`)
- **Phase**: Lebenszyklus-Schritt (z. B. `setup`, `build`, `runtime`, `deploy`)
- **Profil**: optionale Variante (z. B. `dev`, `preview`, `prod`)

## Dotenv-Reihenfolge

1) `.env`
2) `.env.local`
3) `.env.<PIPELINE>`
4) `.env.<PIPELINE>.local`
5) `.env.<PIPELINE>.<PHASE>`
6) `.env.<PIPELINE>.<PHASE>.local`
7) `.env.<PIPELINE>.<PROFILE>` (optional)
8) `.env.<PIPELINE>.<PROFILE>.local` (optional)
9) `.env.<PIPELINE>.<PROFILE>.<PHASE>` (optional)
10) `.env.<PIPELINE>.<PROFILE>.<PHASE>.local` (optional)

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
      required: [PROFILE]
      allowed: []
```

- `variables` gruppiert Keys und optionale `sources`-Regeln.
- `pipelines` definiert `allowed`/`required` pro Phase.
- `allowed` akzeptiert Gruppennamen oder einzelne Keys (Wildcards erlaubt).

## API (sprachunabhaengig)

- **Context**: `pipeline`, `phase`, `profile`
- **Dotenv-Loader**: laedt kontextbezogene Config-Dateien
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
    'profile' => 'dev',
]);
$compiler->compile($context, false);
```
