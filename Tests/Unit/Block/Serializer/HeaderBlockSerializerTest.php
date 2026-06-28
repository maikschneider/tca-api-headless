<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\Serializer\HeaderBlockSerializer;
use MaikSchneider\TcaApiHeadless\Link\TypoLinkResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

final class HeaderBlockSerializerTest extends TestCase
{
    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    private function subject(?array $linkResolve = null): HeaderBlockSerializer
    {
        $linkService = self::createStub(LinkService::class);
        $linkService->method('resolve')->willReturn($linkResolve ?? []);

        return new HeaderBlockSerializer(
            new TypoLinkResolver($linkService, self::createStub(SiteFinder::class)),
        );
    }

    #[Test]
    public function supportsOnlyHeaderCType(): void
    {
        self::assertTrue($this->subject()->supports(['CType' => 'header']));
        self::assertFalse($this->subject()->supports(['CType' => 'text']));
    }

    #[Test]
    public function serializesHeadlineAndSubheadlineWithoutLink(): void
    {
        $block = $this->subject()->serialize(
            ['uid' => 3, 'CType' => 'header', 'header' => 'Title', 'subheader' => 'Sub', 'header_link' => ''],
            $this->context(),
        );

        self::assertSame('header', $block['type']);
        self::assertSame('Title', $block['data']['headline']);
        self::assertSame('Sub', $block['data']['subheadline']);
        self::assertArrayNotHasKey('link', $block['data']);
    }

    #[Test]
    public function includesResolvedLink(): void
    {
        $block = $this->subject(['type' => LinkService::TYPE_URL, 'url' => 'https://example.com'])->serialize(
            ['uid' => 4, 'CType' => 'header', 'header' => 'Title', 'header_link' => 'https://example.com'],
            $this->context(),
        );

        self::assertSame('https://example.com', $block['data']['link']['href']);
        self::assertSame('url', $block['data']['link']['type']);
        self::assertArrayNotHasKey('subheadline', $block['data']);
    }
}
