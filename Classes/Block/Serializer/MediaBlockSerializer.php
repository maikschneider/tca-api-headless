<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerInterface;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;

/**
 * Serializes "textmedia" and "image" content elements into a `media` block:
 * `{ headline?, body?: PortableText, images: image[] }`.
 *
 * Images are emitted as the contract's own `image` value object (see
 * Contract/Schema/block.schema.json `$defs.image`). Image processing
 * (resize/crop) is a later enhancement; the original file URL and dimensions
 * are returned for now.
 */
final class MediaBlockSerializer implements BlockSerializerInterface
{
    /**
     * @var array<string, string> CType → FAL relation field name.
     */
    private const FIELD_BY_CTYPE = [
        'textmedia' => 'assets',
        'image' => 'image',
    ];

    public function __construct(
        private readonly HtmlToPortableText $htmlToPortableText,
        private readonly FileRepository $fileRepository,
    ) {
    }

    public function supports(array $row): bool
    {
        return isset(self::FIELD_BY_CTYPE[$row['CType'] ?? '']);
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $cType = (string)($row['CType'] ?? '');
        $uid = (int)($row['uid'] ?? 0);

        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }
        $body = $this->htmlToPortableText->convert((string)($row['bodytext'] ?? ''));
        if ($body !== []) {
            $data['body'] = $body;
        }
        $data['images'] = $this->images($uid, self::FIELD_BY_CTYPE[$cType]);

        return [
            'type' => 'media',
            'id' => $uid,
            'data' => $data,
        ];
    }

    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function images(int $uid, string $field): array
    {
        $images = [];
        foreach ($this->fileRepository->findByRelation('tt_content', $field, $uid) as $reference) {
            if ($reference instanceof FileReference) {
                $images[] = $this->image($reference);
            }
        }

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    private function image(FileReference $reference): array
    {
        $url = (string)($reference->getPublicUrl() ?? '');
        if ($url !== '' && !str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        $alt = $reference->getProperty('alternative');
        $title = $reference->getProperty('title');
        $width = $reference->getProperty('width');
        $height = $reference->getProperty('height');

        return [
            'src' => $url,
            'width' => is_numeric($width) ? (int)$width : null,
            'height' => is_numeric($height) ? (int)$height : null,
            'alt' => is_string($alt) && $alt !== '' ? $alt : null,
            'title' => is_string($title) && $title !== '' ? $title : null,
            'crop' => null,
        ];
    }
}
