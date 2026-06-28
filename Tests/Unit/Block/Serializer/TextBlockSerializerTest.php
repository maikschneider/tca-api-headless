<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Unit\Block\Serializer;

use MaikSchneider\HeadlessPages\Block\BlockContext;
use MaikSchneider\HeadlessPages\Block\Serializer\TextBlockSerializer;
use MaikSchneider\HeadlessPages\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class TextBlockSerializerTest extends TestCase
{
    private TextBlockSerializer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TextBlockSerializer(new HtmlToPortableText());
    }

    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    #[Test]
    public function supportsOnlyTextCType(): void
    {
        self::assertTrue($this->subject->supports(['CType' => 'text']));
        self::assertFalse($this->subject->supports(['CType' => 'header']));
    }

    #[Test]
    public function serializesHeaderAndBody(): void
    {
        $block = $this->subject->serialize(
            ['uid' => 5, 'CType' => 'text', 'header' => 'Hi', 'bodytext' => '<p>Yo</p>'],
            $this->context(),
        );

        self::assertSame('text', $block['type']);
        self::assertSame(5, $block['id']);
        self::assertSame('Hi', $block['data']['headline']);
        self::assertSame('normal', $block['data']['body'][0]['style']);
        self::assertSame('Yo', $block['data']['body'][0]['children'][0]['text']);
    }

    #[Test]
    public function omitsHeadlineWhenHeaderEmptyAndKeepsEmptyBody(): void
    {
        $block = $this->subject->serialize(
            ['uid' => 6, 'CType' => 'text', 'header' => '', 'bodytext' => ''],
            $this->context(),
        );

        self::assertArrayNotHasKey('headline', $block['data']);
        self::assertSame([], $block['data']['body']);
    }
}
