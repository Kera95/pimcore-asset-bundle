<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Service\Assets;

use DateTimeImmutable;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\Concrete;
use Pimcore\Model\Document;
use Pimcore\Model\Element\ElementInterface;
use Pimcore\Model\Element\Service as ElementService;
use Psr\Log\LoggerInterface;

final class PathResolver
{
    private const MISSING_CHARACTER = '_';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Builds the target folder path for the given asset, or null when a token could not be resolved
     *
     * @param string $pattern
     * @param ElementInterface $element
     * @param Asset $asset
     * @return string|null
     */
    public function resolve(string $pattern, ElementInterface $element, Asset $asset): ?string
    {
        $unresolved = null;

        $path = preg_replace_callback(
            PathTokens::TOKEN_PATTERN,
            function (array $matches) use ($element, $asset, &$unresolved): string {
                $value = $this->resolveToken($matches[1], $matches[2] ?? null, $element, $asset);

                if ($value === null || $value === '') {
                    $unresolved ??= $matches[0];

                    return '';
                }

                return $value;
            },
            $pattern
        );

        if ($unresolved !== null) {
            $this->logger->warning(
                sprintf(
                    'Skipped asset sorting for asset %d: token "%s" of pattern "%s" resolved to an empty value.',
                    $asset->getId(),
                    $unresolved,
                    $pattern
                )
            );

            return null;
        }

        return $this->sanitize($path);
    }

    /**
     * Resolves a single token to its value, or null when the token is unknown or not applicable
     *
     * @param string $token
     * @param string|null $argument
     * @param ElementInterface $element
     * @param Asset $asset
     * @return string|null
     */
    private function resolveToken(string $token, ?string $argument, ElementInterface $element, Asset $asset): ?string
    {
        $basename = pathinfo($asset->getFilename(), PATHINFO_FILENAME);

        switch ($token) {
            case PathTokens::ID:
                return (string) $element->getId();

            case PathTokens::KEY:
                return $element->getKey();

            case PathTokens::TYPE:
                return ElementService::getElementType($element);

            case PathTokens::CLASS_NAME:
                return $element instanceof Concrete ? $element->getClassName() : null;

            case PathTokens::DOCUMENT_TYPE:
                return $element instanceof Document ? $element->getType() : null;

            case PathTokens::FIELD:
                return $this->resolveFieldValue($element, $argument);

            case PathTokens::FILENAME:
                return $asset->getFilename();

            case PathTokens::BASENAME:
                return $basename;

            case PathTokens::EXTENSION:
                return pathinfo($asset->getFilename(), PATHINFO_EXTENSION);

            case PathTokens::ASSET_TYPE:
                return $asset->getType();

            case PathTokens::CHAR:
                return $this->resolveCharacter($basename, $argument);

            case PathTokens::DATE:
                return (new DateTimeImmutable())->format($argument ?: 'Y-m-d');
        }

        return null;
    }

    /**
     * Reads a DataObject field and casts it to a path segment
     *
     * @param ElementInterface $element
     * @param string|null $fieldName
     * @return string|null
     */
    private function resolveFieldValue(ElementInterface $element, ?string $fieldName): ?string
    {
        if (!$element instanceof Concrete || !$fieldName) {
            return null;
        }

        $value = $element->get($fieldName);

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Returns the nth character of the filename, falling back to a placeholder for short names
     *
     * @param string $basename
     * @param string|null $position
     * @return string
     */
    private function resolveCharacter(string $basename, ?string $position): string
    {
        $offset = max(1, (int) $position) - 1;
        $character = mb_substr($basename, $offset, 1);

        return $character === '' ? self::MISSING_CHARACTER : mb_strtolower($character);
    }

    /**
     * Drops empty segments and turns every remaining one into a valid asset key
     *
     * @param string $path
     * @return string
     */
    private function sanitize(string $path): string
    {
        $segments = array_map(
            static fn (string $segment): string => ElementService::getValidKey($segment, 'asset'),
            explode('/', $path)
        );

        $segments = array_filter($segments, static fn (string $segment): bool => $segment !== '');

        return '/' . implode('/', $segments);
    }
}
