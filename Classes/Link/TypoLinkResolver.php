<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Link;

use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Resolves a TYPO3 typolink string into the contract's `link` value object
 * (`{ href, type, target, title }`), or null when it cannot be resolved.
 *
 * Uses the same primitives as tca-api's TypoLinkProcessor (LinkService +
 * SiteFinder router) without importing its configuration DTO. Parsing of the
 * typolink target/title/class parts is a later enhancement.
 */
final class TypoLinkResolver
{
    public function __construct(
        private readonly LinkService $linkService,
        private readonly SiteFinder $siteFinder,
    ) {
    }

    /**
     * @return array{href: string, type: string, target: null, title: null}|null
     */
    public function resolve(string $typolink): ?array
    {
        if (trim($typolink) === '') {
            return null;
        }

        try {
            $details = $this->linkService->resolve($typolink);
        } catch (\Exception) {
            return null;
        }

        $type = (string)($details['type'] ?? LinkService::TYPE_UNKNOWN);
        $href = match ($type) {
            LinkService::TYPE_URL => $details['url'] ?? null,
            LinkService::TYPE_EMAIL => !empty($details['email']) ? 'mailto:' . $details['email'] : null,
            LinkService::TYPE_TELEPHONE => !empty($details['telephone']) ? 'tel:' . $details['telephone'] : null,
            LinkService::TYPE_PAGE => $this->resolvePageUrl($details),
            default => null,
        };

        if (!is_string($href) || $href === '') {
            return null;
        }

        return [
            'href' => $href,
            'type' => $this->mapType($type),
            'target' => null,
            'title' => null,
        ];
    }

    private function mapType(string $type): string
    {
        return match ($type) {
            LinkService::TYPE_PAGE => 'page',
            LinkService::TYPE_EMAIL => 'mail',
            LinkService::TYPE_TELEPHONE => 'tel',
            LinkService::TYPE_FILE => 'file',
            default => 'url',
        };
    }

    /**
     * @param array<string, mixed> $details
     */
    private function resolvePageUrl(array $details): ?string
    {
        $pageId = (int)($details['pageuid'] ?? 0);
        if ($pageId <= 0) {
            return null;
        }

        try {
            $queryParams = [];
            if (!empty($details['parameters'])) {
                parse_str(ltrim((string)$details['parameters'], '&?'), $queryParams);
            }

            $site = $this->siteFinder->getSiteByPageId($pageId);
            $uri = $site->getRouter()->generateUri(
                $pageId,
                $queryParams,
                (string)($details['fragment'] ?? ''),
                RouterInterface::ABSOLUTE_URL,
            );

            return (string)$uri;
        } catch (\Exception) {
            return null;
        }
    }
}
