<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Navigation;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The number of page levels below the requested page in a navigation
 * response: the depth query parameter capped at the site's maximum, or the
 * site's default without it.
 */
final class NavigationDepth
{
    public const PARAMETER = 'depth';

    public static function isValid(ServerRequestInterface $request): bool
    {
        return !\array_key_exists(self::PARAMETER, $request->getQueryParams()) || self::requested($request) !== null;
    }

    public static function levels(ServerRequestInterface $request, Site $site): int
    {
        $settings = $site->getSettings();
        $requested = self::requested($request);
        if ($requested === null) {
            return (int)$settings->get('tca_api_headless.navigation.depth', 2);
        }

        return min($requested, (int)$settings->get('tca_api_headless.navigation.maxDepth', 5));
    }

    private static function requested(ServerRequestInterface $request): ?int
    {
        $value = $request->getQueryParams()[self::PARAMETER] ?? null;
        if (!\is_string($value) || preg_match('/^\d+$/', $value) !== 1 || (int)$value < 1) {
            return null;
        }

        return (int)$value;
    }
}
