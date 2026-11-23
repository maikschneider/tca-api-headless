<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\RichText;

use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class HtmlToPortableTextTest extends UnitTestCase
{
    private HtmlToPortableText $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new HtmlToPortableText();
    }

    #[Test]
    public function blankInputYieldsNoBlocks(): void
    {
        self::assertSame([], $this->subject->convert(" \n "));
        self::assertSame([], $this->subject->convert('<p> </p>'));
    }

    #[Test]
    public function aParagraphBecomesANormalBlock(): void
    {
        self::assertSame([
            [
                '_type' => 'block',
                '_key' => 'k0',
                'style' => 'normal',
                'markDefs' => [],
                'children' => [
                    ['_type' => 'span', '_key' => 'k1', 'marks' => [], 'text' => 'Hello world'],
                ],
            ],
        ], $this->subject->convert('<p>Hello world</p>'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function blockStyles(): array
    {
        return [
            'h1' => ['<h1>x</h1>', 'h1'],
            'h2' => ['<h2>x</h2>', 'h2'],
            'h6' => ['<h6>x</h6>', 'h6'],
            'blockquote' => ['<blockquote>x</blockquote>', 'blockquote'],
        ];
    }

    #[Test]
    #[DataProvider('blockStyles')]
    public function blockElementsKeepTheirStyle(string $html, string $style): void
    {
        $result = $this->subject->convert($html);

        self::assertCount(1, $result);
        self::assertSame($style, $result[0]['style']);
        self::assertSame('x', $result[0]['children'][0]['text']);
    }

    #[Test]
    public function inlineFormattingBecomesDecorators(): void
    {
        $result = $this->subject->convert('<p>a <strong>b</strong> <em>c</em> <b><i>d</i></b> <u>e</u> <s>f</s> <del>g</del> <code>h</code></p>');

        self::assertSame([
            [[], 'a '],
            [['strong'], 'b'],
            [[], ' '],
            [['em'], 'c'],
            [[], ' '],
            [['strong', 'em'], 'd'],
            [[], ' '],
            [['underline'], 'e'],
            [[], ' '],
            [['strike-through'], 'f'],
            [[], ' '],
            [['strike-through'], 'g'],
            [[], ' '],
            [['code'], 'h'],
        ], $this->spans($result[0]));
    }

    #[Test]
    public function aLinkBecomesAnAnnotation(): void
    {
        $result = $this->subject->convert('<p>see <a href="https://example.org/team">the <strong>team</strong></a></p>');

        self::assertSame([['_key' => 'k0', '_type' => 'link', 'href' => 'https://example.org/team']], $result[0]['markDefs']);
        self::assertSame([
            [[], 'see '],
            [['k0'], 'the '],
            [['k0', 'strong'], 'team'],
        ], $this->spans($result[0]));
    }

    #[Test]
    public function theLinkResolverMapsEveryHref(): void
    {
        $result = $this->subject->convert(
            '<p><a href="t3://page?uid=2">About</a> and <a href="t3://page?uid=999">gone</a></p>',
            static fn (string $href): ?string => $href === 't3://page?uid=2' ? 'https://example.org/about' : null,
        );

        self::assertSame([['_key' => 'k0', '_type' => 'link', 'href' => 'https://example.org/about']], $result[0]['markDefs']);
        self::assertSame([
            [['k0'], 'About'],
            [[], ' and gone'],
        ], $this->spans($result[0]));
    }

    #[Test]
    public function aLinkWithoutHrefKeepsItsText(): void
    {
        $result = $this->subject->convert('<p><a name="top">Top</a></p>');

        self::assertSame([], $result[0]['markDefs']);
        self::assertSame([[[], 'Top']], $this->spans($result[0]));
    }

    #[Test]
    public function listsBecomeListItems(): void
    {
        $result = $this->subject->convert('<ul><li>one</li><li>two<ol><li>two.a</li></ol></li></ul><ol><li>first</li></ol>');

        self::assertSame([
            ['bullet', 1, 'one'],
            ['bullet', 1, 'two'],
            ['number', 2, 'two.a'],
            ['number', 1, 'first'],
        ], array_map(
            static fn (array $block): array => [$block['listItem'], $block['level'], $block['children'][0]['text']],
            $result,
        ));
    }

    #[Test]
    public function aLineBreakBecomesANewline(): void
    {
        $result = $this->subject->convert("<p>line1<br>\n  line2</p>");

        self::assertSame([[[], "line1\nline2"]], $this->spans($result[0]));
    }

    #[Test]
    public function sourceWhitespaceCollapsesLikeHtml(): void
    {
        $result = $this->subject->convert("\n<p>\n  a\n  <strong> b </strong>  c\n</p>\n\n<p>d</p>\n");

        self::assertCount(2, $result);
        self::assertSame([[[], 'a '], [['strong'], 'b '], [[], 'c']], $this->spans($result[0]));
        self::assertSame([[[], 'd']], $this->spans($result[1]));
    }

    #[Test]
    public function inlineContentOutsideABlockBecomesANormalBlock(): void
    {
        $result = $this->subject->convert('loose <em>text</em><p>para</p>tail');

        self::assertSame(['normal', 'normal', 'normal'], array_column($result, 'style'));
        self::assertSame([[[], 'loose '], [['em'], 'text']], $this->spans($result[0]));
        self::assertSame([[[], 'para']], $this->spans($result[1]));
        self::assertSame([[[], 'tail']], $this->spans($result[2]));
    }

    #[Test]
    public function containersAreUnwrapped(): void
    {
        $result = $this->subject->convert('<div class="x"><h2>Title</h2><p>Body</p></div>');

        self::assertSame(['h2', 'normal'], array_column($result, 'style'));
    }

    #[Test]
    public function noMarkupSurvives(): void
    {
        $result = $this->subject->convert(
            '<p><span class="c">a</span><img src="x.png" alt="img"><script>alert(1)</script><style>p{}</style>b &lt;i&gt;</p>'
            . '<table><tr><td>c</td></tr></table>',
        );

        self::assertSame([[[], 'ab <i>']], $this->spans($result[0]));
        self::assertSame([[[], 'c']], $this->spans($result[1]));
    }

    #[Test]
    public function keysAreUniqueAndRestartPerConversion(): void
    {
        $first = $this->subject->convert('<p>a <a href="https://example.org">b</a></p><p>c</p>');
        $second = $this->subject->convert('<p>a <a href="https://example.org">b</a></p><p>c</p>');

        self::assertSame($first, $second);
        $keys = [];
        array_walk_recursive($first, static function (mixed $value, string|int $key) use (&$keys): void {
            if ($key === '_key') {
                $keys[] = $value;
            }
        });
        self::assertSame($keys, array_unique($keys));
    }

    /**
     * @param array<string, mixed> $block
     * @return list<array{mixed, mixed}> marks and text per span
     */
    private function spans(array $block): array
    {
        return array_values(array_map(static fn (array $span): array => [$span['marks'], $span['text']], $block['children']));
    }
}
