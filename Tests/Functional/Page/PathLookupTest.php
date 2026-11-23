<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Page;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The page collection looks up a single page by its path, in the request language.
 */
final class PathLookupTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_composition', 'pages_navigation', 'fe_groups', 'fe_users'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function queriesWithoutPath(): array
    {
        return [
            'no path' => [''],
            'empty path' => ['?path='],
            'blank path' => ['?path=%20'],
            'list of paths' => ['?path[]=/about'],
            'map of paths' => ['?path[x]=/about'],
            'only other parameters' => ['?page=1&itemsPerPage=50'],
        ];
    }

    #[Test]
    #[DataProvider('queriesWithoutPath')]
    public function pagesAreNeverListedWithoutAPath(string $query): void
    {
        $response = $this->request('http://localhost/_api/headless_pages' . $query);

        self::assertSame(400, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame([
            '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
            '@type' => 'hydra:Error',
            'hydra:title' => 'Bad Request',
            'hydra:description' => 'The query parameter "path" is required.',
        ], $this->json($response));
    }

    #[Test]
    public function theRootPathFindsTheSiteRoot(): void
    {
        self::assertSame([
            '@context' => 'http://www.w3.org/ns/hydra/context.jsonld',
            '@type' => 'hydra:Collection',
            '@id' => '/_api/headless_pages',
            'hydra:totalItems' => 1,
            'hydra:member' => [
                ['@type' => 'HeadlessPages', '@id' => '/_api/headless_pages/1', 'uid' => 1],
            ],
            'hydra:view' => [
                '@type' => 'hydra:PartialCollectionView',
                'hydra:first' => '/_api/headless_pages?path=%2F&page=1',
                'hydra:last' => '/_api/headless_pages?path=%2F&page=1',
                'hydra:previous' => null,
                'hydra:next' => null,
            ],
        ], $this->lookUp('http://localhost/_api/headless_pages?path=/'));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function pathsOfPages(): array
    {
        return [
            'page' => ['/about', 2],
            'nested page' => ['/section/child', 6],
            'deeply nested page' => ['/navigation/products/a/details', 77],
            'trailing slash' => ['/section/child/', 6],
            'without leading slash' => ['section/child', 6],
            'surrounding blanks' => ['%20/about%20', 2],
        ];
    }

    #[Test]
    #[DataProvider('pathsOfPages')]
    public function aPathFindsItsPage(string $path, int $uid): void
    {
        self::assertSame([$uid], $this->foundUids('http://localhost/_api/headless_pages?path=' . $path));
    }

    #[Test]
    public function aTranslatedPathFindsThePageByItsDefaultLanguageUid(): void
    {
        self::assertSame([2], $this->foundUids('http://localhost/de/_api/headless_pages?path=/ueber-uns'));
        self::assertSame([71], $this->foundUids('http://localhost/de/_api/headless_pages?path=/navigation/produkte'));
        self::assertSame([1], $this->foundUids('http://localhost/de/_api/headless_pages?path=/'));
    }

    #[Test]
    public function aPathOnlyMatchesInItsOwnLanguage(): void
    {
        self::assertSame([], $this->foundUids('http://localhost/de/_api/headless_pages?path=/about'));
        self::assertSame([], $this->foundUids('http://localhost/_api/headless_pages?path=/ueber-uns'));
        // Not translated, and the German language has no fallback.
        self::assertSame([], $this->foundUids('http://localhost/de/_api/headless_pages?path=/navigation/english-only'));
    }

    #[Test]
    public function anUnknownPathIsAnEmptyCollection(): void
    {
        $collection = $this->lookUp('http://localhost/_api/headless_pages?path=/missing');

        self::assertSame(0, $collection['hydra:totalItems']);
        self::assertSame([], $collection['hydra:member']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsMatchedLikeTheFrontend(): array
    {
        return [
            'other letter case' => ['/About'],
            'uid' => ['2'],
            'query string' => ['/about?x=1'],
            'fragment' => ['/about%23top'],
        ];
    }

    #[Test]
    #[DataProvider('pathsMatchedLikeTheFrontend')]
    public function onlyTheExactPathOfAPageMatches(string $path): void
    {
        self::assertSame([], $this->foundUids('http://localhost/_api/headless_pages?path=' . rawurlencode(rawurldecode($path))));
    }

    #[Test]
    public function otherQueryParametersDoNotSteerTheLookup(): void
    {
        self::assertSame([], $this->foundUids('http://localhost/_api/headless_pages?path=/missing&id=2'));
        self::assertSame([2], $this->foundUids('http://localhost/_api/headless_pages?path=/about&id=6'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pathsOfPagesTheSiteDoesNotServe(): array
    {
        return [
            'sysfolder' => ['/storage'],
            'hidden page' => ['/navigation/hidden'],
            'scheduled page' => ['/navigation/scheduled'],
            'expired page' => ['/navigation/expired'],
            'page of another site' => ['/other-page'],
        ];
    }

    #[Test]
    #[DataProvider('pathsOfPagesTheSiteDoesNotServe')]
    public function aPageTheSiteDoesNotServeIsNotFound(string $path): void
    {
        self::assertSame([], $this->foundUids('http://localhost/_api/headless_pages?path=' . $path));
    }

    #[Test]
    public function aPageForAFrontendUserGroupIsOnlyFoundForItsMembers(): void
    {
        foreach (['/members' => 7, '/members-area/page' => 9] as $path => $uid) {
            $url = 'http://localhost/_api/headless_pages?path=' . $path;
            self::assertSame([], $this->foundUids($url), $path . ' anonymous');
            self::assertSame([], $this->foundUids($url, 2), $path . ' other group');
            self::assertSame([$uid], $this->foundUids($url, 1), $path . ' member');
        }
    }

    #[Test]
    public function theLookupIsDocumentedAsACollectionOfPages(): void
    {
        $response = $this->openApiDocument('main')['paths']['/_api/headless_pages']['get']['responses']['200'];

        self::assertSame(['$ref' => '#/components/schemas/HeadlessPagesCollection'], $response['content']['application/ld+json']['schema']);
    }

    /**
     * @return list<int>
     */
    private function foundUids(string $url, ?int $frontendUser = null): array
    {
        $collection = $this->lookUp($url, $frontendUser);
        self::assertSame(\count($collection['hydra:member']), $collection['hydra:totalItems']);

        return array_column($collection['hydra:member'], 'uid');
    }

    /**
     * @return array<string, mixed>
     */
    private function lookUp(string $url, ?int $frontendUser = null): array
    {
        $context = new InternalRequestContext();
        if ($frontendUser !== null) {
            $context = $context->withFrontendUserId($frontendUser);
        }
        $response = $this->executeFrontendSubRequest(new InternalRequest($url), $context);
        self::assertSame(200, $response->getStatusCode(), $url . ': ' . $response->getBody());

        return $this->json($response);
    }
}
