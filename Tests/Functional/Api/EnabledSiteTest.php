<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Api;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class EnabledSiteTest extends AbstractApiTestCase
{
    #[Test]
    public function aPageOfTheSiteIsServed(): void
    {
        $response = $this->request('http://localhost/_api/headless_pages/2');

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame('application/ld+json', $response->getHeaderLine('Content-Type'));
        self::assertSame([
            '@type' => 'HeadlessPages',
            '@id' => '/_api/headless_pages/2',
            'uid' => 2,
            'title' => 'About',
            'slug' => '/about',
            'seo_title' => '',
            'description' => null,
            'no_index' => 0,
            'no_follow' => 0,
            'canonical_link' => null,
            'og_title' => '',
            'og_description' => null,
            'og_image' => [],
            'meta' => [
                'robots' => 'index,follow',
                'canonical' => 'http://localhost/about',
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'url' => 'http://localhost/about',
                    'name' => 'About',
                    'inLanguage' => 'en-US',
                ],
            ],
            'regions' => [],
        ], $this->json($response));
    }

    #[Test]
    public function theSiteRootIsServed(): void
    {
        $response = $this->request('http://localhost/_api/headless_pages/1');

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame('Home', $this->json($response)['title']);
    }

    #[Test]
    public function aNavigationRootOfTheSiteIsServed(): void
    {
        $response = $this->request('http://localhost/_api/headless_navigation/2');

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame([
            '@type' => 'HeadlessNavigation',
            '@id' => '/_api/headless_navigation/2',
            'uid' => 2,
            'title' => 'About',
            'nav_title' => 'About us',
            'slug' => '/about',
            'doktype' => 1,
            'url' => 'http://localhost/about',
            'children' => [],
        ], $this->json($response));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pageResources(): array
    {
        return [
            'pages' => ['headless_pages'],
            'navigation' => ['headless_navigation'],
        ];
    }

    #[Test]
    #[DataProvider('pageResources')]
    public function aPageOfAnotherSiteAnswersLikeAMissingPage(string $resource): void
    {
        $missing = $this->request('http://localhost/_api/' . $resource . '/999');
        self::assertSame(404, $missing->getStatusCode());

        foreach ([10, 11] as $uid) {
            $response = $this->request('http://localhost/_api/' . $resource . '/' . $uid);
            self::assertSame(404, $response->getStatusCode(), 'uid ' . $uid);
            self::assertSame((string)$missing->getBody(), (string)$response->getBody(), 'uid ' . $uid);
        }
    }

    #[Test]
    #[DataProvider('pageResources')]
    public function aSysfolderIsNotServed(string $resource): void
    {
        self::assertSame(404, $this->request('http://localhost/_api/' . $resource . '/3')->getStatusCode());
    }

    #[Test]
    public function theNavigationCannotBeListed(): void
    {
        self::assertSame(405, $this->request('http://localhost/_api/headless_navigation?path=/')->getStatusCode());
    }

    #[Test]
    public function contentHasNoEndpointOfItsOwn(): void
    {
        self::assertSame(405, $this->request('http://localhost/_api/headless_content')->getStatusCode());
        self::assertSame(405, $this->request('http://localhost/_api/headless_content/1')->getStatusCode());
    }

    #[Test]
    public function responsesAreNotCached(): void
    {
        foreach (['headless_pages/1', 'headless_navigation/1'] as $path) {
            $response = $this->request('http://localhost/_api/' . $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertFalse($response->hasHeader('X-TCA-API-Cache'), $path);
        }

        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('cache_tca_api')->count('*', 'cache_tca_api', []));
    }

    #[Test]
    public function theResourcesAreDocumented(): void
    {
        $document = $this->openApiDocument('main');

        self::assertArrayHasKey('/_api/headless_pages/{uid}', $document['paths']);
        self::assertArrayHasKey('/_api/headless_navigation/{uid}', $document['paths']);
        self::assertArrayHasKey('HeadlessContentRead', $document['components']['schemas']);
    }
}
