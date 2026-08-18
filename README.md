# TsfAssetBundle

[![Codeception](https://github.com/Kera95/pimcore-asset-bundle/actions/workflows/codeception.yml/badge.svg)](https://github.com/Kera95/pimcore-asset-bundle/actions/workflows/codeception.yml)

Automatically sort assets referenced by DataObjects and Documents in
[Pimcore](https://pimcore.com/) 11.x, 12.x, and 2026.x.

- Composer package: `kerimkaralic/pimcore-asset-bundle`
- Namespace: `Tsf\AssetBundle`
- Bundle class: `Tsf\AssetBundle\TsfAssetBundle`
- Config alias: `tsf_asset`
- License: MIT

## Requirements

| | |
|---|---|
| PHP | >=8.1, <8.6 |
| Pimcore | ^11.0, ^12.0, or ^2026.1 |
| Symfony | ^6.2 or ^7.3 |

The effective PHP and Symfony versions are determined by the selected Pimcore line:

| Pimcore | PHP supported by Pimcore | Symfony supported by Pimcore |
|---|---|---|
| 11.x | 8.1–8.3 | 6.2–6.4 |
| 12.x | 8.3–8.4 | 6.4 or 7.3–7.4 |
| 2026.x | 8.4–8.5 | 7.4 |

The bundle uses Pimcore model events and has no dependency on either the Classic Admin or Studio UI,
so the same sorting implementation is used on all supported lines.

## Installation

```bash
composer require kerimkaralic/pimcore-asset-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Tsf\AssetBundle\TsfAssetBundle::class => ['all' => true],
];
```

Then install it:

```bash
bin/console pimcore:bundle:install TsfAssetBundle
```

The package declares `extra.pimcore.bundles`, so Pimcore discovers it as an available
`pimcore-bundle`; `config/bundles.php` is what actually enables it in the kernel.

## What it does

When a DataObject or a Pimcore Document is saved, every asset it references is moved into a folder
structure generated from a configurable path pattern. Assets already sitting in their target folder
are left alone, so re-saving an element is a no-op.

## Configuration

All options live under the `tsf_asset` key. Run `bin/console debug:config tsf_asset` for the
resolved configuration. A ready-to-copy version is available in
[`docs/configuration.example.yaml`](./docs/configuration.example.yaml).

```yaml
# config/packages/tsf_asset.yaml
tsf_asset:
    sorting:
        data_objects:
            enabled: true                                    # master switch, default true
            path: '/{class}/{char:1}/{char:2}'               # fallback for classes without a rule
            asset_types:                                     # optional, per asset type override
                document: '/datasheets/{class}'
            classes:                                         # one rule per DataObject class
                Product:
                    enabled: true                            # default true, set false to skip the class
                    path: '/product/{field:sku}/{char:1}/{char:2}'
                    asset_types:
                        document: '/product/{field:sku}/datasheets'
        documents:
            enabled: false                                   # master switch, default false
            path: '/documents/{doctype}/{key}'
            asset_types:
                video: '/documents/media/{key}'
```

When a switch is `false` the listener returns immediately and the save continues untouched.

### Partial and environment overrides

Symfony merges repeated bundle configuration, so an override only needs to contain the keys that
change. For example, disable DataObject sorting in development without repeating any paths:

```yaml
# config/packages/dev/tsf_asset.yaml
tsf_asset:
    sorting:
        data_objects:
            enabled: false
```

Or retarget only `Product` while preserving the other class and asset-type rules:

```yaml
tsf_asset:
    sorting:
        data_objects:
            classes:
                Product:
                    path: '/catalog/{field:sku}/{asset_type}'
```

### Per class rules

`sorting.data_objects.classes` is keyed by DataObject class name. Classes are runtime data in
Pimcore, so they cannot be enumerated at compile time — list only the classes that need their own
rule and everything else falls back to `sorting.data_objects.path`. A class rule inherits the
section `path` when its own `path` is `null`, and its `asset_types` are merged over the section
ones.

### Path tokens

Patterns must be absolute. Unknown tokens are rejected when the container is compiled, so a typo
fails fast instead of silently producing a wrong folder. Run `bin/console tsf:asset:path-tokens`
for this table:

| Token | Resolves to |
|---|---|
| `{id}` | Id of the saved element |
| `{key}` | Key of the saved element |
| `{type}` | `object` or `document` |
| `{class}` | DataObject class name, empty for documents |
| `{doctype}` | Document type such as `page` or `snippet`, empty for objects |
| `{field:NAME}` | Value of a DataObject field, e.g. `{field:sku}` |
| `{filename}` | Asset filename including the extension |
| `{basename}` | Asset filename without the extension |
| `{extension}` | Asset extension without the dot |
| `{asset_type}` | Asset type: `image`, `document`, `video`, `audio`, `text`, `archive` |
| `{char:N}` | Nth character of the asset filename, lowercased, `_` when the name is shorter |
| `{date:FORMAT}` | Current date in the given PHP date format, e.g. `{date:Y/m}` |

A token that resolves to an empty value aborts the move for that asset and logs a warning — an
object with no SKU will not dump its images into a shared folder. `{char:N}` is exempt and falls
back to `_`. Every resolved segment is passed through `Element\Service::getValidKey()`, and
segments that sanitise to nothing are dropped.

### Asset type overrides

`asset_types` is keyed by the value of `Asset::getType()`, so a PDF (`Pimcore\Model\Asset\Document`,
type `document`) can land somewhere different from an image referenced by the same element. Falls
back to `path` for any type not listed.

## What gets collected

From DataObjects — every field value that is or contains an `Asset`: single asset relations, image
galleries, hotspot images, advanced relations carrying `ElementMetadata`, and plain arrays of any
of those. Asset folders are ignored.

From Documents — the `image`, `video`, `pdf`, `relation` and `relations` editables of any
`PageSnippet`, including editables nested inside areablocks and blocks. Documents without editables
(links, folders, hardlinks) are skipped.

Sorting runs on `postAdd` and `preUpdate` for both element types, so a newly created element is
sorted on its first save.

Not traversed: localized fields, field collections and object bricks. Their container values are
returned by `get()` but are not unwrapped, so assets nested inside them are left where they are.

## Layout

```
composer.json
LICENSE
config/services.yaml               # DI: autowire/autoconfigure over src/
docs/configuration.example.yaml     # ready-to-copy project configuration and override examples
src/TsfAssetBundle.php             # bundle class (Pimcore\Extension\Bundle\AbstractPimcoreBundle)
src/Installer.php                  # SettingsStoreAwareInstaller — install/uninstall support
src/Command/
  ListPathTokensCommand.php        # tsf:asset:path-tokens
src/DependencyInjection/
  TsfAssetExtension.php            # loads config/services.yaml, sets %tsf_asset.config%
  Configuration.php                # semantic config tree, validates path patterns
src/EventListener/
  DataObjectListener.php           # pimcore.dataobject.postAdd + preUpdate
  DocumentListener.php             # pimcore.document.postAdd + preUpdate
src/Model/
  SortingRule.php                  # resolved path pattern + asset type overrides
src/Service/Assets/
  AssetStructureSorter.php         # collects the assets and moves them
  PathResolver.php                 # turns a pattern into a sanitised folder path
  PathTokens.php                   # token catalogue, shared by config info and command
src/Service/Config/
  SortingConfiguration.php         # picks the rule for an element
codeception.dist.yml               # test runner configuration
tests/
  _bootstrap.php                   # autoloader + Pimcore kernel stub, no database
  Support/                         # test doubles and factories
  Unit/                            # unit suite, mirrors src/
```

Everything under `src/` is auto-registered as a private, autowired service. `Installer` is excluded
from that sweep and declared explicitly as public — Pimcore fetches it from the container.

## Extending

- **Services / listeners / subscribers / commands**: drop the class in `src/`; autoconfigure tags it.
  No YAML edit needed for `EventSubscriberInterface`, `Command`, etc.
- **Controllers**: add `src/Controller/`, then ship a `config/routes.yaml` and load it from the
  extension (or have the consuming project import it).
- **Config options**: extend `Configuration::getConfigTreeBuilder()`.
- **Migrations**: add `src/Migrations/`, then return the last migration class name from
  `Installer::getLastMigrationVersionClassName()` so install/uninstall marks them correctly.

## Local development inside a Pimcore project

The bundle is consumed through a composer **path repository** so the working copy is symlinked into
`vendor/`:

```json
"repositories": [
    { "type": "path", "url": "bundles/Tsf/AssetBundle" }
],
"require": {
    "kerimkaralic/pimcore-asset-bundle": "@dev"
}
```

`vendor/kerimkaralic/pimcore-asset-bundle` is a symlink to the working copy, so edits apply immediately —
only `bin/console cache:clear` is needed. Adding new classes needs no `dump-autoload`, since the
package's own PSR-4 mapping covers `src/`.

## Tests

Codeception, mirroring how the Pimcore core bundles are tested. The unit suite needs no database,
no OpenSearch and no booted Pimcore kernel — `tests/_bootstrap.php` installs a stub kernel that
only provides the event dispatcher `Pimcore\Model\Element\Service` reaches for.

Standalone:

```bash
composer install
vendor/bin/codecept run
```

The GitHub Actions workflow runs the same command against Pimcore 11.x, 12.x, and 2026.x.

From inside a Pimcore project that consumes the bundle as a path package (no `composer install`
in the bundle needed — the bootstrap falls back to the project autoloader):

```bash
docker compose exec -T -w /var/www/html/bundles/Tsf/AssetBundle php \
    php /var/www/html/vendor/bin/codecept run Unit
```

What is covered:

| Class | Covered |
|---|---|
| `PathTokens` | token catalogue, unknown and malformed token detection |
| `PathResolver` | every token, the unresolved-token bail-out and its log line, key sanitising |
| `SortingRule` | fallback path and per asset type overrides |
| `SortingConfiguration` | section and class rules, disable switches, asset type inheritance |
| `Configuration` | defaults, merging, and every path pattern validation error |
| `TsfAssetExtension` | services, parameters, listener tags, injected sorting sub-tree |
| `DataObject`/`DocumentListener` | delegation and the folder / non-`PageSnippet` guards |
| `AssetStructureSorter` | which assets get collected from fields, editables and value objects |

Not covered by the unit suite: `AssetStructureSorter::moveAsset()` and `getUniqueFilename()`, which
go through `Pimcore\Model\Asset\Service::createFolderByPath()` and `Asset::save()` and therefore
need a real installation. Those belong in a functional suite run against a test database.

## Versioning

`getVersion()` reads the installed version from composer metadata, so it reports whatever git tag
was resolved (`dev-main` while developing from a path repository). Tag releases with `vX.Y.Z`.

## Commands

```bash
bin/console pimcore:bundle:list
bin/console pimcore:bundle:install TsfAssetBundle
bin/console pimcore:bundle:uninstall TsfAssetBundle
bin/console debug:config tsf_asset          # resolved configuration
bin/console tsf:asset:path-tokens           # tokens usable in a path pattern
```

In this Docker-based project prefix each with `docker compose exec -T php php`.

## License

MIT — see [LICENSE](./LICENSE).
