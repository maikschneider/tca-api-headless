<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Block;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerInterface;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerRegistry;
use MaikSchneider\TcaApiHeadless\Block\Serializer\FallbackBlockSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class BlockSerializerRegistryTest extends TestCase
{
    private function context(): BlockContext
    {
        return new BlockContext($this->createStub(SiteLanguage::class), 1);
    }

    #[Test]
    public function higherPrioritySerializerWinsOverFallback(): void
    {
        $special = new class implements BlockSerializerInterface {
            public function supports(array $row): bool
            {
                return ($row['CType'] ?? '') === 'text';
            }

            public function serialize(array $row, BlockContext $context): array
            {
                return ['type' => 'text-special', 'id' => (int)$row['uid'], 'data' => new \stdClass()];
            }

            public function getPriority(): int
            {
                return 10;
            }
        };

        $registry = new BlockSerializerRegistry([new FallbackBlockSerializer(), $special]);

        $block = $registry->serialize(['uid' => 5, 'CType' => 'text'], $this->context());
        self::assertSame('text-special', $block['type']);
        self::assertSame(5, $block['id']);
    }

    #[Test]
    public function unmappedCTypeFallsBackToRawCType(): void
    {
        $registry = new BlockSerializerRegistry([new FallbackBlockSerializer()]);

        $block = $registry->serialize(['uid' => 7, 'CType' => 'my_plugin', 'header' => 'Hi'], $this->context());
        self::assertSame('my_plugin', $block['type']);
        self::assertSame(7, $block['id']);
        self::assertSame('Hi', $block['data']['headline']);
    }
}
