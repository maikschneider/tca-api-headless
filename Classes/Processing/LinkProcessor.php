<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Processing;

use MaikSchneider\TcaApi\Configuration\ColumnDefinition;
use MaikSchneider\TcaApi\Serializer\Processing\ColumnProcessorInterface;
use MaikSchneider\TcaApi\Serializer\Processing\LinkLanguageResolver;
use MaikSchneider\TcaApi\Serializer\Processing\TypoLinkProcessor;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Resource\File;

/**
 * Sends a typolink column as {href, target, title}, or null when its URL doesn't
 * resolve to an absolute http(s), mailto or tel URL. Target and title are null
 * when the typolink doesn't set them.
 */
final readonly class LinkProcessor implements ColumnProcessorInterface
{
    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function __construct(
        private TypoLinkCodecService $typoLinkCodecService,
        private TypoLinkProcessor $typoLinkProcessor,
        private LinkService $linkService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{href: string, target: ?string, title: ?string}|null
     */
    public function process(mixed $value, ColumnDefinition $config, array $context): ?array
    {
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = $this->typoLinkCodecService->decode($value);
        $href = $this->url($parts['url'], $config, $context);
        if ($href === null) {
            return null;
        }

        return [
            'href' => $href,
            'target' => $parts['target'] !== '' ? $parts['target'] : null,
            'title' => $parts['title'] !== '' ? $parts['title'] : null,
        ];
    }

    /**
     * Resolves a link URL like a typolink field (pages in the request language) and
     * makes file and root-relative links absolute against the request host.
     *
     * @param array<string, mixed> $context
     */
    public function url(string $url, ColumnDefinition $config, array $context): ?string
    {
        $url = $this->typoLinkProcessor->process($url, $config, $context);
        if (\is_string($url) && str_starts_with($url, 't3://')) {
            $url = $this->fileUrl($url);
        }
        if (!\is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $normalizedParams = LinkLanguageResolver::contextRequest($context)?->getAttribute('normalizedParams');
            if (!$normalizedParams instanceof NormalizedParams) {
                return null;
            }
            $url = $normalizedParams->getRequestHost() . $url;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, self::LINK_SCHEMES, true) ? $url : null;
    }

    private function fileUrl(string $link): ?string
    {
        try {
            $file = $this->linkService->resolve($link)['file'] ?? null;
        } catch (\Exception) {
            return null;
        }
        if (!$file instanceof File) {
            return null;
        }

        $publicUrl = $file->getPublicUrl();
        if ($publicUrl === null || parse_url($publicUrl, PHP_URL_SCHEME) !== null) {
            return $publicUrl;
        }

        return '/' . ltrim($publicUrl, '/');
    }
}
