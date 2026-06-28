<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Navigation;

use MaikSchneider\TcaApiHeadless\Navigation\NavigationBuilder;
use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractHeadlessTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;

final class NavigationBuilderTest extends AbstractHeadlessTestCase
{
    private function defaultLanguage(): \TYPO3\CMS\Core\Site\Entity\SiteLanguage
    {
        return (new Site('test', 1, [
            'base' => '/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'en_US.UTF-8', 'base' => '/'],
            ],
        ]))->getDefaultLanguage();
    }

    #[Test]
    public function buildsNestedNavigationFromPageTree(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $nav = $this->get(NavigationBuilder::class)->build(1, 3, $this->defaultLanguage());

        self::assertCount(1, $nav);
        self::assertSame(2, $nav[0]['id']);
        self::assertSame('Team', $nav[0]['title']);
        self::assertArrayHasKey('link', $nav[0]);

        // Child page.
        self::assertCount(1, $nav[0]['children']);
        self::assertSame(4, $nav[0]['children'][0]['id']);
        self::assertSame('Leadership', $nav[0]['children'][0]['title']);
    }

    #[Test]
    public function depthLimitsRecursion(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $nav = $this->get(NavigationBuilder::class)->build(1, 1, $this->defaultLanguage());

        self::assertSame(2, $nav[0]['id']);
        self::assertSame([], $nav[0]['children']);
    }

    #[Test]
    public function translatedLanguageOverlaysTitle(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $german = (new Site('test', 1, [
            'base' => '/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'en_US.UTF-8', 'base' => '/'],
                ['languageId' => 1, 'locale' => 'de_DE.UTF-8', 'base' => '/de/'],
            ],
        ]))->getLanguageById(1);

        $nav = $this->get(NavigationBuilder::class)->build(1, 1, $german);

        self::assertSame('Team (DE)', $nav[0]['title']);
    }
}
