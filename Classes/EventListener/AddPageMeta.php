<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\EventListener;

use MaikSchneider\TcaApi\Event\AfterOperationEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Adds robots, canonical URL and a schema.org WebPage to a headless_pages
 * show response, built from its serialized columns only.
 */
#[AsEventListener('tca-api-headless/add-page-meta')]
final class AddPageMeta
{
    public function __invoke(AfterOperationEvent $event): void
    {
        $request = $event->getRequest();
        $site = $request->getAttribute('site');
        $language = $request->getAttribute('language');
        if ($event->getOperation() !== 'show' || $event->getConfig()->resourceName !== 'headless_pages' || !$site instanceof Site || !$language instanceof SiteLanguage) {
            return;
        }

        $data = $event->getData();
        $noIndex = (bool)($data['no_index'] ?? false);
        $url = (string)$site->getRouter()->generateUri(
            (int)$data['uid'],
            ['_language' => $language->getLanguageId()],
            '',
            RouterInterface::ABSOLUTE_URL,
        );
        // As in EXT:seo, a page that must not be indexed gets no canonical URL.
        $canonical = $noIndex ? null : $this->httpUrl($data['canonical_link']['href'] ?? null) ?? $url;

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'url' => $canonical ?? $url,
            'name' => (string)(($data['seo_title'] ?? '') ?: ($data['title'] ?? '')),
            'inLanguage' => $language->getHreflang(),
        ];
        if (($data['description'] ?? '') !== '') {
            $schema['description'] = (string)$data['description'];
        }

        $data['meta'] = [
            'robots' => ($noIndex ? 'noindex' : 'index') . ',' . (($data['no_follow'] ?? false) ? 'nofollow' : 'follow'),
            'canonical' => $canonical,
            'schema' => $schema,
        ];
        $event->setData($data);
    }

    /**
     * The resolved canonical_link if it is a web URL; a mail, phone or record
     * link, or a page unavailable in the request language, is none.
     */
    private function httpUrl(mixed $link): ?string
    {
        return is_string($link) && preg_match('#^https?://#i', $link) === 1 ? $link : null;
    }
}
