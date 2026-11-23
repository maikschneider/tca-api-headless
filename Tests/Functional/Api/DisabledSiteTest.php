<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Api;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The site "other" does not include the add-on's site set.
 */
final class DisabledSiteTest extends AbstractApiTestCase
{
    private const RESOURCES = ['headless_pages', 'headless_navigation', 'headless_content'];

    #[Test]
    public function theResourcesAnswerLikeUnknownOnes(): void
    {
        $unknown = $this->request('http://other.localhost/_api/no_such_resource/10');
        self::assertSame(404, $unknown->getStatusCode());

        foreach (self::RESOURCES as $resource) {
            foreach (['/_api/' . $resource, '/_api/' . $resource . '/10', '/_api/' . $resource . '/11'] as $path) {
                $response = $this->request('http://other.localhost' . $path);
                self::assertSame(404, $response->getStatusCode(), $path);
                self::assertSame((string)$unknown->getBody(), (string)$response->getBody(), $path);
            }
        }
    }

    #[Test]
    public function theResourcesAreMissingFromTheDocumentation(): void
    {
        $document = json_encode($this->openApiDocument('other'), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsStringIgnoringCase('headless', $document);

        $served = $this->request('http://other.localhost/_api/openapi.json');
        self::assertSame(200, $served->getStatusCode());
        self::assertStringNotContainsStringIgnoringCase('headless', (string)$served->getBody());

        foreach (['/_api/', '/_api/docs.jsonld'] as $path) {
            $response = $this->request('http://other.localhost' . $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringNotContainsStringIgnoringCase('headless', (string)$response->getBody(), $path);
        }
    }

    #[Test]
    public function theEnabledSiteStillDocumentsThem(): void
    {
        foreach (['/_api/openapi.json', '/_api/', '/_api/docs.jsonld'] as $path) {
            $response = $this->request('http://localhost' . $path);
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsStringIgnoringCase('headless', (string)$response->getBody(), $path);
        }
    }
}
