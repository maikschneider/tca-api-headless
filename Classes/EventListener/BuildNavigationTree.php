<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\Event\AfterOperationEvent;
use MaikSchneider\TcaApiHeadless\Composition\RecordReader;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Adds the absolute URL and the subpages to a headless_navigation show
 * response, down to tca_api_headless.navigation.depth levels below the
 * requested page: {url, children: [{…, url, children: […]}]}.
 *
 * The tree is read breadth-first with one read per level. Like a frontend
 * menu, it leaves out pages hidden in menus, pages unavailable in the request
 * language and the page trees of other sites, with their subpages.
 */
#[AsEventListener('tca-api-headless/build-navigation-tree')]
final readonly class BuildNavigationTree
{
    public const RESOURCE = 'headless_navigation';

    public function __construct(
        private RecordReader $recordReader,
        private PageRepository $pageRepository,
        private SiteFinder $siteFinder,
    ) {
    }

    public function __invoke(AfterOperationEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        if ($event->getOperation() !== 'show' || $event->getConfig()->resourceName !== self::RESOURCE || !$site instanceof Site || !$language instanceof SiteLanguage) {
            return;
        }

        $data = $event->getData();
        $pageUid = (int)$data['uid'];
        $data['url'] = $this->url($pageUid, $site, $language);
        $data['children'] = $this->subpagesOf($pageUid, $event->getConfig(), $request, $site, $language);
        $event->setData($data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subpagesOf(int $pageUid, ApiDefinition $config, ServerRequestInterface $request, Site $site, SiteLanguage $language): array
    {
        $depth = (int)$site->getSettings()->get('tca_api_headless.navigation.depth', 2);
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($language);
        $otherSiteRoots = $this->otherSiteRoots($site);

        // Per level: nodes by parent uid, then by their own uid.
        $levels = [];
        $parentUids = [$pageUid];
        while (\count($levels) < $depth && $parentUids !== []) {
            $nodes = [];
            foreach ($this->recordReader->readByPid($config, $parentUids, $request) as [$row, $node]) {
                if (!$this->isInMenu($row, $languageAspect) || isset($otherSiteRoots[(int)$row['uid']])) {
                    continue;
                }
                $node['url'] = $this->url($row, $site, $language);
                $node['children'] = [];
                $nodes[(int)$row['pid']][(int)$row['uid']] = $node;
            }
            $levels[] = $nodes;
            $parentUids = array_keys(array_replace([], ...array_values($nodes)));
        }

        for ($level = \count($levels) - 1; $level > 0; $level--) {
            foreach ($levels[$level - 1] as $parentUid => $siblings) {
                foreach (array_keys($siblings) as $uid) {
                    $levels[$level - 1][$parentUid][$uid]['children'] = array_values($levels[$level][$uid] ?? []);
                }
            }
        }

        return array_values($levels[0][$pageUid] ?? []);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isInMenu(array $row, LanguageAspect $languageAspect): bool
    {
        return !($row['nav_hide'] ?? false) && $this->pageRepository->isPageSuitableForLanguage($row, $languageAspect);
    }

    /**
     * @return array<int, true>
     */
    private function otherSiteRoots(Site $site): array
    {
        $roots = [];
        foreach ($this->siteFinder->getAllSites() as $otherSite) {
            if ($otherSite->getIdentifier() !== $site->getIdentifier()) {
                $roots[$otherSite->getRootPageId()] = true;
            }
        }

        return $roots;
    }

    /**
     * The router takes a page row in the request language as it is, without
     * reading the page again.
     *
     * @param int|array<string, mixed> $page
     */
    private function url(int|array $page, Site $site, SiteLanguage $language): string
    {
        return (string)$site->getRouter()->generateUri($page, ['_language' => $language], '', RouterInterface::ABSOLUTE_URL);
    }
}
