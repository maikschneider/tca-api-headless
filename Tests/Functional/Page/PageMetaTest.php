<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Page;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pages 40–44 of Fixtures/pages_meta.csv and their German translations;
 * page 41 has an og_image.
 */
final class PageMetaTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_meta', 'sys_file_storage', 'sys_file', 'sys_file_metadata', 'sys_file_reference'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
        if (!is_dir($this->instancePath . '/fileadmin')) {
            mkdir($this->instancePath . '/fileadmin');
        }
        copy(__DIR__ . '/../Fixtures/Files/og-image.png', $this->instancePath . '/fileadmin/og-image.png');
    }

    #[Test]
    public function withoutCanonicalLinkTheCanonicalIsTheAbsolutePageUrlInTheRequestLanguage(): void
    {
        self::assertSame([
            'robots' => 'index,follow',
            'canonical' => 'http://localhost/meta-defaults',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'url' => 'http://localhost/meta-defaults',
                'name' => 'Meta defaults',
                'inLanguage' => 'en-US',
            ],
        ], $this->meta('http://localhost/_api/headless_pages/40'));

        self::assertSame([
            'robots' => 'index,follow',
            'canonical' => 'http://localhost/de/meta-standard',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'url' => 'http://localhost/de/meta-standard',
                'name' => 'Meta Standard',
                'inLanguage' => 'de-DE',
            ],
        ], $this->meta('http://localhost/de/_api/headless_pages/40'));
    }

    #[Test]
    public function robotsReflectNoIndexAndNoFollow(): void
    {
        self::assertSame('noindex,nofollow', $this->meta('http://localhost/_api/headless_pages/42')['robots']);
        self::assertSame('noindex,nofollow', $this->meta('http://localhost/de/_api/headless_pages/42')['robots']);
        self::assertSame('index,nofollow', $this->meta('http://localhost/_api/headless_pages/44')['robots']);
        self::assertSame('index,nofollow', $this->meta('http://localhost/de/_api/headless_pages/44')['robots']);
    }

    #[Test]
    public function aPageThatMustNotBeIndexedHasNoCanonical(): void
    {
        $meta = $this->meta('http://localhost/_api/headless_pages/42');

        self::assertNull($meta['canonical']);
        self::assertSame('http://localhost/not-indexed', $meta['schema']['url']);
    }

    #[Test]
    public function anExplicitCanonicalLinkWins(): void
    {
        self::assertSame([
            'robots' => 'index,follow',
            'canonical' => 'https://example.com/canonical',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'url' => 'https://example.com/canonical',
                'name' => 'Explicit SEO title',
                'inLanguage' => 'en-US',
                'description' => 'Explicit description',
            ],
        ], $this->meta('http://localhost/_api/headless_pages/41'));
    }

    #[Test]
    public function aCanonicalPageLinkFollowsTheRequestLanguage(): void
    {
        self::assertSame('http://localhost/about', $this->meta('http://localhost/_api/headless_pages/43')['canonical']);
        self::assertSame('http://localhost/de/ueber-uns', $this->meta('http://localhost/de/_api/headless_pages/43')['canonical']);
    }

    #[Test]
    public function aCanonicalPageLinkUnavailableInTheRequestLanguageFallsBackToThePageUrl(): void
    {
        self::assertSame('http://localhost/meta-explicit', $this->meta('http://localhost/_api/headless_pages/44')['canonical']);
        self::assertSame('http://localhost/de/nicht-gefolgt', $this->meta('http://localhost/de/_api/headless_pages/44')['canonical']);
    }

    #[Test]
    public function aCanonicalLinkThatIsNoWebUrlFallsBackToThePageUrl(): void
    {
        self::assertSame('http://localhost/record-canonical', $this->meta('http://localhost/_api/headless_pages/49')['canonical']);
    }

    #[Test]
    public function theCanonicalLinkCarriesItsTargetAndTitle(): void
    {
        self::assertNull($this->page('http://localhost/_api/headless_pages/40')['canonical_link']);
        self::assertSame(
            ['href' => 'https://example.com/canonical', 'target' => null, 'title' => 'Canonical title'],
            $this->page('http://localhost/_api/headless_pages/41')['canonical_link'],
        );
        self::assertSame(
            ['href' => 'http://localhost/about', 'target' => '_top', 'title' => null],
            $this->page('http://localhost/_api/headless_pages/43')['canonical_link'],
        );
        self::assertSame(
            ['href' => 'http://localhost/de/ueber-uns', 'target' => '_top', 'title' => null],
            $this->page('http://localhost/de/_api/headless_pages/43')['canonical_link'],
        );
        self::assertNull($this->page('http://localhost/_api/headless_pages/49')['canonical_link']);
    }

    #[Test]
    public function theOpenGraphImageHasAnAbsoluteUrl(): void
    {
        $images = $this->page('http://localhost/_api/headless_pages/41')['og_image'];

        self::assertCount(1, $images);
        self::assertMatchesRegularExpression('#^http://localhost/fileadmin/\S+\.png$#', $images[0]['publicUrl']);
        self::assertSame('Open Graph image', $images[0]['metadata']['alternative']);
        self::assertSame([40, 21], [$images[0]['width'], $images[0]['height']]);
    }

    #[Test]
    public function theMetaIsTyped(): void
    {
        $schemas = $this->openApiDocument('main')['components']['schemas'];

        self::assertSame(['$ref' => '#/components/schemas/HeadlessPagesMeta'], $schemas['HeadlessPagesRead']['properties']['meta']);
        self::assertSame(['robots', 'canonical', 'schema'], $schemas['HeadlessPagesMeta']['required']);
        self::assertSame(
            ['@context', '@type', 'url', 'name', 'inLanguage', 'description'],
            array_keys($schemas['HeadlessPagesMeta']['properties']['schema']['properties']),
        );
        self::assertSame(['$ref' => '#/components/schemas/FileObject'], $schemas['HeadlessPagesRead']['properties']['og_image']['items']);
    }

    #[Test]
    public function aSiteWithoutTheSetHasNoMetaSchema(): void
    {
        self::assertArrayNotHasKey('HeadlessPagesMeta', $this->openApiDocument('other')['components']['schemas']);
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(string $url): array
    {
        return $this->page($url)['meta'];
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $url): array
    {
        $response = $this->request($url);
        self::assertSame(200, $response->getStatusCode(), $url . ': ' . $response->getBody());

        return $this->json($response);
    }
}
