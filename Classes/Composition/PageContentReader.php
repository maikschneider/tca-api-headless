<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\DataAccess\EmbedPreloader;
use MaikSchneider\TcaApi\Filter\ExactFilter;
use MaikSchneider\TcaApi\Filter\FilterContext;
use MaikSchneider\TcaApi\Registry\ApiRegistry;
use MaikSchneider\TcaApi\Serializer\ResourceSerializer;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;

/**
 * Reads the content elements of a page through TCA API's read pipeline, in two
 * passes: the content types of headless_content with their columns, and the
 * fallback types a site allows (tca_api_headless.fallbackCTypes) as
 * {uid, CType, header}. Other content types are left out.
 */
final readonly class PageContentReader
{
    public const RESOURCE = 'headless_content';

    /** Content has no endpoint of its own, so it is delivered without IRI and JSON-LD type. */
    private const LINKED_DATA_KEYS = ['@id' => true, '@type' => true];

    private const FALLBACK_KEYS = ['uid' => true, 'CType' => true, 'header' => true];

    public function __construct(
        private ApiRegistry $apiRegistry,
        private DataRepository $dataRepository,
        private EmbedPreloader $embedPreloader,
        private ResourceSerializer $serializer,
    ) {
    }

    /**
     * The fallback types a site allows beyond the content types of $catalog.
     *
     * @return list<string>
     */
    public static function fallbackTypes(ApiDefinition $catalog, SiteSettings $settings): array
    {
        return array_values(array_diff(
            array_map(strval(...), (array)$settings->get('tca_api_headless.fallbackCTypes', [])),
            $catalog->recordTypes ?? [],
        ));
    }

    /**
     * @return list<array{colPos: int, content: array<string, mixed>}> in the order of the sorting field
     */
    public function read(int $pageUid, ServerRequestInterface $request): array
    {
        $catalog = $this->apiRegistry->get(self::RESOURCE);
        $site = $request->getAttribute('site');
        if ($catalog === null || !$site instanceof Site) {
            return [];
        }

        $entries = [];
        foreach ($this->readPass($pageUid, $catalog, $request) as [$row, $content]) {
            $entries[] = [$row, array_diff_key($content, self::LINKED_DATA_KEYS)];
        }
        $fallbackTypes = self::fallbackTypes($catalog, $site->getSettings());
        if ($fallbackTypes !== []) {
            foreach ($this->readPass($pageUid, $this->fallbackDefinition($catalog, $fallbackTypes), $request) as [$row, $content]) {
                $entries[] = [$row, array_intersect_key($content, self::FALLBACK_KEYS)];
            }
        }

        usort($entries, static fn (array $a, array $b): int => [(int)$a[0]['sorting'], (int)$a[0]['uid']] <=> [(int)$b[0]['sorting'], (int)$b[0]['uid']]);

        return array_map(
            static fn (array $entry): array => ['colPos' => (int)$entry[0]['colPos'], 'content' => $entry[1]],
            $entries,
        );
    }

    /**
     * @return list<array{array<string, mixed>, array<string, mixed>}> raw row and serialized record
     */
    private function readPass(int $pageUid, ApiDefinition $config, ServerRequestInterface $request): array
    {
        $language = $request->getAttribute('tca_api.language');
        $constraints = [
            [ExactFilter::class, new FilterContext($pageUid, $config->table, 't.pid', ['type' => 'int'], $request, $config)],
            [FrontendGroupFilter::class, new FilterContext(null, $config->table, 't.fe_group', [], $request, $config)],
        ];

        $rows = $this->dataRepository->findCollection($config->table, $constraints, 0, 0, ['sorting' => 'asc'], $config, $language);
        if ($rows === []) {
            return [];
        }

        $allowedResources = $request->getAttribute('tca_api.allowed_resources');
        $preloaded = $this->embedPreloader->preload(
            $rows,
            $config,
            $language,
            [],
            'show',
            \is_array($allowedResources) ? array_values(array_filter($allowedResources, \is_string(...))) : [],
        ) + ['__request' => $request];
        $apiPrefix = (string)$request->getAttribute('tca_api.api_prefix', '/_api');
        $serialized = $this->serializer->serializeCollection($rows, $config, $apiPrefix . '/' . $config->resourceName, [], $preloaded, 'show');

        return array_map(null, $rows, $serialized);
    }

    /**
     * @param list<string> $types
     */
    private function fallbackDefinition(ApiDefinition $catalog, array $types): ApiDefinition
    {
        return ApiDefinition::fromArray([
            'general' => [
                'table' => $catalog->table,
                'resourceName' => $catalog->resourceName,
                'resourceType' => $catalog->resourceType . 'Fallback',
                'operations' => [],
                'recordTypes' => $types,
                'enabledSetting' => $catalog->enabledSetting,
            ],
            'columns' => [
                'CType' => ['groups' => ['show']],
                'header' => ['groups' => ['show']],
            ],
            'cache' => ['enabled' => false],
        ]);
    }
}
