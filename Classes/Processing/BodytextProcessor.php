<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Processing;

use MaikSchneider\TcaApi\Configuration\ColumnDefinition;
use MaikSchneider\TcaApi\Serializer\Processing\ColumnProcessorInterface;
use MaikSchneider\TcaApi\Serializer\Processing\LinkLanguageResolver;
use MaikSchneider\TcaApi\Serializer\Processing\TypoLinkProcessor;
use MaikSchneider\TcaApiHeadless\RichText\HtmlToPortableText;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\CsvUtility;

/**
 * Structures tt_content.bodytext by the record's CType, the way fluid_styled_content
 * reads it: bullets as a list of items, a table as rows, everything else as rich
 * text converted to Portable Text. Links in rich text become absolute URLs; links
 * that don't resolve to one keep their text without annotation.
 */
final readonly class BodytextProcessor implements ColumnProcessorInterface
{
    private const LINK_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function __construct(
        private HtmlToPortableText $htmlToPortableText,
        private TypoLinkProcessor $typoLinkProcessor,
        private LinkService $linkService,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function process(mixed $value, ColumnDefinition $config, array $context): mixed
    {
        $bodytext = (string)$value;
        $row = $context['rawRow'] ?? [];

        return match ($row['CType'] ?? null) {
            'bullets' => (int)($row['bullets_type'] ?? 0) === 2 ? $this->definitionList($bodytext) : $this->bulletList($bodytext),
            'table' => $this->table($bodytext, $row),
            default => $this->htmlToPortableText->convert(
                $bodytext,
                fn (string $href): ?string => $this->absoluteUrl($href, $config, $context),
            ),
        };
    }

    /**
     * @return list<string>
     */
    private function bulletList(string $bodytext): array
    {
        $lines = array_map(trim(...), preg_split('/\R/', $bodytext) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @return list<array{term: string, definition: string}>
     */
    private function definitionList(string $bodytext): array
    {
        return array_map(
            static fn (array $cells): array => ['term' => $cells[0], 'definition' => $cells[1] ?? ''],
            $this->rows($bodytext, '|', '"', 0),
        );
    }

    /**
     * Header row and footer row follow table_header_position (1 = top) and
     * table_tfoot like the frontend rendering; a single row is never a footer.
     *
     * @param array<string, mixed> $row
     * @return array{head: list<string>, body: list<list<string>>, foot: list<string>}
     */
    private function table(string $bodytext, array $row): array
    {
        $rows = $this->rows(
            $bodytext,
            \chr((int)($row['table_delimiter'] ?? 0) ?: 124),
            \chr((int)($row['table_enclosure'] ?? 0)),
            (int)($row['cols'] ?? 0),
        );

        $foot = (bool)($row['table_tfoot'] ?? false) && \count($rows) > 1 ? array_pop($rows) : [];
        $head = (int)($row['table_header_position'] ?? 0) === 1 && $rows !== [] ? array_shift($rows) : [];

        return ['head' => $head, 'body' => $rows, 'foot' => $foot];
    }

    /**
     * @return list<list<string>> rows without blank lines
     */
    private function rows(string $bodytext, string $delimiter, string $enclosure, int $maximumColumns): array
    {
        $rows = [];
        foreach (CsvUtility::csvToArray($bodytext, $delimiter, $enclosure, $maximumColumns) as $cells) {
            $cells = array_map(static fn (mixed $cell): string => trim((string)$cell), array_values($cells));
            if (implode('', $cells) !== '') {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * Resolves a link like a typolink field (pages in the request language) and
     * makes file and root-relative links absolute against the request host.
     *
     * @param array<string, mixed> $context
     */
    private function absoluteUrl(string $href, ColumnDefinition $config, array $context): ?string
    {
        $url = $this->typoLinkProcessor->process($href, $config, $context);
        if (\is_string($url) && str_starts_with($url, 't3://')) {
            $url = $this->fileUrl($url);
        }
        if (!\is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $normalizedParams = LinkLanguageResolver::contextRequest($context)?->getAttribute('normalizedParams');
            if (!$normalizedParams instanceof NormalizedParams) {
                return null;
            }
            $url = $normalizedParams->getRequestHost() . $url;
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, self::LINK_SCHEMES, true) ? $url : null;
    }

    private function fileUrl(string $link): ?string
    {
        try {
            $file = $this->linkService->resolve($link)['file'] ?? null;
        } catch (\Exception) {
            return null;
        }
        if (!$file instanceof File) {
            return null;
        }

        $publicUrl = $file->getPublicUrl();
        if ($publicUrl === null || parse_url($publicUrl, PHP_URL_SCHEME) !== null) {
            return $publicUrl;
        }

        return '/' . ltrim($publicUrl, '/');
    }
}
