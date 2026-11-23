<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Processing;

use MaikSchneider\TcaApi\Configuration\ColumnDefinition;
use MaikSchneider\TcaApi\Serializer\Processing\LinkLanguageResolver;
use MaikSchneider\TcaApi\Serializer\Processing\TypoLinkProcessor;
use MaikSchneider\TcaApiHeadless\Processing\BodytextProcessor;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BodytextProcessorTest extends UnitTestCase
{
    private BodytextProcessor $subject;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // CsvUtility uses a base constant the TYPO3 bootstrap defines; unit tests share
        // the functional bootstrap, which defines it only once a test instance boots.
        if (!\defined('LF')) {
            \define('LF', "\n");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $linkService = $this->createMock(LinkService::class);
        $this->subject = new BodytextProcessor(
            new HtmlToPortableText(),
            new TypoLinkProcessor($linkService, $this->createMock(SiteFinder::class), new LinkLanguageResolver($this->createMock(ConnectionPool::class))),
            $linkService,
        );
    }

    /**
     * @return array<string, array{string, int, list<mixed>}>
     */
    public static function bulletLists(): array
    {
        return [
            'one item per line' => ["one\ntwo", 0, ['one', 'two']],
            'any line break, trimmed, blank lines dropped' => ["  one \r\n\r\ntwo\rthree\n", 1, ['one', 'two', 'three']],
            'empty' => ['', 0, []],
            'definition list' => ["Term A|Definition A\nTerm B|Definition B", 2, [
                ['term' => 'Term A', 'definition' => 'Definition A'],
                ['term' => 'Term B', 'definition' => 'Definition B'],
            ]],
            'definition list: enclosed pipe, missing definition' => ["\"A|B\"|Definition\n\nTerm only", 2, [
                ['term' => 'A|B', 'definition' => 'Definition'],
                ['term' => 'Term only', 'definition' => ''],
            ]],
        ];
    }

    /**
     * @param list<mixed> $expected
     */
    #[Test]
    #[DataProvider('bulletLists')]
    public function bulletsBecomeItems(string $bodytext, int $bulletsType, array $expected): void
    {
        self::assertSame($expected, $this->process($bodytext, ['CType' => 'bullets', 'bullets_type' => $bulletsType]));
    }

    /**
     * @return array<string, array{string, array<string, int>, array{head: list<string>, body: list<list<string>>, foot: list<string>}}>
     */
    public static function tables(): array
    {
        return [
            'default delimiter, no header' => ["a|b\n1|2", [], ['head' => [], 'body' => [['a', 'b'], ['1', '2']], 'foot' => []]],
            'header on top' => ["a|b\n1|2", ['table_header_position' => 1], ['head' => ['a', 'b'], 'body' => [['1', '2']], 'foot' => []]],
            'header on the left stays in the body' => ["a|b\n1|2", ['table_header_position' => 2], ['head' => [], 'body' => [['a', 'b'], ['1', '2']], 'foot' => []]],
            'footer' => ["a|b\n1|2\nx|y", ['table_header_position' => 1, 'table_tfoot' => 1], ['head' => ['a', 'b'], 'body' => [['1', '2']], 'foot' => ['x', 'y']]],
            'a single row is never a footer' => ['a|b', ['table_tfoot' => 1], ['head' => [], 'body' => [['a', 'b']], 'foot' => []]],
            'semicolon' => ["a;b\n1;2", ['table_delimiter' => 59], ['head' => [], 'body' => [['a', 'b'], ['1', '2']], 'foot' => []]],
            'comma' => ['a,b', ['table_delimiter' => 44], ['head' => [], 'body' => [['a', 'b']], 'foot' => []]],
            'colon' => ['a:b', ['table_delimiter' => 58], ['head' => [], 'body' => [['a', 'b']], 'foot' => []]],
            'tab' => ["a\tb", ['table_delimiter' => 9], ['head' => [], 'body' => [['a', 'b']], 'foot' => []]],
            'double-quote enclosure keeps delimiters and line breaks' => ["\"a|b\"|\"c\nd\"", ['table_enclosure' => 34], ['head' => [], 'body' => [['a|b', "c\nd"]], 'foot' => []]],
            'single-quote enclosure' => ["'a|b'|c", ['table_enclosure' => 39], ['head' => [], 'body' => [['a|b', 'c']], 'foot' => []]],
            'no enclosure keeps quotes' => ['"a"|b', ['table_enclosure' => 0], ['head' => [], 'body' => [['"a"', 'b']], 'foot' => []]],
            'br is a line break' => ['a<br>b|c<br />d', [], ['head' => [], 'body' => [["a\nb", "c\nd"]], 'foot' => []]],
            'short rows are padded, blank rows dropped' => ["a|b|c\n\n1", [], ['head' => [], 'body' => [['a', 'b', 'c'], ['1', '', '']], 'foot' => []]],
            'cols limits the columns' => ["a|b|c\n1", ['cols' => 2], ['head' => [], 'body' => [['a', 'b'], ['1', '']], 'foot' => []]],
            'empty' => ['', ['table_header_position' => 1, 'table_tfoot' => 1], ['head' => [], 'body' => [], 'foot' => []]],
        ];
    }

    /**
     * @param array<string, int> $settings
     * @param array{head: list<string>, body: list<list<string>>, foot: list<string>} $expected
     */
    #[Test]
    #[DataProvider('tables')]
    public function tableRowsFollowTheTableSettings(string $bodytext, array $settings, array $expected): void
    {
        self::assertSame($expected, $this->process($bodytext, ['CType' => 'table', ...$settings]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function richTextTypes(): array
    {
        return [
            'text' => ['text'],
            'textmedia' => ['textmedia'],
            'any other type' => ['custom'],
        ];
    }

    #[Test]
    #[DataProvider('richTextTypes')]
    public function otherTypesBecomePortableText(string $cType): void
    {
        $result = $this->process('<p>Hello</p>', ['CType' => $cType]);

        self::assertIsArray($result);
        self::assertSame('block', $result[0]['_type']);
        self::assertSame('Hello', $result[0]['children'][0]['text']);
    }

    #[Test]
    public function linksWithoutAnAbsoluteUrlLoseTheirAnnotation(): void
    {
        $result = $this->process(
            '<p><a href="https://example.org/">web</a> <a href="mailto:a@example.org">mail</a> <a href="tel:+49123">phone</a>'
            . ' <a href="javascript:alert(1)">script</a> <a href="#top">anchor</a> <a href="/team">relative</a> <a href="ftp://example.org/">ftp</a></p>',
            ['CType' => 'text'],
        );

        self::assertIsArray($result);
        self::assertSame(['https://example.org/', 'mailto:a@example.org', 'tel:+49123'], array_column($result[0]['markDefs'], 'href'));
        self::assertSame('web mail phone script anchor relative ftp', implode('', array_column($result[0]['children'], 'text')));
    }

    /**
     * @param array<string, mixed> $rawRow
     */
    private function process(string $bodytext, array $rawRow): mixed
    {
        return $this->subject->process($bodytext, ColumnDefinition::fromArray(['groups' => ['show']]), ['rawRow' => $rawRow, 'request' => null]);
    }
}
