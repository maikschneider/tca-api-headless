<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\SitePages;

final class WithAddOnTest extends AbstractSitePagesTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
        __DIR__ . '/../Fixtures/Extensions/site_pages',
    ];
}
