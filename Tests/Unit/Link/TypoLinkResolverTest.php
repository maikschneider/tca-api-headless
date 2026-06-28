<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Link;

use MaikSchneider\TcaApiHeadless\Link\TypoLinkResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Site\SiteFinder;

final class TypoLinkResolverTest extends TestCase
{
    private function subjectResolving(array|\Throwable $resolveResult): TypoLinkResolver
    {
        $linkService = self::createStub(LinkService::class);
        if ($resolveResult instanceof \Throwable) {
            $linkService->method('resolve')->willThrowException($resolveResult);
        } else {
            $linkService->method('resolve')->willReturn($resolveResult);
        }

        return new TypoLinkResolver($linkService, self::createStub(SiteFinder::class));
    }

    #[Test]
    public function emptyInputResolvesToNull(): void
    {
        self::assertNull($this->subjectResolving([])->resolve('   '));
    }

    #[Test]
    public function urlLinkIsReturnedDirectly(): void
    {
        $link = $this->subjectResolving([
            'type' => LinkService::TYPE_URL,
            'url' => 'https://example.com',
        ])->resolve('https://example.com');

        self::assertSame('https://example.com', $link['href']);
        self::assertSame('url', $link['type']);
        self::assertNull($link['target']);
    }

    #[Test]
    public function emailLinkBecomesMailto(): void
    {
        $link = $this->subjectResolving([
            'type' => LinkService::TYPE_EMAIL,
            'email' => 'info@example.com',
        ])->resolve('mailto:info@example.com');

        self::assertSame('mailto:info@example.com', $link['href']);
        self::assertSame('mail', $link['type']);
    }

    #[Test]
    public function unresolvableLinkReturnsNull(): void
    {
        self::assertNull(
            $this->subjectResolving(new \RuntimeException('bad link'))->resolve('t3://broken'),
        );
    }
}
