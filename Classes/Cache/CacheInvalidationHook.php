<?php

declare(strict_types=1);

namespace MaikSchneider\HeadlessPages\Cache;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * DataHandler hook that flushes cached page payloads when records change.
 *
 * Registered in ext_localconf.php as a clearCachePostProc hook. TYPO3 sends
 * cache tags in the format ['tableName' => [...]] when records are created,
 * updated, or deleted; cached pages are tagged with `pages_{uid}` and
 * `tt_content_{uid}` so the matching entries are flushed.
 */
final class CacheInvalidationHook
{
    public function __construct(
        private readonly FrontendInterface $cache,
    ) {
    }

    /**
     * @param array{tags?: array<string, mixed>} $params
     */
    public function clearCachePostProc(array $params): void
    {
        $tags = $params['tags'] ?? [];
        if (!is_array($tags) || $tags === []) {
            return;
        }

        $cacheTags = [];
        foreach (array_keys($tags) as $tag) {
            if (is_string($tag) && $tag !== '') {
                $cacheTags[] = $tag;
            }
        }

        if ($cacheTags !== []) {
            $this->cache->flushByTags($cacheTags);
        }
    }
}
