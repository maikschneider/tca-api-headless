<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApi\Filter\FilterContext;
use MaikSchneider\TcaApi\Filter\FilterInterface;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendGroupRestriction;

/**
 * Limits a read to the records that the frontend user groups of the current
 * request may see (fe_group), like the frontend rendering does.
 */
final class FrontendGroupFilter implements FilterInterface
{
    public function apply(QueryBuilder $qb, FilterContext $context): void
    {
        $qb->getRestrictions()->add(new FrontendGroupRestriction());
    }
}
