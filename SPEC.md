# Pipeline-Config-Spec

Deutsch | [English](SPEC.en.md)

Diese Spec beschreibt die Regeln der `pipeline-config-spec-php`-Bibliothek:
wie Konfigurationswerte aufgelöst, zusammengeführt und validiert werden.
Die PHP-Bibliothek ist die maßgebliche Umsetzung dieser Spec.

---

## Auflösungsregeln

### R-01 Dateistruktur

YAML-Konfigurationsdateien sind gruppenbasiert. Die oberste Ebene enthält
Gruppen aus `variable-groups`; darunter stehen die Variablen.

```yaml
smtp:
  SMTP_FROM_EMAIL: kontakt@example.test
sftp:
  SFTP_PORT: "22"
```

### R-02 Auflösungsreihenfolge

Vier Schichten werden in aufsteigender Priorität zusammengeführt.
Höhere Schichten überschreiben niedrigere.

| Priorität | Schicht                          | Quellen-Typ |
|-----------|----------------------------------|-------------|
| 1 (niedrigste) | Manifest-Default            | `default`   |
| 2         | `pipeline-config/<pipeline>.yaml` | `file`     |
| 3         | `.local/<pipeline>.yaml`         | `local`     |
| 4 (höchste) | CLI-Overrides                  | `cli`       |

### R-03 Merge-Regel

Pro Variable gewinnt der Wert der zuletzt angewendeten Schicht.
Der Quellen-Eintrag (`sources`) wird gemeinsam mit dem Wert übernommen.

### R-04 Fehlende Datei

Fehlt `pipeline-config/<pipeline>.yaml` oder `.local/<pipeline>.yaml`,
wird die Datei stillschweigend übersprungen. Es wird kein Fehler ausgelöst.
Die Auflösung fährt mit den verbleibenden Schichten fort.

### R-05 Quellen-Typ-Erkennung

Der gespeicherte Quellen-Wert wird nachträglich klassifiziert:

| Wert                     | Erkannter Typ |
|--------------------------|---------------|
| `'cli'`                  | `cli`         |
| `'default'`              | `default`     |
| Pfad mit `/.local/`      | `local`       |
| sonstiger nicht-leerer Pfad | `file`     |
| leer                     | `unknown`     |

---

## Validierungsregeln

Die Validierung prüft den vollständig aufgelösten Snapshot aller Phasen
einer Pipeline, bevor ein Wert zurückgegeben oder eine Datei geschrieben wird.

### V-01 Pflicht-Präsenz

Jede Variable, die in irgendeiner Phase der Pipeline gefordert ist, muss
im Snapshot vorhanden sein.

Fehlermeldung: `Fehlende Pflicht-Variable: <VAR>`

### V-02 Kein Leerstring

Ein vorhandener Wert darf nicht der leere String `""` sein.

Fehlermeldung: `Leerer Wert nicht erlaubt: <VAR>`

### V-03 Quellenregel

Ist `sources` für eine Variable gesetzt, muss der erkannte Quellen-Typ
des tatsächlich gelieferten Wertes in der erlaubten Liste stehen.

Fehlermeldung: `Variable in falscher Quelle: <VAR> (<source>, erlaubt: <policy>)`

### V-04 Keine überflüssigen Variablen

Der Snapshot darf keine Variablen enthalten, die nicht im Manifest der
Pipeline definiert sind.

Fehlermeldung: `Überflüssige Variable: <VAR>`

### V-05 Manifest-Default umgeht Quellenregel

Ein Wert, der durch einen Manifest-Default eingesetzt wurde (Quellen-Typ
`default`), ist immer gültig — unabhängig von einer `sources`-Einschränkung.
Defaults gelten als vom Manifest autorisiert.

---

## Manifest-Regeln

### M-01 Pflichtabschnitte

`manifest.yaml` muss die drei Abschnitte `variable-groups`, `phases`
und `pipelines` enthalten.

### M-02 Gruppenstruktur

Jede Variable in einer Gruppe kann optionale Felder enthalten:

- `sources` — erlaubte Quellen-Typen: `cli`, `local`, `file`
- `default` — Standardwert als Fallback (Quellen-Typ `default`)
- `meta` — Dokumentationsfeld, hat keinen Einfluss auf die Auflösung:
  - `desc` — kurze Beschreibung der Variable
  - `notes` — ausführliche Hinweise
  - `example` — Beispielwert

### M-03 Phasenregeln und Pipeline-Overrides

Phasenzuweisungen bestimmen, welche Variablen einer Gruppe aktiv sind:

- `*` — alle Variablen der Gruppe
- `[VAR1, VAR2]` — Teilauswahl

Pipeline-spezifische Phasenregeln unter `pipelines.<pipeline>.<phase>`
ergänzen die gemeinsamen Regeln aus `phases.<phase>` additiv.
Die Variable-Mengen beider Ebenen werden vereinigt.

### M-04 Disjunktheit

Dieselbe Variable darf nicht gleichzeitig in `phases.<phase>` und
`pipelines.<pipeline>.<phase>` stehen. Überlappungen werden beim
Auflösen erkannt.

Fehlermeldung: `Disjunktheitsverletzung: <VAR> in common.<phase> und <pipeline>.<phase>`

### M-05 Pipeline-Name `common` verboten

Der Name `common` ist als interner Bezeichner reserviert und darf nicht
als Pipeline-Name im Manifest verwendet werden.
