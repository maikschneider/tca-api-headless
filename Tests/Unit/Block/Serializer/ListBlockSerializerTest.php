<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\Serializer\ListBlockSerializer;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class ListBlockSerializerTest extends TestCase
{
    private ListBlockSerializer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ListBlockSerializer(new HtmlToPortableText());
    }

    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    #[Test]
    public function supportsOnlyBulletsCType(): void
    {
        self::assertTrue($this->subject->supports(['CType' => 'bullets']));
        self::assertFalse($this->subject->supports(['CType' => 'text']));
    }

    #[Test]
    public function eachNonEmptyLineBecomesOneItem(): void
    {
        $block = $this->subject->serialize(
            ['uid' => 7, 'CType' => 'bullets', 'header' => 'Points', 'bodytext' => "one\ntwo\n\nthree"],
            $this->context(),
        );

        self::assertSame('list', $block['type']);
        self::assertSame('Points', $block['data']['headline']);
        self::assertCount(3, $block['data']['items']);
        // Each item is its own Portable Text array.
        self::assertSame('one', $block['data']['items'][0][0]['children'][0]['text']);
        self::assertSame('three', $block['data']['items'][2][0]['children'][0]['text']);
    }
}
