# Config Pipeline Spec (PHP)

Dieses Repository enthält eine PHP-Implementierung einer Pipeline/Phase-basierten Config-Spec.
Es umfasst YAML-Laden, Manifest-Validierung und Quellen-Prüfungen.

## Begriffe

- **Pipeline**: Projektfluss (z. B. `dev`, `smoketest`, `delivery`)
- **Phase**: Lebenszyklus-Schritt (z. B. `setup`, `build`, `runtime`, `deploy`)
- **Pipeline-Phase**: das Paar aus `pipeline` und `phase`, z. B. `dev/runtime`
- **Konfig-Variable**: fachlicher Schlüssel innerhalb einer Pipeline-Phase,
  z. B. `APP_URL`

## Config-Reihenfolge

1) `config/<PHASE>.yaml`
2) `.local/<PHASE>.yaml`
3) `config/<PIPELINE>-<PHASE>.yaml`
4) `.local/<PIPELINE>-<PHASE>.yaml`

## Manifest-Format

`config/config.manifest.yaml`:

```yaml
variable-groups:
  app:
    APP_URL:
      meta:
        desc: "Basis-URL der Anwendung"
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

- `variable-groups` definiert Gruppen, Keys, `meta` und optionale
  `sources`-Regeln.
- `phases` definiert gültige Phasennamen und gemeinsame Gruppenreferenzen.
- `pipelines` definiert gültige Pipeline-Namen und pipelinespezifische
  Ergänzungen.
- Eine Gruppenreferenz nutzt `gruppe: "*"` für die ganze Gruppe oder
  `gruppe: [KEY, ...]` für eine explizite Teilmenge.
- Eine bekannte leere Phase wie `setup` ist ohne Variablen gültig.
- `meta.notes` kann fachliche Abhängigkeiten zwischen Variablen dokumentieren.
- `PIPELINE` und `PHASE` werden nur lib-intern aus der Pipeline-Phase
  abgeleitet und stehen nicht im App-Manifest.

## Config-Werte und Beispielwerte

Config-Dateien enthalten wirksame Werte für eine Pipeline-Phase. Sie sind
keine Ablage für Beispielwerte.

Beispielwerte gehören in `variable-groups.<gruppe>.<key>.meta.example` im
Manifest. Sie erklären einen möglichen Wert, ersetzen aber keine notwendige
Config-Quelle.

Wenn eine Pipeline-Phase einen Wert braucht und weder eine Config-Datei noch
eine erlaubte lokale, System- oder CLI-Quelle ihn liefert, schlägt die
Validierung fehl. So bleiben fehlende Instanzwerte in CI/CD sichtbar.

## API (sprachunabhängig)

- **Inputs**: `pipeline`, `phase`
- **Pipeline-Phase-Validierung**: `pipeline` muss in `pipelines`, `phase`
  muss in `phases` stehen
- **YAML-Loader**: lädt kontextbezogene Config-Dateien
- **Manifest**: expandiert Gruppen-Referenzen für eine gültige
  Pipeline-Phase
- **Validierung**: prüft Konfig-Variablen der gültigen Pipeline-Phase,
  Disjunktheit und `sources`
- **Compiler**: erzeugt ein validiertes Config-Snapshot
- **Kompilatausgabe**: schreibt ein strukturiertes `config.php` mit
  `pipeline_phase` und `values`

CLI-Overrides werden wie normale Konfig-Variablen behandelt. Ein Override ist
nur gültig, wenn der Schlüssel in der aktuellen Pipeline-Phase vorkommt und
seine `sources` CLI-Quellen erlauben.
Overrides werden als verschachtelte Mappings übergeben und in dieser
Spezifitätsreihenfolge aufgelöst: `<phase>`, dann `<pipeline>.<phase>`.

```json
{
  "deploy": {
    "ftp": {
      "FTP_PASS": "generic-secret"
    }
  },
  "preview": {
    "deploy": {
      "ftp": {
        "FTP_PASS": "specific-secret"
      }
    }
  }
}
```

## PHP-Beispiel

```php
use PipelineConfigSpec\PipelineConfigService;

$configService = new PipelineConfigService($rootPath);
$configService->compile('dev', 'runtime');
```

`compile()` schreibt ein strukturiertes Kompilat:

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

Die Pipeline-Phase bleibt damit im Kompilat klar von den Konfig-Variablen
getrennt. `describe()` verwendet weiterhin den Report-Schlüssel `context`.

Optional: eigenes Config-Verzeichnis (Default ist `config/`):

```php
$configService = new PipelineConfigService($rootPath, 'src/resources/config');
```
