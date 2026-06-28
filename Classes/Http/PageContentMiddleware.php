<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Http;

use MaikSchneider\HeadlessPages\Composition\PageComposer;
use MaikSchneider\HeadlessPages\Contract\Contract;
use MaikSchneider\HeadlessPages\Navigation\NavigationBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Intercepts headless endpoints under the configured base path and returns the
 * composed page contract as JSON. All other requests pass through untouched.
 *
 * Registered after the frontend "site" middleware (so site + language are
 * resolved) and before "page-resolver" (so normal page rendering is skipped).
 */
final class PageContentMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly PageComposer $pageComposer,
        private readonly NavigationBuilder $navigationBuilder,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        $settings = $site->getSettings();
        if (!$settings->get('headless_pages.enabled', true)) {
            return $handler->handle($request);
        }

        $basePath = rtrim((string)$settings->get('headless_pages.basePath', '/_headless'), '/');
        if ($basePath === '') {
            return $handler->handle($request);
        }

        $language = $request->getAttribute('language');
        $route = $this->stripPrefixes($request->getUri()->getPath(), $basePath, $language);
        if ($route === null) {
            // Path is outside our namespace — not our concern.
            return $handler->handle($request);
        }

        $resolvedLanguage = $language instanceof SiteLanguage ? $language : $site->getDefaultLanguage();

        if (preg_match('#^/page/(\d+)$#', $route, $matches) === 1) {
            $payload = $this->pageComposer->compose((int)$matches[1], $resolvedLanguage);
            if ($payload === null) {
                return new JsonResponse(['error' => 'Page not found'], 404);
            }

            return new JsonResponse($payload);
        }

        if ($route === '/navigation') {
            $queryParams = $request->getQueryParams();
            $rootPageId = isset($queryParams['root']) ? (int)$queryParams['root'] : $site->getRootPageId();
            $depth = isset($queryParams['depth']) ? (int)$queryParams['depth'] : 3;

            return new JsonResponse([
                'contract' => Contract::VERSION,
                'type' => 'navigation',
                'root' => $rootPageId,
                'items' => $this->navigationBuilder->build($rootPageId, $depth, $resolvedLanguage),
            ]);
        }

        // Inside our namespace but no matching route.
        return new JsonResponse(['error' => 'Not found'], 404);
    }

    /**
     * Removes the language base and the configured base path from the request
     * path. Returns the remaining route, or null when the path is not ours.
     */
    private function stripPrefixes(string $path, string $basePath, ?SiteLanguage $language): ?string
    {
        if ($language instanceof SiteLanguage) {
            $languageBase = rtrim($language->getBase()->getPath(), '/');
            if ($languageBase !== '' && str_starts_with($path, $languageBase)) {
                $path = substr($path, strlen($languageBase));
            }
        }

        if (!str_starts_with($path, $basePath)) {
            return null;
        }

        return substr($path, strlen($basePath));
    }
}
