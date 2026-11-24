<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Configuration\ColumnDefinition;
use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use MaikSchneider\TcaApiHeadless\Processing\LinkProcessor;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the link columns that LinkProcessor sends as HeadlessLink or null.
 */
#[AsEventListener('tca-api-headless/describe-links')]
final class DescribeLinks
{
    /**
     * Properties of a link, shared with the link annotations of Portable Text.
     */
    public const PROPERTIES = [
        'href' => ['type' => 'string', 'format' => 'uri', 'description' => 'Absolute http(s), mailto or tel URL.'],
        'target' => ['type' => ['string', 'null'], 'description' => 'Link target such as "_blank"; null when not set.'],
        'title' => ['type' => ['string', 'null'], 'description' => 'Link title; null when not set.'],
    ];

    private const SCHEMA_PREFIX = '#/components/schemas/';

    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $document = $event->getDocument();
        $schemas = $document['components']['schemas'] ?? [];
        $schemaNames = new OpenApiSchemaNames($resources);
        $link = ['oneOf' => [['$ref' => self::SCHEMA_PREFIX . 'HeadlessLink'], ['type' => 'null']]];

        $described = false;
        foreach ($resources as $resource) {
            $columns = array_keys(array_filter(
                $resource->columns,
                static fn (ColumnDefinition $column): bool => $column->processor === LinkProcessor::class,
            ));
            if ($columns === []) {
                continue;
            }

            $readSchema = $schemaNames->of($resource) . 'Read';
            $typeSchemas = array_map(
                static fn (string $reference): string => substr($reference, \strlen(self::SCHEMA_PREFIX)),
                (array)($schemas[$readSchema]['discriminator']['mapping'] ?? []),
            );
            foreach ([$readSchema, ...array_values($typeSchemas)] as $name) {
                foreach ($columns as $column) {
                    if (isset($schemas[$name]['properties'][$column])) {
                        $schemas[$name]['properties'][$column] = $link;
                        $described = true;
                    }
                }
            }
        }

        if (!$described) {
            return;
        }

        $schemas['HeadlessLink'] = [
            'type' => 'object',
            'properties' => self::PROPERTIES,
            'required' => array_keys(self::PROPERTIES),
            'additionalProperties' => false,
        ];
        $document['components']['schemas'] = $schemas;
        $event->setDocument($document);
    }
}
