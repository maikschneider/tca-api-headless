<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the meta object that AddPageMeta adds to a page.
 */
#[AsEventListener('tca-api-headless/describe-page-meta')]
final class DescribePageMeta
{
    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $pages = $resources['headless_pages'] ?? null;
        if ($pages === null) {
            return;
        }
        $document = $event->getDocument();
        $schemaNames = new OpenApiSchemaNames($resources);
        $pageSchema = $schemaNames->of($pages) . 'Read';
        if (!isset($document['components']['schemas'][$pageSchema]['properties'])) {
            return;
        }

        $string = ['type' => 'string'];
        $metaSchema = $schemaNames->of($pages) . 'Meta';
        $document['components']['schemas'][$metaSchema] = [
            'type' => 'object',
            'properties' => [
                'robots' => ['type' => 'string', 'enum' => ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow']],
                'canonical' => [
                    'description' => 'Absolute canonical URL; null when the page must not be indexed.',
                    'oneOf' => [['type' => 'string', 'format' => 'uri'], ['type' => 'null']],
                ],
                'schema' => [
                    'type' => 'object',
                    'description' => 'schema.org WebPage as JSON-LD.',
                    'properties' => [
                        '@context' => ['const' => 'https://schema.org'],
                        '@type' => ['const' => 'WebPage'],
                        'url' => ['type' => 'string', 'format' => 'uri'],
                        'name' => $string,
                        'inLanguage' => $string,
                        'description' => $string,
                    ],
                    'required' => ['@context', '@type', 'url', 'name', 'inLanguage'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['robots', 'canonical', 'schema'],
            'additionalProperties' => false,
        ];
        $document['components']['schemas'][$pageSchema]['properties']['meta'] = ['$ref' => '#/components/schemas/' . $metaSchema];
        $event->setDocument($document);
    }
}
