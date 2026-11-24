<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Processing;

use MaikSchneider\TcaApi\Configuration\ColumnDefinition;
use MaikSchneider\TcaApi\Serializer\Processing\LinkLanguageResolver;
use MaikSchneider\TcaApi\Serializer\Processing\TypoLinkProcessor;
use MaikSchneider\TcaApiHeadless\Processing\LinkProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class LinkProcessorTest extends UnitTestCase
{
    private LinkProcessor $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $linkService = $this->createMock(LinkService::class);
        $linkService->method('resolve')->willReturnCallback(
            static fn (string $link): array => str_starts_with($link, 'https://')
                ? ['type' => LinkService::TYPE_URL, 'url' => $link]
                : ['type' => LinkService::TYPE_UNKNOWN],
        );
        $this->subject = new LinkProcessor(
            new TypoLinkCodecService(new NoopEventDispatcher()),
            new TypoLinkProcessor($linkService, $this->createMock(SiteFinder::class), new LinkLanguageResolver($this->createMock(ConnectionPool::class))),
            $linkService,
        );
    }

    /**
     * @return array<string, array{mixed, array{href: string, target: ?string, title: ?string}|null}>
     */
    public static function typoLinks(): array
    {
        return [
            'URL only' => ['https://example.org/', ['href' => 'https://example.org/', 'target' => null, 'title' => null]],
            'target and title' => ['https://example.org/ _blank - "The title"', ['href' => 'https://example.org/', 'target' => '_blank', 'title' => 'The title']],
            'title without target' => ['https://example.org/ - css-class "Title with \"quotes\""', ['href' => 'https://example.org/', 'target' => null, 'title' => 'Title with "quotes"']],
            'target without title' => ['https://example.org/ _top', ['href' => 'https://example.org/', 'target' => '_top', 'title' => null]],
            'title "0"' => ['https://example.org/ - - 0', ['href' => 'https://example.org/', 'target' => null, 'title' => '0']],
            'unresolvable link' => ['t3://record?identifier=news&uid=1 _blank', null],
            'empty' => ['', null],
            'no string' => [null, null],
        ];
    }

    /**
     * @param array{href: string, target: ?string, title: ?string}|null $expected
     */
    #[Test]
    #[DataProvider('typoLinks')]
    public function aTypoLinkBecomesHrefTargetAndTitle(mixed $typoLink, ?array $expected): void
    {
        self::assertSame($expected, $this->subject->process($typoLink, ColumnDefinition::fromArray(['groups' => ['show']]), ['request' => null]));
    }
}
