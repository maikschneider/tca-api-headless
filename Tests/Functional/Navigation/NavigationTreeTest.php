<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Navigation;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The page tree below page 70 of Fixtures/pages_navigation.csv on the site
 * "main" (default depth 2), and below page 30 on the site "second" (depth 1).
 * Page 80 is the root of the site "nested".
 */
final class NavigationTreeTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_composition', 'pages_navigation', 'fe_groups', 'fe_users'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
    }

    #[Test]
    public function theTreeReachesTheConfiguredDepthInPageTreeOrder(): void
    {
        self::assertSame([
            'Products' => ['Product A' => []],
            'Shortcut' => [],
            'External' => [],
            'English only' => [],
            'Not in German menu' => [],
        ], $this->titles($this->navigation('http://localhost/_api/headless_navigation/70')));
    }

    #[Test]
    public function aSiteSettingLimitsTheDepth(): void
    {
        $navigation = $this->navigation('http://second.localhost/_api/headless_navigation/30');

        self::assertSame(['Second child' => []], $this->titles($navigation));
    }

    #[Test]
    public function everyEntryHasItsColumnsAndAbsoluteUrl(): void
    {
        $navigation = $this->navigation('http://localhost/_api/headless_navigation/70');
        $products = $navigation['children'][0];

        self::assertSame('http://localhost/navigation', $navigation['url']);
        self::assertSame([
            '@type' => 'HeadlessNavigation',
            '@id' => '/_api/headless_navigation/71',
            'uid' => 71,
            'title' => 'Products',
            'nav_title' => 'Our products',
            'slug' => '/navigation/products',
            'doktype' => 1,
            'url' => 'http://localhost/navigation/products',
        ], array_diff_key($products, ['children' => true]));
        self::assertSame('http://localhost/navigation/products/a', $products['children'][0]['url']);
        self::assertSame([], $products['children'][0]['children']);
        self::assertSame([4, 3], array_column(\array_slice($navigation['children'], 1, 2), 'doktype'));
    }

    #[Test]
    public function translatedEntriesHaveTheirTitlesAndUrls(): void
    {
        $navigation = $this->navigation('http://localhost/de/_api/headless_navigation/70');

        self::assertSame([
            'Produkte' => ['Produkt A' => []],
            'Abkürzung' => [],
            'Extern' => [],
            'Nur Deutsch' => [],
        ], $this->titles($navigation));
        self::assertSame('http://localhost/de/navigation', $navigation['url']);
        self::assertSame('Unsere Produkte', $navigation['children'][0]['nav_title']);
        self::assertSame('http://localhost/de/navigation/produkte', $navigation['children'][0]['url']);
        self::assertSame('http://localhost/de/navigation/produkte/a', $navigation['children'][0]['children'][0]['url']);
        self::assertSame(71, $navigation['children'][0]['uid']);
    }

    #[Test]
    public function aPageForAFrontendUserGroupIsOnlyListedForItsMembers(): void
    {
        $url = 'http://localhost/_api/headless_navigation/70';

        self::assertArrayNotHasKey('Members', $this->titles($this->navigation($url)));
        self::assertArrayNotHasKey('Members', $this->titles($this->navigation($url, 2)));
        self::assertSame(['Members child' => []], $this->titles($this->navigation($url, 1))['Members']);
    }

    #[Test]
    public function aScheduledPageAppearsOnceItsStartTimeHasPassed(): void
    {
        $url = 'http://localhost/_api/headless_navigation/70';
        self::assertArrayNotHasKey('Scheduled', $this->titles($this->navigation($url)));

        $this->getConnectionPool()->getConnectionForTable('pages')->update('pages', ['starttime' => time() - 60], ['uid' => 82]);

        self::assertArrayHasKey('Scheduled', $this->titles($this->navigation($url)));
    }

    #[Test]
    public function thePageTreeOfAnotherSiteIsLeftOut(): void
    {
        $body = (string)$this->request('http://localhost/_api/headless_navigation/70')->getBody();

        self::assertStringNotContainsString('"uid":80', $body);
    }

    #[Test]
    public function theNavigationIsTypedRecursively(): void
    {
        $schema = $this->openApiDocument('main')['components']['schemas']['HeadlessNavigationRead'];

        self::assertSame(['$ref' => '#/components/schemas/HeadlessNavigationRead'], $schema['properties']['children']['items']);
        self::assertSame(['type' => 'string', 'format' => 'uri'], array_diff_key($schema['properties']['url'], ['description' => true]));
        self::assertContains('children', $schema['required']);
        self::assertContains('url', $schema['required']);
    }

    /**
     * @return array<string, mixed>
     */
    private function navigation(string $url, ?int $frontendUser = null): array
    {
        $response = $this->requestAs($url, $frontendUser);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());

        return $this->json($response);
    }

    private function requestAs(string $url, ?int $frontendUser): ResponseInterface
    {
        $context = new InternalRequestContext();
        if ($frontendUser !== null) {
            $context = $context->withFrontendUserId($frontendUser);
        }

        return $this->executeFrontendSubRequest(new InternalRequest($url), $context);
    }

    /**
     * The subpages as a tree of titles.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function titles(array $entry): array
    {
        $titles = [];
        foreach ($entry['children'] as $child) {
            $titles[$child['title']] = $this->titles($child);
        }

        return $titles;
    }
}
