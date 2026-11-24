<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Page;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Page 600 of Fixtures/pages_free.csv on the site "main" in its free-mode
 * languages: French (2) without fallbacks, Spanish (3) falling back to the
 * default language. The page is translated to French only.
 */
final class FreeModeTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_free', 'tt_content_free', 'sys_file_storage', 'sys_file', 'sys_file_metadata', 'sys_file_reference_free', 'fe_groups', 'fe_users'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
    }

    #[Test]
    public function regionsHoldTheContentOfTheRequestLanguageBySorting(): void
    {
        self::assertSame([
            'main' => [
                ['uid' => 625, 'CType' => 'html', 'header' => 'Brut'],
                ['uid' => 620, 'CType' => 'header', 'header' => 'Premier'],
                ['uid' => 612, 'CType' => 'header', 'header' => 'All languages'],
                ['uid' => 621, 'CType' => 'text', 'header' => 'Deuxième'],
                ['uid' => 624, 'CType' => 'textmedia', 'header' => 'Média'],
            ],
            'left' => [['uid' => 623, 'CType' => 'header', 'header' => 'À gauche']],
        ], $this->regionSummary('http://localhost/fr/_api/headless_pages/600'));
    }

    #[Test]
    public function contentKeepsItsOwnColumnsAndFiles(): void
    {
        $main = $this->page('http://localhost/fr/_api/headless_pages/600')['regions']['main'];

        self::assertSame('Deuxième', $main[3]['bodytext'][0]['children'][0]['text']);
        self::assertSame(['brochure.pdf'], array_column($main[4]['assets'], 'name'));
    }

    #[Test]
    public function contentForMembersIsLeftOutForAnonymousUsers(): void
    {
        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/fr/_api/headless_pages/600'),
            (new InternalRequestContext())->withFrontendUserId(1),
        );
        $main = $this->json($response)['regions']['main'];

        self::assertSame('Membres', end($main)['header']);
        self::assertNotContains('Membres', array_column($this->page('http://localhost/fr/_api/headless_pages/600')['regions']['main'], 'header'));
    }

    #[Test]
    public function aPageWithoutTranslationHasTheContentOfTheDefaultLanguage(): void
    {
        $page = $this->page('http://localhost/es/_api/headless_pages/600');

        self::assertSame('Free', $page['title']);
        self::assertSame([
            'main' => [
                ['uid' => 610, 'CType' => 'header', 'header' => 'First'],
                ['uid' => 611, 'CType' => 'text', 'header' => 'Second'],
                ['uid' => 612, 'CType' => 'header', 'header' => 'All languages'],
                ['uid' => 613, 'CType' => 'textmedia', 'header' => 'Media'],
            ],
        ], $this->regionSummary('http://localhost/es/_api/headless_pages/600'));
    }

    #[Test]
    public function theDefaultLanguageKeepsItsContent(): void
    {
        self::assertSame([
            'main' => [
                ['uid' => 610, 'CType' => 'header', 'header' => 'First'],
                ['uid' => 611, 'CType' => 'text', 'header' => 'Second'],
                ['uid' => 612, 'CType' => 'header', 'header' => 'All languages'],
                ['uid' => 613, 'CType' => 'textmedia', 'header' => 'Media'],
            ],
        ], $this->regionSummary('http://localhost/_api/headless_pages/600'));
    }

    #[Test]
    public function navigationHasTheTranslatedPagesOfALanguageWithoutFallback(): void
    {
        $navigation = $this->page('http://localhost/fr/_api/headless_navigation/600');

        self::assertSame('Libre', $navigation['title']);
        self::assertSame('http://localhost/fr/libre', $navigation['url']);
        self::assertSame([
            ['title' => 'Enfant', 'url' => 'http://localhost/fr/libre/enfant'],
        ], $this->entries($navigation));
    }

    #[Test]
    public function navigationFallsBackToTheDefaultLanguageWhereConfigured(): void
    {
        $navigation = $this->page('http://localhost/es/_api/headless_navigation/600');

        self::assertSame('http://localhost/es/free', $navigation['url']);
        self::assertSame([
            ['title' => 'Free child', 'url' => 'http://localhost/es/free/child'],
            ['title' => 'Untranslated child', 'url' => 'http://localhost/es/free/untranslated'],
        ], $this->entries($navigation));
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $url): array
    {
        $response = $this->request($url);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());

        return $this->json($response);
    }

    /**
     * @return array<array<array{uid: mixed, CType: mixed, header: mixed}>>
     */
    private function regionSummary(string $url): array
    {
        return array_map(
            static fn (array $contents): array => array_map(
                static fn (array $content): array => ['uid' => $content['uid'], 'CType' => $content['CType'], 'header' => $content['header']],
                $contents,
            ),
            $this->page($url)['regions'],
        );
    }

    /**
     * @param array<string, mixed> $navigation
     * @return array<array{title: mixed, url: mixed}>
     */
    private function entries(array $navigation): array
    {
        return array_map(
            static fn (array $child): array => ['title' => $child['title'], 'url' => $child['url']],
            $navigation['children'],
        );
    }
}
