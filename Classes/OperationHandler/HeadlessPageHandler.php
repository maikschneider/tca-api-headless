<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\OperationHandler;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\OperationHandler\GetItemHandler;
use MaikSchneider\TcaApi\OperationHandler\OperationHandlerInterface;
use MaikSchneider\TcaApi\Serializer\HydraResponseBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Serves the page resources of the add-on: a page is only delivered by the site
 * whose page tree it belongs to, and only to the frontend user groups of its
 * fe_group or of an ancestor's that extends to subpages. Otherwise it answers
 * like a missing record.
 */
final readonly class HeadlessPageHandler implements OperationHandlerInterface
{
    public const RESOURCES = ['headless_pages', 'headless_navigation'];

    public function __construct(
        private GetItemHandler $getItemHandler,
        private HydraResponseBuilder $hydraResponseBuilder,
        private SiteFinder $siteFinder,
        private Context $context,
    ) {
    }

    public function supports(ServerRequestInterface $request, string $operation, ApiDefinition $config): bool
    {
        return $operation === 'show' && \in_array($config->resourceName, self::RESOURCES, true);
    }

    public function handle(ServerRequestInterface $request, ApiDefinition $config): ResponseInterface
    {
        $pageUid = (int)$request->getAttribute('tca_api.uid');
        if (!$this->belongsToRequestSite($pageUid, $request) || !$this->isGrantedToFrontendUser($pageUid)) {
            return $this->hydraResponseBuilder->buildError(404, 'Resource not found', 'Not Found');
        }

        return $this->getItemHandler->handle($request, $config);
    }

    public function getPriority(): int
    {
        return 100;
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
     * The group check of the frontend: a page without fe_group is public, otherwise
     * one of the user's groups (incl. -1 "hide at login", -2 "any login") must match.
     */
    private function isGrantedToFrontendUser(int $pageUid): bool
    {
        $groupIds = array_map(strval(...), (array)$this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]));

        foreach (GeneralUtility::makeInstance(RootlineUtility::class, $pageUid)->get() as $page) {
            if ((int)$page['uid'] !== $pageUid && !($page['extendToSubpages'] ?? false)) {
                continue;
            }
            $pageGroups = GeneralUtility::trimExplode(',', (string)($page['fe_group'] ?? ''), true);
            if ($pageGroups !== [] && array_intersect($pageGroups, $groupIds) === []) {
                return false;
            }
        }

        return true;
    }
}
