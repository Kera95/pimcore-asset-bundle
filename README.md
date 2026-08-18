# TsfAssetBundle

Asset functionality for [Pimcore](https://pimcore.com/) 2026.2+.

- Composer package: `tsf/pimcore-asset-bundle`
- Namespace: `Tsf\AssetBundle`
- Bundle class: `Tsf\AssetBundle\TsfAssetBundle`
- Config alias: `tsf_asset`
- License: MIT

## Requirements

| | |
|---|---|
| PHP | ~8.4 or ~8.5 |
| Pimcore | ^2026.2 |
| Symfony | ^7.4 |

## Installation

```bash
composer require tsf/pimcore-asset-bundle
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

## Configuration

Options are defined in `Tsf\AssetBundle\DependencyInjection\Configuration` and read back as the
`%tsf_asset.config%` container parameter. The tree is currently empty. Once options exist they are
set under a `tsf_asset:` key:

```yaml
# config/config.yaml
tsf_asset:
    # ...
```

Inspect the resolved config with `bin/console debug:config tsf_asset`.

## Layout

```
composer.json
LICENSE
config/services.yaml               # DI: autowire/autoconfigure over src/
src/TsfAssetBundle.php             # bundle class (Pimcore\Extension\Bundle\AbstractPimcoreBundle)
src/Installer.php                  # SettingsStoreAwareInstaller — install/uninstall support
src/DependencyInjection/
  TsfAssetExtension.php            # loads config/services.yaml, sets %tsf_asset.config%
  Configuration.php                # semantic config tree
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
    "tsf/pimcore-asset-bundle": "@dev"
}
```

`vendor/tsf/pimcore-asset-bundle` is a symlink to the working copy, so edits apply immediately —
only `bin/console cache:clear` is needed. Adding new classes needs no `dump-autoload`, since the
package's own PSR-4 mapping covers `src/`.

## Versioning

`getVersion()` reads the installed version from composer metadata, so it reports whatever git tag
was resolved (`dev-main` while developing from a path repository). Tag releases with `vX.Y.Z`.

## Commands

```bash
bin/console pimcore:bundle:list
bin/console pimcore:bundle:install TsfAssetBundle
bin/console pimcore:bundle:uninstall TsfAssetBundle
bin/console debug:config tsf_asset
```

In this Docker-based project prefix each with `docker compose exec -T php php`.

## License

MIT — see [LICENSE](./LICENSE).
