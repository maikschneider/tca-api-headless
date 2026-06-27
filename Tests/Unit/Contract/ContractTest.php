<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Contract;

use MaikSchneider\TcaApiHeadless\Contract\Contract;
use PHPUnit\Framework\TestCase;

final class ContractTest extends TestCase
{
    /**
     * @test
     */
    public function versionIsExposed(): void
    {
        self::assertSame('1.0', Contract::VERSION);
    }
}
