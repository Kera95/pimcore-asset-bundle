<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Service\Config;

use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Tsf\AssetBundle\Model\SortingRule;

final class SortingConfiguration
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config The tsf_asset.sorting configuration tree
     */
    public function __construct(
        array $config
    ) {
        $this->config = $config;
    }

    /**
     * Returns the rule to apply for the given object, or null when sorting is switched off for it
     *
     * @param Concrete $object
     * @return SortingRule|null
     */
    public function getRuleForObject(Concrete $object): ?SortingRule
    {
        $section = $this->config['data_objects'];

        if (!$section['enabled']) {
            return null;
        }

        $classRule = $section['classes'][$object->getClassName()] ?? null;

        if ($classRule === null) {
            return new SortingRule($section['path'], $section['asset_types']);
        }

        if (!$classRule['enabled']) {
            return null;
        }

        return new SortingRule(
            $classRule['path'] ?? $section['path'],
            $classRule['asset_types'] + $section['asset_types']
        );
    }

    /**
     * Returns the rule to apply for the given document, or null when sorting is switched off for it
     *
     * @param Document $document
     * @return SortingRule|null
     */
    public function getRuleForDocument(Document $document): ?SortingRule
    {
        $section = $this->config['documents'];

        if (!$section['enabled']) {
            return null;
        }

        return new SortingRule($section['path'], $section['asset_types']);
    }
}
