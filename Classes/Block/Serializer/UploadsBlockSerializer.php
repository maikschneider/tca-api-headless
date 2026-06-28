<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\FileRepository;

/**
 * Serializes the "uploads" content element into an `uploads` block:
 * `{ headline?, files: { src, title?, size? }[] }`.
 */
final class UploadsBlockSerializer implements BlockSerializerInterface
{
    public function __construct(
        private readonly FileRepository $fileRepository,
    ) {
    }

    public function supports(array $row): bool
    {
        return ($row['CType'] ?? '') === 'uploads';
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }
        $data['files'] = $this->files((int)($row['uid'] ?? 0));

        return [
            'type' => 'uploads',
            'id' => (int)($row['uid'] ?? 0),
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
    private function files(int $uid): array
    {
        $files = [];
        foreach ($this->fileRepository->findByRelation('tt_content', 'media', $uid) as $reference) {
            if ($reference instanceof FileReference) {
                $files[] = $this->file($reference);
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function file(FileReference $reference): array
    {
        $url = (string)($reference->getPublicUrl() ?? '');
        if ($url !== '' && !str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        $title = $reference->getProperty('title');
        if (!is_string($title) || $title === '') {
            $title = $reference->getProperty('name');
        }
        $size = $reference->getProperty('size');

        return [
            'src' => $url,
            'title' => is_string($title) && $title !== '' ? $title : null,
            'size' => is_numeric($size) ? (int)$size : null,
        ];
    }
}
