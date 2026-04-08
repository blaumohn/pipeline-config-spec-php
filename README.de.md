# Config Pipeline Spec (PHP)

Dieses Repository enthaelt eine PHP-Implementierung einer Pipeline/Phase-basierten Config-Spec.
Es umfasst YAML-Laden, Manifest-Validierung und Quellen-Pruefungen.

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
variable-groups:
  - key: app
    variables:
      - key: APP_URL
        meta:
          desc: "Basis-URL der Anwendung"
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

- `variable-groups` gruppiert Keys, `meta` und optionale `sources`-Regeln.
- `pipelines` definiert pro Phase Gruppen-Referenzen.
- Phasenregeln nutzen `pipelines.<pipeline>.<phase>[]`.
- Eine Gruppen-Referenz arbeitet entweder mit `select: "*"` fuer die ganze
  Gruppe oder mit `variables` fuer eine explizite Teilmenge.
- `meta.notes` kann fachliche Abhaengigkeiten zwischen Variablen dokumentieren.
- `PIPELINE` und `PHASE` werden lib-intern ergänzt und stehen nicht im
  App-Manifest.

## API (sprachunabhaengig)

- **Inputs**: `pipeline`, `phase`
- **YAML-Loader**: laedt kontextbezogene Config-Dateien
- **Manifest**: expandiert Gruppen-Referenzen
- **Validierung**: prueft Phasenregeln, Disjunktheit und sources
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
