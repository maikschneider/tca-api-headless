<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block\Serializer;

use MaikSchneider\TcaApiHeadless\Block\BlockContext;
use MaikSchneider\TcaApiHeadless\Block\BlockSerializerInterface;

/**
 * Serializes the "table" content element into a `table` block:
 * `{ headline?, head?: string[][], body: string[][] }`.
 *
 * The bodytext holds one table row per line, cells separated by the default
 * delimiter `|`. A FlexForm-configured delimiter is a later enhancement.
 * `table_header_position == 1` marks the first row as the header.
 */
final class TableBlockSerializer implements BlockSerializerInterface
{
    private const DELIMITER = '|';

    public function supports(array $row): bool
    {
        return ($row['CType'] ?? '') === 'table';
    }

    public function serialize(array $row, BlockContext $context): array
    {
        $rows = $this->parse((string)($row['bodytext'] ?? ''));

        $data = [];
        if (($row['header'] ?? '') !== '') {
            $data['headline'] = (string)$row['header'];
        }

        if ((int)($row['table_header_position'] ?? 0) === 1 && $rows !== []) {
            $data['head'] = [array_shift($rows)];
        }

        $data['body'] = array_values($rows);

        return [
            'type' => 'table',
            'id' => (int)($row['uid'] ?? 0),
            'data' => $data,
        ];
    }

    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @return list<list<string>>
     */
    private function parse(string $bodytext): array
    {
        $rows = [];
        foreach (preg_split('/\r\n|\r|\n/', $bodytext) ?: [] as $line) {
            if ($line === '') {
                continue;
            }
            $rows[] = explode(self::DELIMITER, $line);
        }

        return $rows;
    }
}
