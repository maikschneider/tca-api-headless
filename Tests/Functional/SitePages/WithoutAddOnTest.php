<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\SitePages;

final class WithoutAddOnTest extends AbstractSitePagesTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        __DIR__ . '/../Fixtures/Extensions/site_pages',
    ];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/site_pages/Sites' => 'typo3conf/sites',
    ];
}
