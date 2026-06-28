<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\Serializer\UploadsBlockSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class UploadsBlockSerializerTest extends TestCase
{
    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    private function subjectWith(FileReference ...$references): UploadsBlockSerializer
    {
        $repository = self::createStub(FileRepository::class);
        $repository->method('findByRelation')->willReturn($references);

        return new UploadsBlockSerializer($repository);
    }

    #[Test]
    public function supportsOnlyUploadsCType(): void
    {
        self::assertTrue($this->subjectWith()->supports(['CType' => 'uploads']));
        self::assertFalse($this->subjectWith()->supports(['CType' => 'text']));
    }

    #[Test]
    public function serializesFilesWithFallbackTitle(): void
    {
        $reference = self::createStub(FileReference::class);
        $reference->method('getPublicUrl')->willReturn('fileadmin/report.pdf');
        $reference->method('getProperty')->willReturnMap([
            ['title', ''],
            ['name', 'report.pdf'],
            ['size', 2048],
        ]);

        $block = $this->subjectWith($reference)->serialize(
            ['uid' => 11, 'CType' => 'uploads', 'header' => 'Downloads'],
            $this->context(),
        );

        self::assertSame('uploads', $block['type']);
        self::assertSame('Downloads', $block['data']['headline']);
        self::assertCount(1, $block['data']['files']);

        $file = $block['data']['files'][0];
        self::assertSame('/fileadmin/report.pdf', $file['src']);
        self::assertSame('report.pdf', $file['title']);
        self::assertSame(2048, $file['size']);
    }

    #[Test]
    public function emptyRelationYieldsEmptyFiles(): void
    {
        $block = $this->subjectWith()->serialize(
            ['uid' => 12, 'CType' => 'uploads', 'header' => ''],
            $this->context(),
        );

        self::assertArrayNotHasKey('headline', $block['data']);
        self::assertSame([], $block['data']['files']);
    }
}
