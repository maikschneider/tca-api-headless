<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\Serializer\MediaBlockSerializer;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class MediaBlockSerializerTest extends TestCase
{
    private function context(): BlockContext
    {
        return new BlockContext(self::createStub(SiteLanguage::class), 1);
    }

    private function subjectWith(FileReference ...$references): MediaBlockSerializer
    {
        $repository = self::createStub(FileRepository::class);
        $repository->method('findByRelation')->willReturn($references);

        return new MediaBlockSerializer(new HtmlToPortableText(), $repository);
    }

    #[Test]
    public function supportsTextmediaAndImage(): void
    {
        $subject = $this->subjectWith();
        self::assertTrue($subject->supports(['CType' => 'textmedia']));
        self::assertTrue($subject->supports(['CType' => 'image']));
        self::assertFalse($subject->supports(['CType' => 'text']));
    }

    #[Test]
    public function serializesHeaderBodyAndImages(): void
    {
        $reference = self::createStub(FileReference::class);
        $reference->method('getPublicUrl')->willReturn('fileadmin/team.jpg');
        $reference->method('getProperty')->willReturnMap([
            ['width', 800],
            ['height', 600],
            ['alternative', 'Team photo'],
            ['title', null],
        ]);

        $block = $this->subjectWith($reference)->serialize(
            ['uid' => 9, 'CType' => 'textmedia', 'header' => 'Gallery', 'bodytext' => '<p>Intro</p>'],
            $this->context(),
        );

        self::assertSame('media', $block['type']);
        self::assertSame(9, $block['id']);
        self::assertSame('Gallery', $block['data']['headline']);
        self::assertSame('Intro', $block['data']['body'][0]['children'][0]['text']);

        self::assertCount(1, $block['data']['images']);
        $image = $block['data']['images'][0];
        self::assertSame('/fileadmin/team.jpg', $image['src']);
        self::assertSame(800, $image['width']);
        self::assertSame(600, $image['height']);
        self::assertSame('Team photo', $image['alt']);
        self::assertNull($image['title']);
        self::assertNull($image['crop']);
    }

    #[Test]
    public function emptyBodyIsOmittedAndImagesMayBeEmpty(): void
    {
        $block = $this->subjectWith()->serialize(
            ['uid' => 10, 'CType' => 'image', 'header' => '', 'bodytext' => ''],
            $this->context(),
        );

        self::assertArrayNotHasKey('headline', $block['data']);
        self::assertArrayNotHasKey('body', $block['data']);
        self::assertSame([], $block['data']['images']);
    }
}
