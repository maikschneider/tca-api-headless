<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\SitePages;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * A site's own pages resource (Fixtures/Extensions/site_pages) answers and is
 * documented the same with and without the add-on: both subclasses compare it
 * with Fixtures/SitePages.json.
 */
abstract class AbstractSitePagesTestCase extends AbstractApiTestCase
{
    protected const EXPECTED = __DIR__ . '/../Fixtures/SitePages.json';

    #[Test]
    public function theSitePagesResourceIsUnchanged(): void
    {
        $expected = json_decode((string)file_get_contents(self::EXPECTED), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, $this->sitePagesResource());
    }

    /**
     * @return array<string, mixed>
     */
    protected function sitePagesResource(): array
    {
        $responses = [];
        foreach (['/_api/pages', '/_api/pages/1', '/_api/pages/2', '/_api/pages/10'] as $path) {
            $response = $this->request('http://localhost' . $path);
            $responses[$path] = ['status' => $response->getStatusCode(), 'body' => $this->json($response)];
        }

        $document = $this->openApiDocument('main');
        $isSitePages = static fn (string $key): bool => str_starts_with($key, '/_api/pages') || str_starts_with($key, 'Pages');

        return [
            'responses' => $responses,
            'paths' => array_filter($document['paths'], $isSitePages, ARRAY_FILTER_USE_KEY),
            'schemas' => array_filter($document['components']['schemas'], $isSitePages, ARRAY_FILTER_USE_KEY),
        ];
    }
}
