<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Composition;

use MaikSchneider\TcaApiHeadless\Composition\RegionMapper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Settings\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RegionMapperTest extends UnitTestCase
{
    #[Test]
    public function aPageWithoutBackendLayoutUsesTheSiteMap(): void
    {
        self::assertSame(
            [0 => 'main', 7 => 'footer'],
            $this->subject()->regionsOf(1, $this->site([' 0 : main ', '7:footer'])),
        );
    }

    #[Test]
    public function aMalformedSiteMapEntryIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1795454001);

        $this->subject()->regionsOf(1, $this->site(['0:main', 'sidebar']));
    }

    private function subject(): RegionMapper
    {
        $backendLayoutView = $this->createMock(BackendLayoutView::class);
        $backendLayoutView->method('getBackendLayoutForPage')->willReturn($this->createConfiguredMock(BackendLayout::class, ['getIdentifier' => 'default']));

        return new RegionMapper($backendLayoutView);
    }

    /**
     * @param list<string> $regions
     */
    private function site(array $regions): Site
    {
        return new Site('main', 1, [], SiteSettings::create(new Settings(['tca_api_headless.regions' => $regions])));
    }
}
