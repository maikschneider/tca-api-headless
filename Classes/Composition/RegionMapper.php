<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Names the content columns of a page. A page with a backend layout, its own
 * or inherited through backend_layout_next_level, uses the identifiers of the
 * layout's columns; a page without one, and a layout column without an
 * identifier, uses the site setting tca_api_headless.regions ("colPos:name").
 */
final readonly class RegionMapper
{
    private const DEFAULT_LAYOUT = 'default';

    public function __construct(
        private BackendLayoutView $backendLayoutView,
    ) {
    }

    /**
     * @return array<int, string> region name by colPos, in the order of the layout
     */
    public function regionsOf(int $pageUid, Site $site): array
    {
        $siteRegions = $this->siteRegions($site);

        $layout = $this->backendLayoutView->getBackendLayoutForPage($pageUid);
        if ($layout->getIdentifier() === self::DEFAULT_LAYOUT) {
            return $siteRegions;
        }

        $rows = $this->backendLayoutView->parseStructure($layout)['__config']['backend_layout.']['rows.'] ?? [];
        ksort($rows);

        $regions = [];
        foreach ($rows as $row) {
            foreach ($row['columns.'] ?? [] as $column) {
                if (!isset($column['colPos']) || $column['colPos'] === '') {
                    continue;
                }
                $colPos = (int)$column['colPos'];
                $name = trim((string)($column['identifier'] ?? '')) ?: ($siteRegions[$colPos] ?? '');
                if ($name !== '') {
                    $regions[$colPos] = $name;
                }
            }
        }

        return $regions;
    }

    /**
     * @return array<int, string>
     */
    private function siteRegions(Site $site): array
    {
        $regions = [];
        foreach ((array)$site->getSettings()->get('tca_api_headless.regions', []) as $entry) {
            $entry = (string)$entry;
            [$colPos, $name] = array_map(trim(...), explode(':', $entry, 2)) + [1 => ''];
            if (!MathUtility::canBeInterpretedAsInteger($colPos) || $name === '') {
                throw new \UnexpectedValueException(sprintf(
                    'Site "%s": tca_api_headless.regions entry "%s" must be "colPos:name".',
                    $site->getIdentifier(),
                    $entry,
                ), 1795454001);
            }
            $regions[(int)$colPos] = $name;
        }

        return $regions;
    }
}
