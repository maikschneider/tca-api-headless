<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\AfterOperationEvent;
use MaikSchneider\TcaApiHeadless\Composition\PageContentReader;
use MaikSchneider\TcaApiHeadless\Composition\RegionMapper;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Adds the page's content to a headless_pages show response, grouped by
 * region: {regions: {main: [content…], …}}. Content in a column without a
 * region is left out.
 */
#[AsEventListener('tca-api-headless/compose-page-regions')]
final readonly class ComposePageRegions
{
    public function __construct(
        private PageContentReader $pageContentReader,
        private RegionMapper $regionMapper,
    ) {
    }

    public function __invoke(AfterOperationEvent $event): void
    {
        $site = $event->getRequest()->getAttribute('site');
        if ($event->getOperation() !== 'show' || $event->getConfig()->resourceName !== 'headless_pages' || !$site instanceof Site) {
            return;
        }

        $data = $event->getData();
        $pageUid = (int)$data['uid'];
        $contents = $this->pageContentReader->read($pageUid, $event->getRequest());

        $regions = [];
        if ($contents !== []) {
            $regionNames = $this->regionMapper->regionsOf($pageUid, $site);
            $regions = array_fill_keys(array_unique($regionNames), []);
            foreach ($contents as $entry) {
                $region = $regionNames[$entry['colPos']] ?? null;
                if ($region !== null) {
                    $regions[$region][] = $entry['content'];
                }
            }
            $regions = array_filter($regions);
        }

        $data['regions'] = $regions === [] ? new \stdClass() : $regions;
        $event->setData($data);
    }
}
