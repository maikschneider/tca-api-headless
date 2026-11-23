<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Page;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Pages of Fixtures/pages_composition.csv with the content of
 * Fixtures/tt_content_composition.csv. The site "main" allows the fallback
 * types html and shortcut, the site "second" none.
 */
final class ComposePageTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_composition', 'tt_content_composition', 'fe_groups', 'fe_users'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
    }

    #[Test]
    public function contentIsGroupedIntoRegionsBySorting(): void
    {
        self::assertSame([
            'main' => [
                ['uid' => 201, 'CType' => 'header', 'header' => 'First in main'],
                ['uid' => 200, 'CType' => 'text', 'header' => 'Second in main'],
                ['uid' => 202, 'CType' => 'html', 'header' => 'Listed fallback'],
                ['uid' => 212, 'CType' => 'header', 'header' => 'All languages'],
            ],
            'left' => [['uid' => 204, 'CType' => 'text', 'header' => 'Left']],
            'right' => [['uid' => 205, 'CType' => 'header', 'header' => 'Right']],
            'border' => [['uid' => 206, 'CType' => 'header', 'header' => 'Border']],
        ], $this->regionSummary('http://localhost/_api/headless_pages/4'));
    }

    #[Test]
    public function contentKeepsItsTypeColumnsWithoutColumnPosition(): void
    {
        $main = $this->page('http://localhost/_api/headless_pages/4')['regions']['main'];

        self::assertSame('Second', $main[1]['bodytext'][0]['children'][0]['text']);
        foreach ($main as $content) {
            self::assertArrayNotHasKey('colPos', $content);
            self::assertArrayNotHasKey('sorting', $content);
        }
    }

    #[Test]
    public function aListedFallbackTypeIsProjectedToUidTypeAndHeader(): void
    {
        $main = $this->page('http://localhost/_api/headless_pages/4')['regions']['main'];

        self::assertSame(['uid' => 202, 'CType' => 'html', 'header' => 'Listed fallback'], $main[2]);
    }

    #[Test]
    public function aSiteWithoutFallbackTypesDropsTheirContent(): void
    {
        self::assertSame(
            ['main' => [['uid' => 241, 'CType' => 'header', 'header' => 'Second site header']]],
            $this->regionSummary('http://second.localhost/_api/headless_pages/30'),
        );
    }

    #[Test]
    public function aPageWithoutContentHasAnEmptyRegionsObject(): void
    {
        $response = $this->request('http://localhost/_api/headless_pages/2');

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertStringContainsString('"regions":{}', (string)$response->getBody());
    }

    #[Test]
    public function translatedContentFollowsTheRequestLanguage(): void
    {
        self::assertSame(
            ['main' => [
                ['uid' => 200, 'CType' => 'text', 'header' => 'Zweiter im Hauptbereich'],
                ['uid' => 212, 'CType' => 'header', 'header' => 'All languages'],
            ]],
            $this->regionSummary('http://localhost/de/_api/headless_pages/4'),
        );
        self::assertSame(
            'Zweiter',
            $this->page('http://localhost/de/_api/headless_pages/4')['regions']['main'][0]['bodytext'][0]['children'][0]['text'],
        );
    }

    #[Test]
    public function regionsFollowTheBackendLayoutInheritedFromAParentPage(): void
    {
        self::assertSame([
            'content' => [['uid' => 220, 'CType' => 'header', 'header' => 'Content column']],
            'aside' => [['uid' => 221, 'CType' => 'header', 'header' => 'Aside column']],
            'right' => [['uid' => 222, 'CType' => 'header', 'header' => 'Column without identifier']],
        ], $this->regionSummary('http://localhost/_api/headless_pages/6'));
    }

    #[Test]
    public function aLayoutForTheNextLevelLeavesThePageItselfOnTheSiteMap(): void
    {
        self::assertSame(
            ['main' => [['uid' => 225, 'CType' => 'header', 'header' => 'Section content']]],
            $this->regionSummary('http://localhost/_api/headless_pages/5'),
        );
    }

    #[Test]
    public function contentForAFrontendUserGroupIsOnlyComposedForItsMembers(): void
    {
        $headers = fn (?int $user): array => array_column(
            $this->regionSummary('http://localhost/_api/headless_pages/4', $user)['main'],
            'header',
        );

        self::assertNotContains('Members only', $headers(null));
        self::assertNotContains('Members only', $headers(2));
        self::assertContains('Members only', $headers(1));
    }

    #[Test]
    public function aPageForAFrontendUserGroupIsOnlyServedToItsMembers(): void
    {
        foreach ([7, 9] as $page) {
            $url = 'http://localhost/_api/headless_pages/' . $page;
            self::assertSame(404, $this->request($url)->getStatusCode(), $url . ' anonymous');
            self::assertSame(404, $this->requestAs($url, 2)->getStatusCode(), $url . ' other group');

            $response = $this->requestAs($url, 1);
            self::assertSame(200, $response->getStatusCode(), $url . ' member: ' . $response->getBody());
        }
    }

    #[Test]
    public function aSysfolderAndAPageOfAnotherSiteAreNotComposed(): void
    {
        foreach (['http://localhost/_api/headless_pages/3', 'http://localhost/_api/headless_pages/30'] as $url) {
            self::assertSame(404, $this->request($url)->getStatusCode(), $url);
        }
    }

    #[Test]
    public function theRegionsAreTypedWithTheFallbackTypesOfTheSite(): void
    {
        $schemas = $this->openApiDocument('main')['components']['schemas'];

        self::assertSame([
            'type' => 'array',
            'items' => ['oneOf' => [
                ['$ref' => '#/components/schemas/HeadlessContentRead'],
                ['$ref' => '#/components/schemas/HeadlessContentFallbackRead'],
            ]],
        ], $schemas['HeadlessPagesRead']['properties']['regions']['additionalProperties']);
        self::assertSame(['html', 'shortcut'], $schemas['HeadlessContentFallbackRead']['properties']['CType']['enum']);
    }

    #[Test]
    public function aSiteWithoutFallbackTypesHasNoFallbackSchema(): void
    {
        $schemas = $this->openApiDocument('second')['components']['schemas'];

        self::assertSame(
            ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/HeadlessContentRead']],
            $schemas['HeadlessPagesRead']['properties']['regions']['additionalProperties'],
        );
        self::assertArrayNotHasKey('HeadlessContentFallbackRead', $schemas);
    }

    #[Test]
    public function composedContentCarriesNoLinkToTheContentResource(): void
    {
        $body = (string)$this->request('http://localhost/_api/headless_pages/4')->getBody();

        self::assertStringNotContainsString('headless_content', $body);
        self::assertStringNotContainsString('"@type":"HeadlessContent', $body);
    }

    private function requestAs(string $url, ?int $frontendUser): \Psr\Http\Message\ResponseInterface
    {
        $context = new InternalRequestContext();
        if ($frontendUser !== null) {
            $context = $context->withFrontendUserId($frontendUser);
        }

        return $this->executeFrontendSubRequest(new InternalRequest($url), $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $url, ?int $frontendUser = null): array
    {
        $response = $this->requestAs($url, $frontendUser);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());

        return $this->json($response);
    }

    /**
     * @return array<array<array{uid: mixed, CType: mixed, header: mixed}>>
     */
    private function regionSummary(string $url, ?int $frontendUser = null): array
    {
        return array_map(
            static fn (array $contents): array => array_map(
                static fn (array $content): array => ['uid' => $content['uid'], 'CType' => $content['CType'], 'header' => $content['header']],
                $contents,
            ),
            $this->page($url, $frontendUser)['regions'],
        );
    }
}
