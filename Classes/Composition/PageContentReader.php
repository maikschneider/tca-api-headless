<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\Registry\ApiRegistry;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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
        private RecordReader $recordReader,
        private DataRepository $dataRepository,
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
        $languageId = $this->freeModeLanguageId($pageUid, $request);
        foreach ($this->recordReader->readByPid($catalog, [$pageUid], $request, $languageId) as [$row, $content]) {
            $entries[] = [$row, array_diff_key($content, self::LINKED_DATA_KEYS)];
        }
        $fallbackTypes = self::fallbackTypes($catalog, $site->getSettings());
        if ($fallbackTypes !== []) {
            foreach ($this->recordReader->readByPid($this->fallbackDefinition($catalog, $fallbackTypes), [$pageUid], $request, $languageId) as [$row, $content]) {
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
     * In free mode (fallbackType "free") content is not translated record by
     * record: a page shows the content of its own language. A page without a
     * translation is served in the default language, and so is its content.
     * Null in the other modes, which overlay default-language content.
     */
    private function freeModeLanguageId(int $pageUid, ServerRequestInterface $request): ?int
    {
        $language = $request->getAttribute('tca_api.language');
        if (!$language instanceof SiteLanguage || $language->getLanguageId() === 0 || $language->getFallbackType() !== 'free') {
            return null;
        }
        $page = $this->dataRepository->findByIdsWithOverlay('pages', [$pageUid], $language)[$pageUid] ?? [];

        return isset($page['_LOCALIZED_UID']) ? $language->getLanguageId() : 0;
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
