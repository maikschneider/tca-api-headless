<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for all headless_pages functional tests.
 *
 * Boots a real TYPO3 instance with the extension loaded, so composition can be
 * exercised end-to-end against a database.
 */
abstract class AbstractHeadlessTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'maikschneider/headless-pages',
    ];

    protected array $coreExtensionsToLoad = [
        'frontend',
        'fluid_styled_content',
        'seo',
    ];
}
