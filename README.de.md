# pipeline-config-spec (PHP)

Deutsch | [English](README.md)

PHP-Umsetzung der pipeline/phase-basierten Config-Spec.

## Was es ist
<small>*Doku: [Spec: Pipeline-Spec-System](https://docs.template.ysdani.com/de/specs/systeme/pipeline-spec/)*</small>

> Lädt, validiert und kompiliert YAML-basierte Konfiguration entlang von
> Pipelines und Phasen. Sprachneutrale Spec: [SPEC.md](SPEC.md)

---

## PHP-Nutzung
<small>*Doku: [Bereich: pipeline-config-spec-php](https://docs.template.ysdani.com/de/areas/pipeline-config-spec-php/)*</small>

> ```php
> use PipelineConfigSpec\PipelineConfig;
>
> $config = new PipelineConfig($rootPath);
> $config->compile('dev', 'runtime');
> ```
>
> Eigenes Config-Verzeichnis (Default: `pipeline-config/`):
>
> ```php
> $config = new PipelineConfig($rootPath, 'src/resources/pipeline-config');
> ```

---
