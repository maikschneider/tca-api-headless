<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\OperationHandler;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\OperationHandler\GetItemHandler;
use MaikSchneider\TcaApi\OperationHandler\OperationHandlerInterface;
use MaikSchneider\TcaApi\Serializer\HydraResponseBuilder;
use MaikSchneider\TcaApi\Serializer\ResourceSerializer;
use MaikSchneider\TcaApiHeadless\Cache\CacheDependencies;
use MaikSchneider\TcaApiHeadless\Composition\FrontendGroups;
use MaikSchneider\TcaApiHeadless\EventListener\BuildNavigationTree;
use MaikSchneider\TcaApiHeadless\Navigation\NavigationDepth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Serves the page resources of the add-on: a page is only delivered by the site
 * whose page tree it belongs to, and only like the frontend would: to the
 * frontend user groups of the page and of the ancestors that extend to
 * subpages, and not below a hidden or scheduled ancestor that extends to
 * subpages. Otherwise it answers like a missing record.
 *
 * The page collection is no listing but the lookup of one page by its path,
 * resolved by the site router like a frontend request in the request language.
 */
final readonly class HeadlessPageHandler implements OperationHandlerInterface
{
    public const RESOURCES = ['headless_pages', 'headless_navigation'];

    public const LOOKUP_RESOURCE = 'headless_pages';

    public function __construct(
        private GetItemHandler $getItemHandler,
        private HydraResponseBuilder $hydraResponseBuilder,
        private DataRepository $dataRepository,
        private ResourceSerializer $serializer,
        private SiteFinder $siteFinder,
        private Context $context,
        private FrontendGroups $frontendGroups,
        private CacheDependencies $cacheDependencies,
    ) {
    }

    public function supports(ServerRequestInterface $request, string $operation, ApiDefinition $config): bool
    {
        return match ($operation) {
            'show' => \in_array($config->resourceName, self::RESOURCES, true),
            'list' => $config->resourceName === self::LOOKUP_RESOURCE,
            default => false,
        };
    }

    public function handle(ServerRequestInterface $request, ApiDefinition $config): ResponseInterface
    {
        if ($request->getAttribute('tca_api.operation') === 'list') {
            return $this->lookUp($request, $config);
        }

        if ($config->resourceName === BuildNavigationTree::RESOURCE && !NavigationDepth::isValid($request)) {
            return $this->hydraResponseBuilder->buildError(400, 'The query parameter "depth" must be a positive integer.', 'Bad Request');
        }

        if (!$this->isDeliverable((int)$request->getAttribute('tca_api.uid'), $request)) {
            return $this->hydraResponseBuilder->buildError(404, 'Resource not found', 'Not Found');
        }

        return $this->getItemHandler->handle($request, $config);
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * A collection of the page at the given path, or an empty one. Without a path
     * there is nothing to look up: pages are never enumerated.
     */
    private function lookUp(ServerRequestInterface $request, ApiDefinition $config): ResponseInterface
    {
        $path = $request->getQueryParams()['path'] ?? null;
        if (!\is_string($path) || trim($path) === '') {
            return $this->hydraResponseBuilder->buildError(400, 'The query parameter "path" is required.', 'Bad Request');
        }

        $baseUrl = (string)$request->getAttribute('tca_api.api_prefix', '/_api') . '/' . $config->resourceName;
        $members = [];
        $pageUid = $this->resolvePath(trim($path), $request);
        if ($pageUid !== null && $this->isDeliverable($pageUid, $request)) {
            $language = $request->getAttribute('tca_api.language');
            $this->cacheDependencies->expireWithRecords($config->table, [], $config, $language, $pageUid);
            $row = $this->dataRepository->findById($config->table, $pageUid, $config, $language);
            if ($row !== null) {
                $members[] = $this->serializer->serialize($row, $config, $baseUrl, [], ['__request' => $request], operation: 'list');
            }
        }

        return $this->hydraResponseBuilder->buildCollection($members, \count($members), $baseUrl, 1, 1, ['path' => $path]);
    }

    /**
     * The page the frontend would serve for the path below the base of the
     * request language, route enhancers included. The router ignores
     * visibility; the caller checks it.
     */
    private function resolvePath(string $path, ServerRequestInterface $request): ?int
    {
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        if (!$site instanceof Site || !$language instanceof SiteLanguage) {
            return null;
        }

        $tail = ltrim($path, '/');
        // A query string or fragment is not part of a page path.
        if (strpbrk($tail, '?#') !== false) {
            return null;
        }
        $base = $language->getBase();
        $uri = $base->withPath(rtrim($base->getPath(), '/') . '/' . $tail);
        // A fresh request: query parameters of the API request (e.g. "id") must not steer the router.
        $routeRequest = new ServerRequest($uri, 'GET');

        try {
            $result = $site->getRouter()->matchRequest($routeRequest, new SiteRouteResult($uri, $site, $language, $tail));
        } catch (RouteNotFoundException) {
            return null;
        }

        return $result instanceof PageArguments ? $result->getPageId() : null;
    }

    private function isDeliverable(int $pageUid, ServerRequestInterface $request): bool
    {
        return $this->belongsToRequestSite($pageUid, $request)
            && $this->isVisibleThroughAncestors($pageUid);
    }

    private function belongsToRequestSite(int $pageUid, ServerRequestInterface $request): bool
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof SiteInterface) {
            return false;
        }

        try {
            return $this->siteFinder->getSiteByPageId($pageUid)->getIdentifier() === $site->getIdentifier();
        } catch (SiteNotFoundException) {
            return false;
        }
    }

    /**
     * The frontend's inheritance: the page's own groups, and the visibility and
     * groups of every ancestor that extends to subpages. The page's own
     * visibility is checked by the read of the definition. A cached response
     * expires when one of these ancestors starts or ends.
     */
    private function isVisibleThroughAncestors(int $pageUid): bool
    {
        $now = (int)$this->context->getPropertyFromAspect('date', 'timestamp');
        $visible = true;
        $transitions = [];

        foreach (GeneralUtility::makeInstance(RootlineUtility::class, $pageUid)->get() as $page) {
            $isPage = (int)$page['uid'] === $pageUid;
            if (!$isPage && !($page['extendToSubpages'] ?? false)) {
                continue;
            }
            if (!$this->frontendGroups->grant($page['fe_group'] ?? '')) {
                $visible = false;
            }
            if ($isPage) {
                continue;
            }
            $starttime = (int)($page['starttime'] ?? 0);
            $endtime = (int)($page['endtime'] ?? 0);
            if (($page['hidden'] ?? false) || $starttime > $now || ($endtime !== 0 && $endtime <= $now)) {
                $visible = false;
            }
            $transitions[] = $starttime;
            $transitions[] = $endtime;
        }

        $transitions = array_filter($transitions, static fn (int $time): bool => $time > $now);
        $this->cacheDependencies->expireAt($transitions === [] ? null : min($transitions));

        return $visible;
    }
}
