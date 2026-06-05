# pipeline-config-spec (PHP)

[Deutsch](README.de.md) | English

PHP implementation of the pipeline/phase-based config spec.

## What it is
<small>*Docs: [Pipeline-Spec System](https://docs.template.ysdani.com/de/specs/systeme/pipeline-spec/)*</small>

> Loads, validates, and compiles YAML-based configuration along pipelines
> and phases. Language-agnostic spec: [SPEC.md](SPEC.md)

---

## PHP usage
<small>*Docs: [Area: pipeline-config-spec-php](https://docs.template.ysdani.com/de/areas/pipeline-config-spec-php/)*</small>

> ```php
> use PipelineConfigSpec\PipelineConfig;
>
> $config = new PipelineConfig($rootPath);
> $config->compile('dev', 'runtime');
> ```
>
> Custom config dir (default: `pipeline-config/`):
>
> ```php
> $config = new PipelineConfig($rootPath, 'src/resources/pipeline-config');
> ```

---
