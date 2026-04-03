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

`config/config.manifest.yaml`:

```yaml
variables:
  app:
    APP_URL:
      meta:
        desc: "Basis-URL der Anwendung"
        example: "https://example.invalid"
  mail:
    SMTP_PASS:
      sources: [system, local]

pipelines:
  common:
    runtime:
      app:
        - APP_URL
      mail:
        - SMTP_PASS
  dev:
    runtime: {}
```

- `variables` gruppiert Keys und optionale `sources`-Regeln.
- `pipelines` definiert pro Phase direkte Gruppen-Referenzen.
- Phasenregeln nutzen `pipelines.<pipeline>.<phase>.<group>`.
- Ein Gruppenwert ist entweder `*` oder eine explizite Schlüssel-Liste.
- `PIPELINE` und `PHASE` werden lib-intern ergänzt und stehen nicht im
  App-Manifest.

## API (sprachunabhaengig)

- **Inputs**: `pipeline`, `phase`
- **YAML-Loader**: laedt kontextbezogene Config-Dateien
- **Manifest**: expandiert Gruppen/Wildcards
- **Policy**: prueft Phasenregeln, Disjunktheit und sources
- **Compiler**: erzeugt ein validiertes Config-Snapshot

## PHP-Beispiel

```php
use PipelineConfigSpec\PipelineConfigService;

$configService = new PipelineConfigService($rootPath);
$configService->compile('dev', 'runtime');
```

Optional: eigenes Config-Verzeichnis (Default ist `config/`):

```php
$configService = new PipelineConfigService($rootPath, 'src/resources/config');
```
