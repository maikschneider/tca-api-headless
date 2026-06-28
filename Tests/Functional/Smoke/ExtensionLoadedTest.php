<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Functional\Smoke;

use MaikSchneider\HeadlessPages\Tests\Functional\AbstractHeadlessTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

final class ExtensionLoadedTest extends AbstractHeadlessTestCase
{
    #[Test]
    public function extensionIsLoaded(): void
    {
        self::assertTrue(
            ExtensionManagementUtility::isLoaded('headless_pages'),
            'The headless_pages extension must be loaded in the test instance.',
        );
    }
}
