<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Smoke;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractHeadlessTestCase;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final class ExtensionLoadedTest extends AbstractHeadlessTestCase
{
    /**
     * @test
     */
    public function extensionIsLoaded(): void
    {
        self::assertTrue(
            ExtensionManagementUtility::isLoaded('tca_api_headless'),
            'The tca_api_headless extension must be loaded in the test instance.',
        );
    }

    /**
     * @test
     */
    public function tcaApiDependencyIsLoaded(): void
    {
        self::assertTrue(
            ExtensionManagementUtility::isLoaded('tca_api'),
            'The tca-api dependency must be loaded alongside tca_api_headless.',
        );
    }
}
