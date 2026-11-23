<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use MaikSchneider\TcaApiHeadless\OperationHandler\HeadlessPageHandler;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the page collection as what HeadlessPageHandler makes of it: the lookup
 * of one page by its mandatory path, answered with at most one page reference.
 */
#[AsEventListener('tca-api-headless/describe-path-lookup')]
final class DescribePathLookup
{
    private const SCHEMA_PREFIX = '#/components/schemas/';

    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $pages = $resources[HeadlessPageHandler::LOOKUP_RESOURCE] ?? null;
        if ($pages === null) {
            return;
        }
        $document = $event->getDocument();
        $schemaName = (new OpenApiSchemaNames($resources))->of($pages);
        $collectionSchema = $schemaName . 'Collection';
        if (!isset($document['components']['schemas'][$collectionSchema]['properties']['hydra:member'])) {
            return;
        }

        $lookupSchema = $schemaName . 'Lookup';
        $document['components']['schemas'][$lookupSchema] = [
            'type' => 'object',
            'description' => 'The page found at the path; fetch the page itself by its uid.',
            'properties' => [
                '@type' => ['type' => 'string'],
                '@id' => ['type' => 'string'],
                'uid' => ['type' => 'integer', 'description' => 'uid of the page in the default language.'],
            ],
            'required' => ['@type', '@id', 'uid'],
            'additionalProperties' => false,
        ];
        $document['components']['schemas'][$collectionSchema]['properties']['hydra:member'] = [
            'type' => 'array',
            'maxItems' => 1,
            'items' => ['$ref' => self::SCHEMA_PREFIX . $lookupSchema],
        ];

        $path = $event->getContext()->prefix . '/' . HeadlessPageHandler::LOOKUP_RESOURCE;
        if (isset($document['paths'][$path]['get'])) {
            $operation = $document['paths'][$path]['get'];
            $operation['summary'] = 'Look up a page by its path';
            $operation['description'] = 'Resolves the path like the frontend in the request language. An unknown path answers an empty collection.';
            // The lookup has no pages to paginate or fields to select.
            $operation['parameters'] = [[
                'name' => 'path',
                'in' => 'query',
                'required' => true,
                'description' => 'Page path below the base of the request language, e.g. "/about".',
                'schema' => ['type' => 'string', 'minLength' => 1],
            ]];
            if (isset($operation['responses']['400'])) {
                $operation['responses']['400']['description'] = 'The path is missing';
            }
            $document['paths'][$path]['get'] = $operation;
        }

        $event->setDocument($document);
    }
}
