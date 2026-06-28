<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerRegistry;
use MaikSchneider\TcaApiHeadless\Contract\Contract;
use MaikSchneider\TcaApiHeadless\Meta\SeoMetaBuilder;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Composes a single TYPO3 page into the headless page contract.
 *
 * At this stage it produces the page envelope with meta only; content regions
 * are filled in by {@see RegionResolver} in a later step.
 */
final class PageComposer
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly RegionResolver $regionResolver,
        private readonly BlockSerializerRegistry $blockSerializerRegistry,
        private readonly SeoMetaBuilder $seoMetaBuilder,
        private readonly FrontendInterface $cache,
    ) {
    }

    /**
     * @return array<string, mixed>|null The page payload, or null when the page does not exist.
     */
    public function compose(int $pageId, SiteLanguage $language): ?array
    {
        $cacheKey = sprintf('page_%d_%d', $pageId, $language->getLanguageId());
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $page = $this->fetchPage($pageId, $language);
        if ($page === null) {
            return null;
        }

        $seoMeta = $this->seoMetaBuilder->build($page, $language);
        $regions = $this->composeRegions($pageId, $language);

        $payload = [
            'contract' => Contract::VERSION,
            'type' => 'page',
            'id' => $pageId,
            'meta' => [
                'title' => (string)($page['title'] ?? ''),
                'language' => $language->getLocale()->getLanguageCode(),
                'slug' => (string)($page['slug'] ?? ''),
                'seo' => $seoMeta['seo'],
                'schema' => $seoMeta['schema'],
            ],
            'regions' => $regions,
        ];

        $this->cache->set($cacheKey, $payload, $this->cacheTags($pageId, $regions));

        return $payload;
    }

    /**
     * @param array<string, list<array<string, mixed>>>|\stdClass $regions
     * @return list<string>
     */
    private function cacheTags(int $pageId, array|\stdClass $regions): array
    {
        $tags = ['pages_' . $pageId];
        if (is_array($regions)) {
            foreach ($regions as $blocks) {
                foreach ($blocks as $block) {
                    if (isset($block['id'])) {
                        $tags[] = 'tt_content_' . (int)$block['id'];
                    }
                }
            }
        }

        return array_values(array_unique($tags));
    }

    /**
     * @return array<string, list<array<string, mixed>>>|\stdClass Region map, or an empty object when there is no content.
     */
    private function composeRegions(int $pageId, SiteLanguage $language): array|\stdClass
    {
        $context = new BlockContext($language, $pageId);
        $regions = [];
        foreach ($this->regionResolver->resolve($pageId, $language) as $regionName => $rows) {
            foreach ($rows as $row) {
                $regions[$regionName][] = $this->blockSerializerRegistry->serialize($row, $context);
            }
        }

        // Emit an empty object (not an array) when the page has no content.
        return $regions === [] ? new \stdClass() : $regions;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPage(int $pageId, SiteLanguage $language): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $row = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        // Overlay the translated page record when a non-default language is requested.
        $languageId = $language->getLanguageId();
        if ($languageId > 0) {
            $overlay = $this->fetchTranslationOverlay($pageId, $languageId);
            if ($overlay !== null) {
                $row = array_merge($row, $overlay);
            }
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchTranslationOverlay(int $pageId, int $languageId): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $row = $queryBuilder
            ->select('title', 'slug')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT),
                ),
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row === false ? null : $row;
    }
}
