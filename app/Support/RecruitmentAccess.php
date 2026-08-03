<?php

namespace App\Support;

use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentInterview;
use App\Models\User;

class RecruitmentAccess
{
    public static function canAccessCandidate(User $user, RecruitmentCandidate $candidate): bool
    {
        if (
            $user->hasPermission('recruitment.manage')
            || $user->hasPermission('recruitment.approve')
        ) {
            return true;
        }

        $candidate->loadMissing('requisition:id,hiring_manager_user_id');

        if (
            (int) $candidate->owner_user_id === (int) $user->getAuthIdentifier()
            || (int) $candidate->requisition?->hiring_manager_user_id
                === (int) $user->getAuthIdentifier()
        ) {
            return true;
        }

        return $candidate->interviews()
            ->get(['panel_user_ids'])
            ->contains(fn (RecruitmentInterview $interview) => in_array(
                (int) $user->getAuthIdentifier(),
                array_map('intval', $interview->panel_user_ids ?? []),
                true,
            ));
    }
}
