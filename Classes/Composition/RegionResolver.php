<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves the content of a page into named regions.
 *
 * Content elements are fetched from tt_content by pid and colPos, then grouped
 * under semantic region names mapped from colPos. The frontend never sees raw
 * colPos integers.
 *
 * Language handling selects records authored in the requested language
 * (free / directly-translated mode) plus "all languages" records. Connected-mode
 * overlay (translated content overlaying the default language via l18n_parent)
 * is a later enhancement.
 */
final class RegionResolver
{
    /**
     * Default colPos → region name map (mirrors TYPO3's default backend layout).
     * backend_layout-driven mapping is a later enhancement.
     *
     * @var array<int, string>
     */
    private const COLPOS_REGION_MAP = [
        0 => 'main',
        1 => 'left',
        2 => 'right',
        3 => 'border',
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return array<string, list<array<string, mixed>>> Region name → ordered content rows.
     */
    public function resolve(int $pageId, SiteLanguage $language): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        $rows = $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT),
                ),
                $queryBuilder->expr()->in(
                    'colPos',
                    $queryBuilder->createNamedParameter(
                        array_keys(self::COLPOS_REGION_MAP),
                        Connection::PARAM_INT_ARRAY,
                    ),
                ),
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter(
                        [-1, $language->getLanguageId()],
                        Connection::PARAM_INT_ARRAY,
                    ),
                ),
            )
            ->orderBy('colPos')
            ->addOrderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $regions = [];
        foreach ($rows as $row) {
            $regionName = self::COLPOS_REGION_MAP[(int)$row['colPos']] ?? null;
            if ($regionName === null) {
                continue;
            }
            $regions[$regionName][] = $row;
        }

        return $regions;
    }
}
