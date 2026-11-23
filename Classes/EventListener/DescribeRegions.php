<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use MaikSchneider\TcaApiHeadless\Composition\PageContentReader;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the regions that ComposePageRegions adds to a page: content of the
 * headless_content types, and the fallback types the site allows as
 * HeadlessContentFallbackRead.
 */
#[AsEventListener('tca-api-headless/describe-regions')]
final class DescribeRegions
{
    private const SCHEMA_PREFIX = '#/components/schemas/';

    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $pages = $resources['headless_pages'] ?? null;
        $content = $resources[PageContentReader::RESOURCE] ?? null;
        if ($pages === null || $content === null) {
            return;
        }
        $document = $event->getDocument();
        $schemaNames = new OpenApiSchemaNames($resources);
        $pageSchema = $schemaNames->of($pages) . 'Read';
        $contentSchema = $schemaNames->of($content) . 'Read';
        if (!isset($document['components']['schemas'][$pageSchema]['properties'], $document['components']['schemas'][$contentSchema])) {
            return;
        }

        $item = ['$ref' => self::SCHEMA_PREFIX . $contentSchema];
        $fallbackTypes = PageContentReader::fallbackTypes($content, $event->getContext()->settings);
        if ($fallbackTypes !== []) {
            $fallbackSchema = $schemaNames->of($content) . 'FallbackRead';
            $document['components']['schemas'][$fallbackSchema] = [
                'type' => 'object',
                'description' => 'A content type the site allows without its columns.',
                'properties' => [
                    'uid' => ['type' => 'integer'],
                    'CType' => ['type' => 'string', 'enum' => $fallbackTypes],
                    'header' => ['type' => 'string'],
                ],
                'required' => ['uid', 'CType', 'header'],
                'additionalProperties' => false,
            ];
            $item = ['oneOf' => [$item, ['$ref' => self::SCHEMA_PREFIX . $fallbackSchema]]];
        }

        $document['components']['schemas'][$pageSchema]['properties']['regions'] = [
            'type' => 'object',
            'description' => 'Content elements by region, in their order on the page.',
            'additionalProperties' => ['type' => 'array', 'items' => $item],
        ];
        $event->setDocument($document);
    }
}
