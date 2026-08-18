<?php

declare(strict_types=1);

namespace Tsf\AssetBundle;

use Composer\InstalledVersions;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Tsf\AssetBundle\DependencyInjection\TsfAssetExtension;

use function dirname;

class TsfAssetBundle extends AbstractPimcoreBundle
{
    public function getNiceName(): string
    {
        return 'TSF Asset Bundle';
    }

    public function getDescription(): string
    {
        return 'Moves referenced assets into configurable folder structures when DataObjects and Documents are saved.';
    }

    public function getComposerPackageName(): string
    {
        return 'tsf/pimcore-asset-bundle';
    }

    public function getVersion(): string
    {
        if (!InstalledVersions::isInstalled($this->getComposerPackageName())) {
            return '';
        }

        return ltrim((string) InstalledVersions::getPrettyVersion($this->getComposerPackageName()), 'v');
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return new TsfAssetExtension();
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getInstaller(): Installer
    {
        return $this->container->get(Installer::class);
    }
}
