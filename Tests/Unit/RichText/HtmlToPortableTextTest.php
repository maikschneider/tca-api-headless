<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\RichText;

use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HtmlToPortableTextTest extends TestCase
{
    private HtmlToPortableText $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new HtmlToPortableText();
    }

    #[Test]
    public function blankInputYieldsEmptyArray(): void
    {
        self::assertSame([], $this->subject->convert('   '));
    }

    #[Test]
    public function paragraphBecomesNormalBlock(): void
    {
        $result = $this->subject->convert('<p>Hello world</p>');

        self::assertCount(1, $result);
        self::assertSame('block', $result[0]['_type']);
        self::assertSame('normal', $result[0]['style']);
        self::assertSame('Hello world', $result[0]['children'][0]['text']);
        self::assertSame([], $result[0]['children'][0]['marks']);
    }

    #[Test]
    public function headingKeepsItsLevel(): void
    {
        $result = $this->subject->convert('<h2>Title</h2>');

        self::assertSame('h2', $result[0]['style']);
        self::assertSame('Title', $result[0]['children'][0]['text']);
    }

    #[Test]
    public function strongAndEmBecomeDecorators(): void
    {
        $result = $this->subject->convert('<p>a <strong>b</strong> <em>c</em></p>');
        $children = $result[0]['children'];

        self::assertSame('a ', $children[0]['text']);
        self::assertSame('b', $children[1]['text']);
        self::assertSame(['strong'], $children[1]['marks']);
        self::assertSame('c', $children[3]['text']);
        self::assertSame(['em'], $children[3]['marks']);
    }

    #[Test]
    public function linkBecomesAnnotationInMarkDefs(): void
    {
        $result = $this->subject->convert('<p>see <a href="/team">team</a></p>');
        $block = $result[0];

        self::assertCount(1, $block['markDefs']);
        $def = $block['markDefs'][0];
        self::assertSame('link', $def['_type']);
        self::assertSame('/team', $def['href']);

        // The linked span carries the markDef key as its mark.
        $linkedSpan = $block['children'][1];
        self::assertSame('team', $linkedSpan['text']);
        self::assertSame([$def['_key']], $linkedSpan['marks']);
    }

    #[Test]
    public function unorderedListProducesBulletItems(): void
    {
        $result = $this->subject->convert('<ul><li>one</li><li>two</li></ul>');

        self::assertCount(2, $result);
        self::assertSame('bullet', $result[0]['listItem']);
        self::assertSame(1, $result[0]['level']);
        self::assertSame('one', $result[0]['children'][0]['text']);
        self::assertSame('two', $result[1]['children'][0]['text']);
    }

    #[Test]
    public function orderedListProducesNumberItems(): void
    {
        $result = $this->subject->convert('<ol><li>first</li></ol>');

        self::assertSame('number', $result[0]['listItem']);
    }

    #[Test]
    public function brBecomesNewlineSpan(): void
    {
        $result = $this->subject->convert('<p>line1<br>line2</p>');
        $texts = array_column($result[0]['children'], 'text');

        self::assertSame(['line1', "\n", 'line2'], $texts);
    }
}
