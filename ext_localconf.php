<?php

declare(strict_types=1);

use MaikSchneider\TcaApiHeadless\Cache\CacheInvalidationHook;

defined('TYPO3') or die();

// ── Headless page payload cache ─────────────────────────────────────────────
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tca_api_headless'] ??= [];

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['tca_api_headless']
    = CacheInvalidationHook::class . '->clearCachePostProc';
