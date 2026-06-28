<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApiHeadless\Contract\Contract;
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
    ) {}

    /**
     * @return array<string, mixed>|null The page payload, or null when the page does not exist.
     */
    public function compose(int $pageId, SiteLanguage $language): ?array
    {
        $page = $this->fetchPage($pageId, $language);
        if ($page === null) {
            return null;
        }

        return [
            'contract' => Contract::VERSION,
            'type' => 'page',
            'id' => $pageId,
            'meta' => [
                'title' => (string)($page['title'] ?? ''),
                'language' => $language->getLocale()->getLanguageCode(),
                'slug' => (string)($page['slug'] ?? ''),
            ],
            // No regions yet — emitted as an empty object, not an array.
            'regions' => new \stdClass(),
        ];
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
