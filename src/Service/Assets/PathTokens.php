<?php

declare(strict_types=1);

namespace Tsf\AssetBundle\Service\Assets;

final class PathTokens
{
    public const TOKEN_PATTERN = '/\{([a-z_]+)(?::([^}]*))?\}/';

    private const BRACED_TOKEN_PATTERN = '/\{([^{}]+)\}/';

    public const ID = 'id';

    public const KEY = 'key';

    public const TYPE = 'type';

    public const CLASS_NAME = 'class';

    public const DOCUMENT_TYPE = 'doctype';

    public const FIELD = 'field';

    public const FILENAME = 'filename';

    public const BASENAME = 'basename';

    public const EXTENSION = 'extension';

    public const ASSET_TYPE = 'asset_type';

    public const CHAR = 'char';

    public const DATE = 'date';

    /**
     * Every token that may be used inside a path pattern, with the hint shown to the editor
     *
     * @return array<string, string>
     */
    public static function getDefinitions(): array
    {
        return [
            self::ID            => 'Id of the saved element. Example: {id}',
            self::KEY           => 'Key of the saved element. Example: {key}',
            self::TYPE          => 'Element type, either "object" or "document". Example: {type}',
            self::CLASS_NAME    => 'DataObject class name, empty for documents. Example: {class}',
            self::DOCUMENT_TYPE => 'Document type such as page or snippet, empty for objects. Example: {doctype}',
            self::FIELD         => 'Value of a DataObject field, empty for documents. Example: {field:sku}',
            self::FILENAME      => 'Asset filename including the extension. Example: {filename}',
            self::BASENAME      => 'Asset filename without the extension. Example: {basename}',
            self::EXTENSION     => 'Asset extension without the dot. Example: {extension}',
            self::ASSET_TYPE    => 'Asset type such as image, document or video. Example: {asset_type}',
            self::CHAR          => 'Nth character of the asset filename, "_" when it is shorter. Example: {char:1}',
            self::DATE          => 'Current date in the given PHP date format. Example: {date:Y/m}',
        ];
    }

    /**
     * Names of all known tokens
     *
     * @return array<int, string>
     */
    public static function getNames(): array
    {
        return array_keys(self::getDefinitions());
    }

    /**
     * Tokens used in the given pattern that this bundle cannot resolve
     *
     * @param string $pattern
     * @return array<int, string>
     */
    public static function getUnknownTokens(string $pattern): array
    {
        preg_match_all(self::BRACED_TOKEN_PATTERN, $pattern, $matches);

        $names = array_map(
            static fn (string $token): string => explode(':', $token, 2)[0],
            $matches[1] ?? []
        );

        return array_values(array_diff(array_unique($names), self::getNames()));
    }

    /**
     * Whether the pattern contains unmatched braces or a token that does not follow the token syntax
     *
     * @param string $pattern
     * @return bool
     */
    public static function hasMalformedToken(string $pattern): bool
    {
        $withoutTokens = preg_replace(self::TOKEN_PATTERN, '', $pattern);

        return $withoutTokens === null
            || str_contains($withoutTokens, '{')
            || str_contains($withoutTokens, '}');
    }

    /**
     * Single line hint appended to the configuration info, mirrored by the future Studio UI form
     *
     * @return string
     */
    public static function getInfoLine(): string
    {
        return 'Available tokens: {' . implode('}, {', self::getNames()) . '}.';
    }
}
