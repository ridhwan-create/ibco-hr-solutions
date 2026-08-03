<?php

namespace App\Support;

use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimRequest;
use App\Models\ClearanceTask;
use App\Models\DisciplineCase;
use App\Models\EmployeeLeaveRequest;
use App\Models\HrDocument;
use App\Models\LeaveApprovalAssignment;
use App\Models\OnboardingTask;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\PerformanceReview;
use App\Models\RecruitmentInterview;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentRequisition;
use App\Models\SeparationCase;
use App\Models\TrainingApprovalAssignment;
use App\Models\TrainingRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LeaveApprovalAlerts
{
    /**
     * @return array{
     *     enabled: bool,
     *     total: int,
     *     supervisor: int,
     *     hr: int,
     *     polling_seconds: int,
     *     leave_total: int,
     *     leave_supervisor: int,
     *     leave_hr: int,
     *     overtime_total: int,
     *     overtime_supervisor: int,
     *     overtime_hr: int,
     *     claim_total: int,
     *     claim_supervisor: int,
     *     claim_finance: int,
     *     performance_total: int,
     *     performance_supervisor: int,
     *     performance_hr: int,
     *     recruitment_total: int,
     *     recruitment_approval: int,
     *     recruitment_interview: int,
     *     onboarding_total: int,
     *     onboarding_registration: int,
     *     onboarding_overdue: int,
     *     training_total: int,
     *     training_supervisor: int,
     *     training_hr: int,
     *     document_total: int,
     *     document_approval: int,
     *     document_expiring: int,
     *     discipline_total: int,
     *     discipline_triage: int,
     *     discipline_investigation: int,
     *     discipline_decision: int,
     *     separation_total: int,
     *     separation_supervisor: int,
     *     separation_hr: int,
     *     separation_clearance: int,
     *     separation_final_review: int
     * }
     */
    public function summarizeFor(?User $user): array
    {
        $summary = [
            'enabled' => false,
            'total' => 0,
            'supervisor' => 0,
            'hr' => 0,
            'polling_seconds' => 60,
            'leave_total' => 0,
            'leave_supervisor' => 0,
            'leave_hr' => 0,
            'overtime_total' => 0,
            'overtime_supervisor' => 0,
            'overtime_hr' => 0,
            'claim_total' => 0,
            'claim_supervisor' => 0,
            'claim_finance' => 0,
            'performance_total' => 0,
            'performance_supervisor' => 0,
            'performance_hr' => 0,
            'recruitment_total' => 0,
            'recruitment_approval' => 0,
            'recruitment_interview' => 0,
            'onboarding_total' => 0,
            'onboarding_registration' => 0,
            'onboarding_overdue' => 0,
            'training_total' => 0,
            'training_supervisor' => 0,
            'training_hr' => 0,
            'document_total' => 0,
            'document_approval' => 0,
            'document_expiring' => 0,
            'discipline_total' => 0,
            'discipline_triage' => 0,
            'discipline_investigation' => 0,
            'discipline_decision' => 0,
            'separation_total' => 0,
            'separation_supervisor' => 0,
            'separation_hr' => 0,
            'separation_clearance' => 0,
            'separation_final_review' => 0,
        ];

        if (! $user) {
            return $summary;
        }

        $canManageLeave = $user->hasPermission('leave.manage');
        $canSuperviseLeave = $user->hasPermission('leave.supervise');
        $canApproveLeave = $user->hasPermission('leave.approve');
        $canManageOvertime = $user->hasPermission('overtime.manage');
        $canSuperviseOvertime = $user->hasPermission('overtime.supervise');
        $canApproveOvertime = $user->hasPermission('overtime.approve');
        $canManageClaims = $user->hasPermission('claims.manage');
        $canSuperviseClaims = $user->hasPermission('claims.supervise');
        $canApproveClaims = $user->hasPermission('claims.approve');
        $canSupervisePerformance = $user->hasPermission('performance.supervise');
        $canModeratePerformance = $user->hasPermission('performance.moderate');
        $canFinalizePerformance = $user->hasPermission('performance.finalize');
        $canApproveRecruitment = $user->hasPermission('recruitment.approve');
        $canInterview = $user->hasPermission('recruitment.interview');
        $canManageRecruitment = $user->hasPermission('recruitment.manage');
        $canManageOnboarding = $user->hasPermission('onboarding.manage');
        $canApproveOnboarding = $user->hasPermission('onboarding.approve');
        $canManageTraining = $user->hasPermission('training.manage');
        $canSuperviseTraining = $user->hasPermission('training.supervise');
        $canApproveTraining = $user->hasPermission('training.approve');
        $canManageDocuments = $user->hasPermission('documents.manage');
        $canApproveDocuments = $user->hasPermission('documents.approve');
        $canManageDiscipline = $user->hasPermission('discipline.manage');
        $canInvestigateDiscipline = $user->hasPermission('discipline.investigate');
        $canApproveDiscipline = $user->hasPermission('discipline.approve');
        $canManageSeparation = $user->hasPermission('separation.manage');
        $canSuperviseSeparation = $user->hasPermission('separation.supervise');
        $canApproveSeparation = $user->hasPermission('separation.approve');
        $canClearSeparation = $user->hasPermission('separation.clearance');

        if (
            ! $canManageLeave
            && ! $canSuperviseLeave
            && ! $canApproveLeave
            && ! $canManageOvertime
            && ! $canSuperviseOvertime
            && ! $canApproveOvertime
            && ! $canManageClaims
            && ! $canSuperviseClaims
            && ! $canApproveClaims
            && ! $canSupervisePerformance
            && ! $canModeratePerformance
            && ! $canFinalizePerformance
            && ! $canApproveRecruitment
            && ! $canInterview
            && ! $canManageRecruitment
            && ! $canManageOnboarding
            && ! $canApproveOnboarding
            && ! $canManageTraining
            && ! $canSuperviseTraining
            && ! $canApproveTraining
            && ! $canManageDocuments
            && ! $canApproveDocuments
            && ! $canManageDiscipline
            && ! $canInvestigateDiscipline
            && ! $canApproveDiscipline
            && ! $canManageSeparation
            && ! $canSuperviseSeparation
            && ! $canApproveSeparation
            && ! $canClearSeparation
        ) {
            return $summary;
        }

        $leaveReady = Schema::hasTable('employee_leave_requests')
            && Schema::hasTable('leave_approval_assignments');
        $leaveSupervisor = $leaveReady && $canSuperviseLeave
            ? $this->leaveSupervisorQuery($user, $canManageLeave)->count()
            : 0;
        $leaveHr = $leaveReady && $canApproveLeave
            ? EmployeeLeaveRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'hr')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $overtimeReady = Schema::hasTable('overtime_requests')
            && Schema::hasTable('overtime_approval_assignments');
        $overtimeSupervisor = $overtimeReady && $canSuperviseOvertime
            ? $this->overtimeSupervisorQuery($user, $canManageOvertime)->count()
            : 0;
        $overtimeHr = $overtimeReady && $canApproveOvertime
            ? OvertimeRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'hr')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $claimsReady = Schema::hasTable('claim_requests')
            && Schema::hasTable('claim_approval_assignments');
        $claimSupervisor = $claimsReady && $canSuperviseClaims
            ? $this->claimSupervisorQuery($user, $canManageClaims)->count()
            : 0;
        $claimFinance = $claimsReady && $canApproveClaims
            ? ClaimRequest::query()
                ->where('status', 'pending')
                ->where(function (Builder $query) {
                    $query->where('approval_stage', 'finance')
                        ->orWhereNull('approval_stage');
                })
                ->count()
            : 0;
        $performanceReady = Schema::hasTable('performance_reviews');
        $performanceSupervisor = $performanceReady && $canSupervisePerformance
            ? PerformanceReview::query()
                ->where('status', 'supervisor_assessment')
                ->when(
                    ! $user->hasPermission('performance.manage'),
                    fn (Builder $query) => $query->where(
                        'supervisor_user_id',
                        $user->getAuthIdentifier(),
                    ),
                )
                ->count()
            : 0;
        $performanceModeration = $performanceReady && $canModeratePerformance
            ? PerformanceReview::query()
                ->where('status', 'hr_moderation')
                ->whereNull('moderated_score')
                ->count()
            : 0;
        $performanceFinalization = $performanceReady && $canFinalizePerformance
            ? PerformanceReview::query()
                ->where('status', 'hr_moderation')
                ->whereNotNull('moderated_score')
                ->count()
            : 0;
        $performanceHr = $performanceModeration + $performanceFinalization;
        $recruitmentReady = Schema::hasTable('recruitment_requisitions')
            && Schema::hasTable('recruitment_offers')
            && Schema::hasTable('recruitment_interviews')
            && Schema::hasTable('recruitment_scorecards');
        $recruitmentApproval = $recruitmentReady && $canApproveRecruitment
            ? RecruitmentRequisition::query()
                ->where('status', 'pending_approval')
                ->count()
                + RecruitmentOffer::query()
                    ->where('status', 'pending_approval')
                    ->count()
            : 0;
        $recruitmentInterview = $recruitmentReady && $canInterview
            ? RecruitmentInterview::query()
                ->where('status', 'scheduled')
                ->whereJsonContains(
                    'panel_user_ids',
                    (int) $user->getAuthIdentifier(),
                )
                ->whereDoesntHave(
                    'scorecards',
                    fn (Builder $query) => $query->where(
                        'panel_user_id',
                        $user->getAuthIdentifier(),
                    ),
                )
                ->count()
            : 0;
        $onboardingReady = Schema::hasTable('onboarding_tasks');
        $onboardingRegistration = $onboardingReady
            && $canApproveOnboarding
            && Schema::hasColumn('onboarding_cases', 'employee_record_id')
            ? \App\Models\OnboardingCase::query()
                ->whereIn('status', ['pending', 'active'])
                ->whereNull('employee_record_id')
                ->whereNull('legacy_employee_id')
                ->whereNull('employee_user_id')
                ->whereHas('offer', fn (Builder $query) => $query->where('status', 'accepted'))
                ->count()
            : 0;
        $onboardingOverdue = $onboardingReady && $canManageOnboarding
            ? OnboardingTask::query()
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereDate('due_date', '<', today())
                ->count()
            : 0;
        $trainingReady = Schema::hasTable('training_requests')
            && Schema::hasTable('training_approval_assignments');
        $trainingSupervisor = $trainingReady && $canSuperviseTraining
            ? $this->trainingSupervisorQuery($user, $canManageTraining)->count()
            : 0;
        $trainingHr = $trainingReady && $canApproveTraining
            ? TrainingRequest::query()
                ->where('status', 'pending')
                ->where('approval_stage', 'hr')
                ->count()
            : 0;
        $documentReady = Schema::hasTable('hr_documents');
        $documentApproval = $documentReady && $canApproveDocuments
            ? HrDocument::query()
                ->where('status', 'pending_approval')
                ->where(fn (Builder $query) => $query
                    ->where('approver_user_id', $user->getAuthIdentifier())
                    ->orWhereNull('approver_user_id'))
                ->count()
            : 0;
        $documentExpiring = $documentReady && $canManageDocuments
            ? HrDocument::query()
                ->whereIn('status', ['issued', 'acknowledged'])
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                ->count()
            : 0;
        $disciplineReady = Schema::hasTable('discipline_cases')
            && Schema::hasTable('discipline_case_members');
        $disciplineTriage = $disciplineReady && $canManageDiscipline
            ? DisciplineCase::query()
                ->whereIn('status', ['submitted', 'triage', 'show_cause_pending'])
                ->count()
            : 0;
        $disciplineInvestigation = $disciplineReady && $canInvestigateDiscipline
            ? DisciplineCase::query()
                ->where('status', 'investigation')
                ->when(! $canManageDiscipline, fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('investigator_user_id', $user->getAuthIdentifier())
                        ->orWhereHas('members', fn (Builder $query) => $query
                            ->where('user_id', $user->getAuthIdentifier())
                            ->whereNull('recused_at'))))
                ->count()
            : 0;
        $disciplineDecision = $disciplineReady && $canApproveDiscipline
            ? DisciplineCase::query()
                ->where(fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query
                        ->where('status', 'decision')
                        ->whereNull('decided_at'))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('status', 'appeal')
                        ->where('decided_by', '<>', $user->getAuthIdentifier())))
                ->count()
            : 0;
        $separationReady = Schema::hasTable('separation_cases')
            && Schema::hasTable('clearance_tasks');
        $separationSupervisor = $separationReady && $canSuperviseSeparation
            ? SeparationCase::query()
                ->where('status', 'pending_approval')
                ->where('approval_stage', 'supervisor')
                ->when(
                    ! $canManageSeparation,
                    fn (Builder $query) => $query->where(
                        'supervisor_user_id',
                        $user->getAuthIdentifier(),
                    ),
                )
                ->count()
            : 0;
        $separationHr = $separationReady && $canApproveSeparation
            ? SeparationCase::query()
                ->where('status', 'pending_approval')
                ->where('approval_stage', 'hr')
                ->where(fn (Builder $query) => $query
                    ->where('hr_approver_user_id', $user->getAuthIdentifier())
                    ->orWhereNull('hr_approver_user_id'))
                ->count()
            : 0;
        $separationClearance = $separationReady && $canClearSeparation
            ? ClearanceTask::query()
                ->whereIn('status', ['pending', 'in_progress', 'submitted', 'rejected'])
                ->where(fn (Builder $query) => $query
                    ->where('assigned_user_id', $user->getAuthIdentifier())
                    ->when($canManageSeparation, fn (Builder $query) => $query
                        ->orWhereNull('assigned_user_id')))
                ->count()
            : 0;
        $separationFinalReview = $separationReady && $canApproveSeparation
            ? SeparationCase::query()->where('status', 'final_review')->count()
            : 0;
        $leaveTotal = $leaveSupervisor + $leaveHr;
        $overtimeTotal = $overtimeSupervisor + $overtimeHr;
        $claimTotal = $claimSupervisor + $claimFinance;
        $performanceTotal = $performanceSupervisor + $performanceHr;
        $recruitmentTotal = $recruitmentApproval + $recruitmentInterview;
        $onboardingTotal = $onboardingRegistration + $onboardingOverdue;
        $trainingTotal = $trainingSupervisor + $trainingHr;
        $documentTotal = $documentApproval + $documentExpiring;
        $disciplineTotal = $disciplineTriage + $disciplineInvestigation
            + $disciplineDecision;
        $separationTotal = $separationSupervisor + $separationHr
            + $separationClearance + $separationFinalReview;

        return [
            ...$summary,
            'enabled' => true,
            'total' => $leaveTotal + $overtimeTotal + $claimTotal + $performanceTotal
                + $recruitmentTotal + $onboardingTotal + $trainingTotal
                + $documentTotal + $disciplineTotal + $separationTotal,
            'supervisor' => $leaveSupervisor + $overtimeSupervisor + $claimSupervisor
                + $performanceSupervisor + $recruitmentInterview + $trainingSupervisor
                + $disciplineInvestigation
                + $separationSupervisor + $separationClearance,
            'hr' => $leaveHr + $overtimeHr + $claimFinance + $performanceHr
                + $recruitmentApproval + $onboardingTotal + $trainingHr
                + $documentApproval + $documentExpiring + $disciplineTriage + $disciplineDecision
                + $separationHr + $separationFinalReview,
            'leave_total' => $leaveTotal,
            'leave_supervisor' => $leaveSupervisor,
            'leave_hr' => $leaveHr,
            'overtime_total' => $overtimeTotal,
            'overtime_supervisor' => $overtimeSupervisor,
            'overtime_hr' => $overtimeHr,
            'claim_total' => $claimTotal,
            'claim_supervisor' => $claimSupervisor,
            'claim_finance' => $claimFinance,
            'performance_total' => $performanceTotal,
            'performance_supervisor' => $performanceSupervisor,
            'performance_hr' => $performanceHr,
            'recruitment_total' => $recruitmentTotal,
            'recruitment_approval' => $recruitmentApproval,
            'recruitment_interview' => $recruitmentInterview,
            'onboarding_total' => $onboardingTotal,
            'onboarding_registration' => $onboardingRegistration,
            'onboarding_overdue' => $onboardingOverdue,
            'training_total' => $trainingTotal,
            'training_supervisor' => $trainingSupervisor,
            'training_hr' => $trainingHr,
            'document_total' => $documentTotal,
            'document_approval' => $documentApproval,
            'document_expiring' => $documentExpiring,
            'discipline_total' => $disciplineTotal,
            'discipline_triage' => $disciplineTriage,
            'discipline_investigation' => $disciplineInvestigation,
            'discipline_decision' => $disciplineDecision,
            'separation_total' => $separationTotal,
            'separation_supervisor' => $separationSupervisor,
            'separation_hr' => $separationHr,
            'separation_clearance' => $separationClearance,
            'separation_final_review' => $separationFinalReview,
        ];
    }

    private function leaveSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = EmployeeLeaveRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = LeaveApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    private function overtimeSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = OvertimeRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = OvertimeApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    private function claimSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = ClaimRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = ClaimApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    private function trainingSupervisorQuery(User $user, bool $canManage): Builder
    {
        $query = TrainingRequest::query()
            ->where('status', 'pending')
            ->where('approval_stage', 'supervisor');

        if ($canManage) {
            return $query;
        }

        $departmentIds = TrainingApprovalAssignment::query()
            ->where('approver_user_id', $user->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->where(fn (Builder $query) => $query
            ->whereIn('department_id', $departmentIds)
            ->orWhere('supervisor_user_id', $user->getAuthIdentifier()));
    }
}
