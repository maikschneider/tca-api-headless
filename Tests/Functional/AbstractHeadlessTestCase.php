<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all tca_api_headless functional tests.
 *
 * Boots a real TYPO3 instance with the extension (and its tca-api dependency)
 * loaded, so composition can be exercised end-to-end against a database.
 */
abstract class AbstractHeadlessTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
    ];

    protected array $coreExtensionsToLoad = [
        'frontend',
        'fluid_styled_content',
        'seo',
    ];
}
