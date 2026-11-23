<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use MaikSchneider\TcaApiHeadless\Processing\BodytextProcessor;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the bodytext that BodytextProcessor sends per content type: list items
 * for bullets, rows for table, Portable Text for every other type.
 */
#[AsEventListener('tca-api-headless/describe-bodytext')]
final class DescribeBodytext
{
    private const SCHEMA_PREFIX = '#/components/schemas/';

    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $content = $resources['headless_content'] ?? null;
        if ($content === null || ($content->columns['bodytext']->processor ?? null) !== BodytextProcessor::class) {
            return;
        }

        $document = $event->getDocument();
        $schemas = $document['components']['schemas'] ?? [];
        $readSchema = (new OpenApiSchemaNames($resources))->of($content) . 'Read';
        $typeSchemas = $schemas[$readSchema]['discriminator']['mapping'] ?? [];

        $used = [];
        foreach ($typeSchemas as $cType => $reference) {
            $name = substr($reference, \strlen(self::SCHEMA_PREFIX));
            if (!isset($schemas[$name]['properties']['bodytext'])) {
                continue;
            }

            $bodytext = match ((string)$cType) {
                'bullets' => 'HeadlessBulletList',
                'table' => 'HeadlessTable',
                default => 'PortableText',
            };
            $schemas[$name]['properties']['bodytext'] = ['$ref' => self::SCHEMA_PREFIX . $bodytext];
            $used[$bodytext] = true;
        }

        if ($used === []) {
            return;
        }

        if (isset($used['PortableText'])) {
            $schemas += self::portableTextSchemas();
        }
        if (isset($used['HeadlessBulletList'])) {
            $schemas['HeadlessBulletList'] = [
                'description' => 'One entry per line; terms and definitions for a definition list (bullets_type 2).',
                'anyOf' => [
                    ['type' => 'array', 'items' => ['type' => 'string']],
                    [
                        'type' => 'array',
                        'items' => self::object(['term' => ['type' => 'string'], 'definition' => ['type' => 'string']]),
                    ],
                ],
            ];
        }
        if (isset($used['HeadlessTable'])) {
            $row = ['type' => 'array', 'items' => ['type' => 'string']];
            $schemas['HeadlessTable'] = self::object([
                'head' => [...$row, 'description' => 'Header row; empty without one.'],
                'body' => ['type' => 'array', 'items' => $row],
                'foot' => [...$row, 'description' => 'Footer row; empty without one.'],
            ]);
        }

        $document['components']['schemas'] = $schemas;
        $event->setDocument($document);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function portableTextSchemas(): array
    {
        $string = ['type' => 'string'];

        return [
            'PortableText' => [
                'type' => 'array',
                'items' => ['$ref' => self::SCHEMA_PREFIX . 'PortableTextBlock'],
                'description' => 'Rich text as Portable Text, see https://www.portabletext.org/specification/',
            ],
            'PortableTextBlock' => self::object([
                '_type' => ['type' => 'string', 'const' => 'block'],
                '_key' => $string,
                'style' => ['type' => 'string', 'enum' => ['normal', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote']],
                'markDefs' => ['type' => 'array', 'items' => ['$ref' => self::SCHEMA_PREFIX . 'PortableTextLink']],
                'children' => ['type' => 'array', 'items' => ['$ref' => self::SCHEMA_PREFIX . 'PortableTextSpan']],
                'listItem' => ['type' => 'string', 'enum' => ['bullet', 'number']],
                'level' => ['type' => 'integer', 'minimum' => 1],
            ], ['_type', '_key', 'style', 'markDefs', 'children']),
            'PortableTextSpan' => self::object([
                '_type' => ['type' => 'string', 'const' => 'span'],
                '_key' => $string,
                'marks' => [
                    'type' => 'array',
                    'items' => $string,
                    'description' => 'Decorators (strong, em, underline, strike-through, code) and markDef keys.',
                ],
                'text' => $string,
            ]),
            'PortableTextLink' => self::object([
                '_type' => ['type' => 'string', 'const' => 'link'],
                '_key' => $string,
                'href' => ['type' => 'string', 'format' => 'uri', 'description' => 'Absolute http(s), mailto or tel URL.'],
            ]),
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string>|null $required all properties when null
     * @return array<string, mixed>
     */
    private static function object(array $properties, ?array $required = null): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required ?? array_keys($properties),
            'additionalProperties' => false,
        ];
    }
}
