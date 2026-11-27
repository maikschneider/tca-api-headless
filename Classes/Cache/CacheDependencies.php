<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Cache;

use MaikSchneider\TcaApi\Cache\CacheTagCollector;
use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\Filter\FilterContext;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Records what the cached response of the current request depends on beyond
 * the records TCA API tags itself: cache tags, and the next time one of the
 * records it is built from starts or ends, including records not visible yet.
 * A no-op without a cache miss to collect for.
 */
final readonly class CacheDependencies
{
    public function __construct(
        private CacheTagCollector $cacheTagCollector,
        private DataRepository $dataRepository,
        private Context $context,
    ) {
    }

    public function addTag(string $tag): void
    {
        $this->cacheTagCollector->addTag($tag);
    }

    /**
     * Expire with the next start or end of the records DataRepository::findCollection()
     * reads with the same arguments (or findById() when $uid is given).
     *
     * @param array<array{class-string, FilterContext}> $constraints
     */
    public function expireWithRecords(string $table, array $constraints, ApiDefinition $config, ?SiteLanguage $language, ?int $uid = null): void
    {
        if ($this->cacheTagCollector->isActive()) {
            $this->expireAt($this->dataRepository->findNextTimeTransition($table, $constraints, $config, $language, $uid));
        }
    }

    public function expireAt(?int $timestamp): void
    {
        if ($timestamp !== null) {
            $this->cacheTagCollector->limitLifetime($timestamp - (int)$this->context->getPropertyFromAspect('date', 'timestamp'));
        }
    }
}
