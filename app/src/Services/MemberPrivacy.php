<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;

/** Shapes list rows after the controller has obtained a per-member policy decision. */
final class MemberPrivacy
{
    /** Return only public list fields when the per-member PII decision is denied. */
    public static function listRow(Member $member, bool $canViewPii): Member
    {
        if ($canViewPii) {
            return $member;
        }

        return new Member(array_intersect_key($member->toArray(), array_flip([
            'id', 'public_id', 'sca_name', 'status', 'branch_id', 'branch',
        ])));
    }
}
