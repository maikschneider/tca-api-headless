<?php

declare(strict_types=1);

use MaikSchneider\HeadlessPages\Cache\CacheInvalidationHook;

defined('TYPO3') or die();

// ── Headless page payload cache ─────────────────────────────────────────────
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['headless_pages'] ??= [];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['headless_pages']
    = CacheInvalidationHook::class . '->clearCachePostProc';
