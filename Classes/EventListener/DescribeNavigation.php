<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\ModifyOpenApiDocumentEvent;
use MaikSchneider\TcaApi\OpenApi\OpenApiSchemaNames;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Types the URL and the subpages that BuildNavigationTree adds to a
 * navigation entry; a subpage is a navigation entry itself.
 */
#[AsEventListener('tca-api-headless/describe-navigation')]
final class DescribeNavigation
{
    public function __invoke(ModifyOpenApiDocumentEvent $event): void
    {
        $resources = $event->getResources();
        $navigation = $resources[BuildNavigationTree::RESOURCE] ?? null;
        if ($navigation === null) {
            return;
        }
        $document = $event->getDocument();
        $schema = (new OpenApiSchemaNames($resources))->of($navigation) . 'Read';
        if (!isset($document['components']['schemas'][$schema]['properties'])) {
            return;
        }

        $entry = $document['components']['schemas'][$schema];
        $entry['properties']['url'] = ['type' => 'string', 'format' => 'uri', 'description' => 'Absolute URL of the page in the request language.'];
        $entry['properties']['children'] = [
            'type' => 'array',
            'description' => 'Subpages shown in menus, in their order in the page tree.',
            'items' => ['$ref' => '#/components/schemas/' . $schema],
        ];
        $entry['required'] = array_values(array_unique(array_merge($entry['required'] ?? [], ['uid', 'url', 'children'])));
        $document['components']['schemas'][$schema] = $entry;
        $event->setDocument($document);
    }
}
