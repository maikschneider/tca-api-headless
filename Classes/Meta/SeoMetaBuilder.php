<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Meta;

use MaikSchneider\TcaApiHeadless\Link\TypoLinkResolver;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Builds the `seo` and `schema` (schema.org) sections of a page's meta from the
 * page record and EXT:seo fields.
 */
final class SeoMetaBuilder
{
    public function __construct(
        private readonly TypoLinkResolver $typoLinkResolver,
        private readonly FileRepository $fileRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $page
     * @return array{seo: array<string, mixed>, schema: array<string, mixed>}
     */
    public function build(array $page, SiteLanguage $language): array
    {
        $title = (string)($page['title'] ?? '');
        $seoTitle = ($page['seo_title'] ?? '') !== '' ? (string)$page['seo_title'] : $title;
        $description = (string)($page['description'] ?? '');

        $robots = ((int)($page['no_index'] ?? 0) === 1 ? 'noindex' : 'index')
            . ',' . ((int)($page['no_follow'] ?? 0) === 1 ? 'nofollow' : 'follow');

        $seo = array_filter([
            'title' => $seoTitle,
            'description' => $description !== '' ? $description : null,
            'canonical' => $this->canonical($page),
            'robots' => $robots,
            'og' => $this->openGraph($page, $seoTitle, $description),
        ], static fn ($value): bool => $value !== null && $value !== []);

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description !== '' ? $description : null,
        ], static fn ($value): bool => $value !== null);

        return ['seo' => $seo, 'schema' => $schema];
    }

    /**
     * @param array<string, mixed> $page
     */
    private function canonical(array $page): ?string
    {
        $link = (string)($page['canonical_link'] ?? '');
        if ($link !== '') {
            $resolved = $this->typoLinkResolver->resolve($link);
            if ($resolved !== null) {
                return $resolved['href'];
            }
        }

        $slug = (string)($page['slug'] ?? '');
        return $slug !== '' ? $slug : null;
    }

    /**
     * @param array<string, mixed> $page
     * @return array<string, mixed>
     */
    private function openGraph(array $page, string $fallbackTitle, string $fallbackDescription): array
    {
        $og = [
            'title' => ($page['og_title'] ?? '') !== '' ? (string)$page['og_title'] : $fallbackTitle,
            'description' => ($page['og_description'] ?? '') !== ''
                ? (string)$page['og_description']
                : ($fallbackDescription !== '' ? $fallbackDescription : null),
            'image' => $this->ogImage((int)($page['uid'] ?? 0)),
        ];

        return array_filter($og, static fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ogImage(int $pageId): ?array
    {
        foreach ($this->fileRepository->findByRelation('pages', 'og_image', $pageId) as $reference) {
            if (!$reference instanceof FileReference) {
                continue;
            }

            $url = (string)($reference->getPublicUrl() ?? '');
            if ($url !== '' && !str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
                $url = '/' . $url;
            }

            $width = $reference->getProperty('width');
            $height = $reference->getProperty('height');

            return [
                'src' => $url,
                'width' => is_numeric($width) ? (int)$width : null,
                'height' => is_numeric($height) ? (int)$height : null,
            ];
        }

        return null;
    }
}
