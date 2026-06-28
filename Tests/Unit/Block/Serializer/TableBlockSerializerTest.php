<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Unit\Block\Serializer;

use MaikSchneider\HeadlessPages\Block\BlockContext;
use MaikSchneider\HeadlessPages\Block\Serializer\TableBlockSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class TableBlockSerializerTest extends TestCase
{
    private TableBlockSerializer $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TableBlockSerializer();
    }

    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    #[Test]
    public function supportsOnlyTableCType(): void
    {
        self::assertTrue($this->subject->supports(['CType' => 'table']));
        self::assertFalse($this->subject->supports(['CType' => 'text']));
    }

    #[Test]
    public function firstRowBecomesHeadWhenHeaderPositionTop(): void
    {
        $block = $this->subject->serialize(
            [
                'uid' => 8,
                'CType' => 'table',
                'header' => 'Prices',
                'bodytext' => "Name|Price\nApple|1\nPear|2",
                'table_header_position' => 1,
            ],
            $this->context(),
        );

        self::assertSame('table', $block['type']);
        self::assertSame('Prices', $block['data']['headline']);
        self::assertSame([['Name', 'Price']], $block['data']['head']);
        self::assertSame([['Apple', '1'], ['Pear', '2']], $block['data']['body']);
    }

    #[Test]
    public function withoutHeaderPositionAllRowsAreBody(): void
    {
        $block = $this->subject->serialize(
            [
                'uid' => 9,
                'CType' => 'table',
                'bodytext' => "a|b\nc|d",
                'table_header_position' => 0,
            ],
            $this->context(),
        );

        self::assertArrayNotHasKey('head', $block['data']);
        self::assertArrayNotHasKey('headline', $block['data']);
        self::assertSame([['a', 'b'], ['c', 'd']], $block['data']['body']);
    }
}
