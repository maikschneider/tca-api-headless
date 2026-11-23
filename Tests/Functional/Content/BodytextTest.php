<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Content;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Serializes the content fixtures through the show endpoint the content_show
 * fixture extension adds to headless_content.
 */
final class BodytextTest extends AbstractApiTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
        __DIR__ . '/../Fixtures/Extensions/content_show',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tt_content', 'sys_file_storage', 'sys_file'] as $table) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $table . '.csv');
        }
    }

    #[Test]
    public function richTextBecomesPortableTextWithAbsoluteLinks(): void
    {
        $content = $this->content('http://localhost/_api/headless_content/1');

        $blocks = $content['bodytext'];
        self::assertSame(
            [['h2', null, 'Intro'], ['normal', null, null], ['normal', 'bullet', 'one'], ['normal', 'bullet', 'two']],
            array_map(static fn (array $block): array => [
                $block['style'],
                $block['listItem'] ?? null,
                \count($block['children']) === 1 ? $block['children'][0]['text'] : null,
            ], $blocks),
        );

        $links = array_column($blocks[1]['markDefs'], 'href', '_key');
        self::assertSame([
            'http://localhost/about',
            'http://localhost/contact',
            'http://localhost/fileadmin/brochure.pdf',
            'mailto:info@example.org',
        ], array_values($links));

        $spans = array_map(
            static fn (array $span): array => [implode(',', array_map(static fn (string $mark): string => $links[$mark] ?? $mark, $span['marks'])), $span['text']],
            $blocks[1]['children'],
        );
        self::assertSame([
            ['', 'Hello '],
            ['strong', 'world'],
            ['', ', see '],
            ['http://localhost/about', 'About'],
            ['', ', a missing page, '],
            ['http://localhost/contact', 'contact'],
            ['', ', '],
            ['http://localhost/fileadmin/brochure.pdf', 'the brochure'],
            ['', ', '],
            ['mailto:info@example.org', 'mail'],
            ['', ' and no script.'],
        ], $spans);
    }

    #[Test]
    public function textAndMediaBodytextIsPortableText(): void
    {
        $content = $this->content('http://localhost/_api/headless_content/2');

        self::assertSame([['_key' => 'k0', '_type' => 'link', 'href' => 'https://example.org/article']], $content['bodytext'][0]['markDefs']);
        self::assertSame(['Read ', 'the article', '.'], array_column($content['bodytext'][0]['children'], 'text'));
    }

    #[Test]
    public function pageLinksFollowTheRequestLanguage(): void
    {
        $content = $this->content('http://localhost/de/_api/headless_content/1');

        self::assertSame('Rich text DE', $content['header']);
        self::assertSame(['http://localhost/de/ueber-uns'], array_column($content['bodytext'][0]['markDefs'], 'href'));
    }

    #[Test]
    public function bulletsBecomeItems(): void
    {
        $bullets = $this->content('http://localhost/_api/headless_content/3');
        self::assertSame(0, $bullets['bullets_type']);
        self::assertSame(['one', 'two', 'three'], $bullets['bodytext']);

        $definitions = $this->content('http://localhost/_api/headless_content/4');
        self::assertSame(2, $definitions['bullets_type']);
        self::assertSame([
            ['term' => 'Term A', 'definition' => 'Definition A'],
            ['term' => 'Term B', 'definition' => 'Definition B'],
        ], $definitions['bodytext']);
    }

    #[Test]
    public function tablesBecomeRows(): void
    {
        $table = $this->content('http://localhost/_api/headless_content/5');
        self::assertSame('Ages', $table['table_caption']);
        self::assertSame(1, $table['table_header_position']);
        self::assertSame(['head' => ['Name', 'Age'], 'body' => [['Anna', '30'], ['Ben', '40']], 'foot' => ['Sum', '70']], $table['bodytext']);

        $semicolons = $this->content('http://localhost/_api/headless_content/6');
        self::assertSame(['head' => [], 'body' => [['a;b', 'c'], ['1', '2']], 'foot' => []], $semicolons['bodytext']);
    }

    #[Test]
    public function typesWithoutBodytextDontSendIt(): void
    {
        self::assertArrayNotHasKey('bodytext', $this->content('http://localhost/_api/headless_content/7'));
        self::assertSame([], $this->content('http://localhost/_api/headless_content/8')['bodytext']);
    }

    #[Test]
    public function noResponseContainsMarkup(): void
    {
        $urls = ['http://localhost/de/_api/headless_content/1'];
        foreach (range(1, 8) as $uid) {
            $urls[] = 'http://localhost/_api/headless_content/' . $uid;
        }

        foreach ($urls as $url) {
            $strings = [];
            $content = $this->content($url);
            array_walk_recursive($content, static function (mixed $value) use (&$strings): void {
                if (\is_string($value)) {
                    $strings[] = $value;
                }
            });
            foreach ($strings as $string) {
                self::assertDoesNotMatchRegularExpression('/<\/?[a-z][^>]*>/i', $string, $url);
            }
        }
    }

    #[Test]
    public function theBodytextIsTypedPerContentType(): void
    {
        $schemas = $this->openApiDocument('main')['components']['schemas'];

        $bodytext = static fn (string $type): ?array => $schemas['HeadlessContent' . $type . 'Read']['properties']['bodytext'] ?? null;
        self::assertSame(['$ref' => '#/components/schemas/PortableText'], $bodytext('Text'));
        self::assertSame(['$ref' => '#/components/schemas/PortableText'], $bodytext('Textmedia'));
        self::assertSame(['$ref' => '#/components/schemas/HeadlessBulletList'], $bodytext('Bullets'));
        self::assertSame(['$ref' => '#/components/schemas/HeadlessTable'], $bodytext('Table'));
        self::assertNull($bodytext('Header'));

        foreach (['PortableText', 'PortableTextBlock', 'PortableTextSpan', 'PortableTextLink', 'HeadlessBulletList', 'HeadlessTable'] as $name) {
            self::assertArrayHasKey($name, $schemas);
        }
        self::assertSame(['$ref' => '#/components/schemas/PortableTextLink'], $schemas['PortableTextBlock']['properties']['markDefs']['items']);
    }

    #[Test]
    public function aSiteWithoutTheAddOnGetsNoBodytextSchemas(): void
    {
        $schemas = $this->openApiDocument('other')['components']['schemas'] ?? [];

        self::assertArrayNotHasKey('HeadlessContentRead', $schemas);
        self::assertArrayNotHasKey('PortableText', $schemas);
        self::assertArrayNotHasKey('HeadlessTable', $schemas);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(string $url): array
    {
        $response = $this->request($url);
        self::assertSame(200, $response->getStatusCode(), $url . ': ' . $response->getBody());

        return $this->json($response);
    }
}
