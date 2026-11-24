<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Contract;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The OpenAPI document as the contract of the add-on: what the listeners type,
 * and that they only extend the resources the site serves ("pages-only" and
 * "navigation-only" narrow tca_api.allowedResources to one resource).
 */
final class OpenApiContractTest extends AbstractApiTestCase
{
    private const ADDED_SCHEMAS = '/^(HeadlessContent|HeadlessNavigation|HeadlessPages|HeadlessTable$|HeadlessBulletList$|HeadlessLink$|PortableText)/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_sites.csv');
    }

    #[Test]
    public function theLookupTakesAMandatoryPathAndFindsAtMostOnePage(): void
    {
        $document = $this->openApiDocument('main');
        $lookup = $document['paths']['/_api/headless_pages']['get'];

        self::assertSame([[
            'name' => 'path',
            'in' => 'query',
            'required' => true,
            'description' => 'Page path below the base of the request language, e.g. "/about".',
            'schema' => ['type' => 'string', 'minLength' => 1],
        ]], $lookup['parameters']);
        self::assertSame('The path is missing', $lookup['responses']['400']['description']);
        self::assertSame(
            ['type' => 'array', 'maxItems' => 1, 'items' => ['$ref' => '#/components/schemas/HeadlessPagesLookup']],
            $document['components']['schemas']['HeadlessPagesCollection']['properties']['hydra:member'],
        );
        self::assertSame(['@type', '@id', 'uid'], $document['components']['schemas']['HeadlessPagesLookup']['required']);
        self::assertFalse($document['components']['schemas']['HeadlessPagesLookup']['additionalProperties']);
    }

    #[Test]
    public function aPageAlwaysHasMetaAndRegions(): void
    {
        $page = $this->openApiDocument('main')['components']['schemas']['HeadlessPagesRead'];

        self::assertSame(['meta', 'regions'], $page['required']);
    }

    #[Test]
    public function linksAreTypedWithTargetAndTitle(): void
    {
        $schemas = $this->openApiDocument('main')['components']['schemas'];
        $link = ['oneOf' => [['$ref' => '#/components/schemas/HeadlessLink'], ['type' => 'null']]];

        self::assertSame($link, $schemas['HeadlessPagesRead']['properties']['canonical_link']);
        self::assertSame($link, $schemas['HeadlessContentHeaderRead']['properties']['header_link']);
        self::assertSame($link, $schemas['HeadlessContentTextRead']['properties']['header_link']);
        self::assertSame(['href', 'target', 'title'], $schemas['HeadlessLink']['required']);
        self::assertSame(['string', 'null'], $schemas['HeadlessLink']['properties']['target']['type']);
        self::assertSame(['string', 'null'], $schemas['HeadlessLink']['properties']['title']['type']);
        self::assertSame(['_type', '_key', 'href', 'target', 'title'], $schemas['PortableTextLink']['required']);
        self::assertSame($schemas['HeadlessLink']['properties'], array_diff_key($schemas['PortableTextLink']['properties'], ['_type' => true, '_key' => true]));
    }

    #[Test]
    public function theDocumentOverHttpEqualsTheCliDocument(): void
    {
        foreach (['main' => 'http://localhost', 'pages-only' => 'http://pages-only.localhost', 'navigation-only' => 'http://navigation-only.localhost'] as $site => $base) {
            self::assertSame($this->openApiDocument($site), $this->json($this->request($base . '/_api/openapi.json')), $site);
        }
    }

    #[Test]
    public function withoutTheContentResourcePagesHaveNoRegions(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tt_content_composition.csv');
        $document = $this->openApiDocument('pages-only');
        $schemas = $document['components']['schemas'];

        self::assertSame(['/_api/headless_pages', '/_api/headless_pages/{uid}'], array_keys($document['paths']));
        self::assertSame('path', $document['paths']['/_api/headless_pages']['get']['parameters'][0]['name']);
        self::assertSame(
            ['HeadlessLink', 'HeadlessPagesCollection', 'HeadlessPagesLookup', 'HeadlessPagesMeta', 'HeadlessPagesRead'],
            $this->addedSchemas($schemas),
        );
        self::assertArrayNotHasKey('regions', $schemas['HeadlessPagesRead']['properties']);
        self::assertSame(['meta'], $schemas['HeadlessPagesRead']['required']);

        $response = $this->request('http://pages-only.localhost/_api/headless_pages/500');
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $page = $this->json($response);
        self::assertArrayHasKey('meta', $page);
        self::assertArrayNotHasKey('regions', $page);
    }

    #[Test]
    public function withoutThePageResourceOnlyTheNavigationIsTyped(): void
    {
        $document = $this->openApiDocument('navigation-only');
        $schemas = $document['components']['schemas'];

        self::assertSame(['/_api/headless_navigation/{uid}'], array_keys($document['paths']));
        self::assertSame(['HeadlessNavigationCollection', 'HeadlessNavigationRead'], $this->addedSchemas($schemas));
        self::assertSame(
            ['$ref' => '#/components/schemas/HeadlessNavigationRead'],
            $schemas['HeadlessNavigationRead']['properties']['children']['items'],
        );

        $response = $this->request('http://navigation-only.localhost/_api/headless_navigation/505');
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame(['Navigation only child'], array_column($this->json($response)['children'], 'title'));
    }

    /**
     * @param array<string, mixed> $schemas
     * @return list<string>
     */
    private function addedSchemas(array $schemas): array
    {
        $names = array_values(preg_grep(self::ADDED_SCHEMAS, array_map(strval(...), array_keys($schemas))) ?: []);
        sort($names);

        return $names;
    }
}
