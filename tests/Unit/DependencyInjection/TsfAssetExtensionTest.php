<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Tests\Unit\DependencyInjection;

use Codeception\Test\Unit;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tsf\AssetBundle\Command\ListPathTokensCommand;
use Tsf\AssetBundle\DependencyInjection\Configuration;
use Tsf\AssetBundle\DependencyInjection\TsfAssetExtension;
use Tsf\AssetBundle\EventListener\DataObjectListener;
use Tsf\AssetBundle\EventListener\DocumentListener;
use Tsf\AssetBundle\Installer;
use Tsf\AssetBundle\Model\SortingRule;
use Tsf\AssetBundle\Service\Assets\AssetStructureSorter;
use Tsf\AssetBundle\Service\Assets\PathResolver;
use Tsf\AssetBundle\Service\Config\SortingConfiguration;
use Tsf\AssetBundle\TsfAssetBundle;

final class TsfAssetExtensionTest extends Unit
{
    private ContainerBuilder $container;

    protected function _before(): void
    {
        $this->container = new ContainerBuilder();
    }

    public function testTheServicesFileIsLoaded(): void
    {
        $this->load();

        foreach ([
            AssetStructureSorter::class,
            PathResolver::class,
            SortingConfiguration::class,
            DataObjectListener::class,
            DocumentListener::class,
            ListPathTokensCommand::class,
            Installer::class,
        ] as $id) {
            self::assertTrue($this->container->hasDefinition($id), sprintf('Service %s is not registered.', $id));
        }
    }

    public function testTheProcessedConfigurationIsExposedAsAParameter(): void
    {
        $this->load(['sorting' => ['data_objects' => ['path' => '/products']]]);

        $parameter = $this->container->getParameter('tsf_asset.config');

        self::assertIsArray($parameter);
        self::assertSame('/products', $parameter['sorting']['data_objects']['path']);
    }

    public function testTheSortingConfigurationReceivesTheSortingSubTree(): void
    {
        $this->load(['sorting' => ['documents' => ['enabled' => true]]]);

        $argument = $this->container->getDefinition(SortingConfiguration::class)->getArgument('$config');

        self::assertIsArray($argument);
        self::assertArrayHasKey('data_objects', $argument);
        self::assertArrayHasKey('documents', $argument);
        self::assertArrayNotHasKey('sorting', $argument);
        self::assertTrue($argument['documents']['enabled']);
        self::assertSame(Configuration::DEFAULT_OBJECT_PATH, $argument['data_objects']['path']);
    }

    public function testTheInstallerIsPublicSoTheBundleCanFetchIt(): void
    {
        $this->load();

        self::assertTrue($this->container->getDefinition(Installer::class)->isPublic());
    }

    public function testTheListenersAreTaggedForTheSaveEvents(): void
    {
        $this->load();

        self::assertSame(
            [
                ['name' => 'kernel.event_listener', 'event' => 'pimcore.dataobject.postAdd', 'method' => 'onPostAdd'],
                ['name' => 'kernel.event_listener', 'event' => 'pimcore.dataobject.preUpdate', 'method' => 'onPreUpdate'],
            ],
            $this->tags(DataObjectListener::class)
        );

        self::assertSame(
            [
                ['name' => 'kernel.event_listener', 'event' => 'pimcore.document.postAdd', 'method' => 'onPostAdd'],
                ['name' => 'kernel.event_listener', 'event' => 'pimcore.document.preUpdate', 'method' => 'onPreUpdate'],
            ],
            $this->tags(DocumentListener::class)
        );
    }

    public function testTheExcludedPathsAreNotInstantiableServices(): void
    {
        $this->load();

        // excluded paths end up as abstract placeholders, which the compiler drops again
        self::assertFalse($this->container->hasDefinition(SortingRule::class));
        self::assertFalse($this->container->hasDefinition(TsfAssetExtension::class));

        foreach (['Tsf\\AssetBundle\\Model', 'Tsf\\AssetBundle\\DependencyInjection', TsfAssetBundle::class] as $id) {
            self::assertTrue($this->container->getDefinition($id)->isAbstract(), sprintf('%s should be abstract.', $id));
        }
    }

    public function testAnInvalidPathIsRejectedWhileLoading(): void
    {
        $this->expectExceptionMessage('uses the unknown token "{nope}"');

        $this->load(['sorting' => ['data_objects' => ['path' => '/{nope}']]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = []): void
    {
        (new TsfAssetExtension())->load([$config], $this->container);
    }

    /**
     * Tag attributes of the given service, with the tag name folded back in for readability
     *
     * @param class-string $id
     * @return array<int, array<string, mixed>>
     */
    private function tags(string $id): array
    {
        $tags = [];

        foreach ($this->container->getDefinition($id)->getTags() as $name => $attributes) {
            foreach ($attributes as $attribute) {
                $tags[] = ['name' => $name] + $attribute;
            }
        }

        return $tags;
    }
}
