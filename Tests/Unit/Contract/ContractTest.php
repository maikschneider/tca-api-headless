<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Tests\Unit\Contract;

use MaikSchneider\HeadlessPages\Contract\Contract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContractTest extends TestCase
{
    #[Test]
    public function versionIsExposed(): void
    {
        self::assertSame('1.0', Contract::VERSION);
    }
}
