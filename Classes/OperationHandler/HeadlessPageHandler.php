<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\OperationHandler;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\OperationHandler\GetItemHandler;
use MaikSchneider\TcaApi\OperationHandler\OperationHandlerInterface;
use MaikSchneider\TcaApi\Serializer\HydraResponseBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Serves the page resources of the add-on: a page is only delivered by the site
 * whose page tree it belongs to, otherwise it answers like a missing record.
 */
final readonly class HeadlessPageHandler implements OperationHandlerInterface
{
    public const RESOURCES = ['headless_pages', 'headless_navigation'];

    public function __construct(
        private GetItemHandler $getItemHandler,
        private HydraResponseBuilder $hydraResponseBuilder,
        private SiteFinder $siteFinder,
    ) {
    }

    public function supports(ServerRequestInterface $request, string $operation, ApiDefinition $config): bool
    {
        return $operation === 'show' && \in_array($config->resourceName, self::RESOURCES, true);
    }

    public function handle(ServerRequestInterface $request, ApiDefinition $config): ResponseInterface
    {
        if (!$this->belongsToRequestSite((int)$request->getAttribute('tca_api.uid'), $request)) {
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
}
