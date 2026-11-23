<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The group check of the frontend: a record without fe_group is public,
 * otherwise one of the user's groups (incl. -1 "hide at login", -2 "any
 * login") must match.
 */
final readonly class FrontendGroups
{
    public function __construct(private Context $context)
    {
    }

    public function grant(mixed $feGroup): bool
    {
        $required = GeneralUtility::trimExplode(',', (string)$feGroup, true);
        if ($required === []) {
            return true;
        }
        $groupIds = array_map(strval(...), (array)$this->context->getPropertyFromAspect('frontend.user', 'groupIds', [0, -1]));

        return array_intersect($required, $groupIds) !== [];
    }
}
