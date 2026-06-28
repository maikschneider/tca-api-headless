<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Block;

use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Immutable context passed to every block serializer.
 */
final class BlockContext
{
    public function __construct(
        public readonly SiteLanguage $language,
        public readonly int $pageId,
    ) {
    }
}
