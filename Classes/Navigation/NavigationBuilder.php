<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Navigation;

use MaikSchneider\HeadlessPages\Link\TypoLinkResolver;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds a navigation tree from the page tree.
 *
 * Each item is `{ id, title, link, children[] }`. The page structure is read in
 * the default language and translated titles are overlaid for non-default
 * languages. Language-aware link URLs are a later enhancement; links are
 * resolved via the typolink resolver and may be null when no site is configured.
 */
final class NavigationBuilder
{
    /**
     * Doktypes included in navigation: standard (1), external link (3), shortcut (4).
     */
    private const NAV_DOKTYPES = [1, 3, 4];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly TypoLinkResolver $typoLinkResolver,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $rootPageId, int $depth, SiteLanguage $language): array
    {
        return $this->childrenOf($rootPageId, max(1, $depth), $language);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function childrenOf(int $pid, int $depth, SiteLanguage $language): array
    {
        if ($depth <= 0) {
            return [];
        }

        $items = [];
        foreach ($this->fetchPages($pid, $language) as $row) {
            $uid = (int)$row['uid'];
            $title = ($row['nav_title'] ?? '') !== '' ? (string)$row['nav_title'] : (string)$row['title'];
            $items[] = [
                'id' => $uid,
                'title' => $title,
                'link' => $this->typoLinkResolver->resolve('t3://page?uid=' . $uid),
                'children' => $this->childrenOf($uid, $depth - 1, $language),
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPages(int $pid, SiteLanguage $language): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $rows = $queryBuilder
            ->select('uid', 'title', 'nav_title', 'doktype')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('nav_hide', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->in(
                    'doktype',
                    $queryBuilder->createNamedParameter(self::NAV_DOKTYPES, Connection::PARAM_INT_ARRAY),
                ),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        if ($language->getLanguageId() > 0 && $rows !== []) {
            $rows = $this->overlayTitles($rows, $language->getLanguageId());
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function overlayTitles(array $rows, int $languageId): array
    {
        $uids = array_map(static fn (array $row): int => (int)$row['uid'], $rows);

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $translations = $queryBuilder
            ->select('l10n_parent', 'title', 'nav_title')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->in(
                    'l10n_parent',
                    $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY),
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchAllAssociative();

        $byParent = [];
        foreach ($translations as $translation) {
            $byParent[(int)$translation['l10n_parent']] = $translation;
        }

        foreach ($rows as &$row) {
            $overlay = $byParent[(int)$row['uid']] ?? null;
            if ($overlay !== null) {
                $row['title'] = $overlay['title'];
                $row['nav_title'] = $overlay['nav_title'];
            }
        }
        unset($row);

        return $rows;
    }
}
