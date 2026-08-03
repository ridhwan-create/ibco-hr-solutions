<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AttendanceSettingsController;
use App\Http\Controllers\ClaimRequestController;
use App\Http\Controllers\ClaimSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentSettingsController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\DisciplineSettingsController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeDisciplineController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\EmployeeOnboardingController;
use App\Http\Controllers\EmployeeClaimController;
use App\Http\Controllers\EmployeeOvertimeController;
use App\Http\Controllers\EmployeePayslipController;
use App\Http\Controllers\EmployeePerformanceController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\EmployeeRosterController;
use App\Http\Controllers\EmployeeSeparationController;
use App\Http\Controllers\EmployeeTrainingController;
use App\Http\Controllers\EmployeeUserImportController;
use App\Http\Controllers\GeoAttendanceController;
use App\Http\Controllers\HrDocumentController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveSettingsController;
use App\Http\Controllers\MaklumatCutiController;
use App\Http\Controllers\MaklumatJawatanController;
use App\Http\Controllers\MaklumatKehadiranController;
use App\Http\Controllers\MaklumatOtController;
use App\Http\Controllers\MaklumatPayrollController;
use App\Http\Controllers\OvertimeRequestController;
use App\Http\Controllers\OvertimeSettingsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayrollSettingsController;
use App\Http\Controllers\PerformanceReviewController;
use App\Http\Controllers\PerformanceSettingsController;
use App\Http\Controllers\MaklumatPekerjaController;
use App\Http\Controllers\ReportBulananController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\RecruitmentSettingsController;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\ScheduleSettingsController;
use App\Http\Controllers\SeparationController;
use App\Http\Controllers\SeparationSettingsController;
use App\Http\Controllers\StatutorySettingsController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\TrainingSettingsController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\UserPasswordBulkResetController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::middleware('permission:employees.view')->group(function () {
        Route::get('pekerja', [MaklumatPekerjaController::class, 'index'])->name('pekerja.index');
        Route::get('pekerja/create', [MaklumatPekerjaController::class, 'create'])
            ->middleware('permission:employees.manage')
            ->name('pekerja.create');
        Route::get('pekerja/{id}', [MaklumatPekerjaController::class, 'show'])
            ->whereNumber('id')
            ->name('pekerja.show');
        Route::middleware('permission:employees.manage')->group(function () {
            Route::post('pekerja', [MaklumatPekerjaController::class, 'store'])->name('pekerja.store');
            Route::get('pekerja/{id}/edit', [MaklumatPekerjaController::class, 'edit'])
                ->whereNumber('id')
                ->name('pekerja.edit');
            Route::put('pekerja/{id}', [MaklumatPekerjaController::class, 'update'])
                ->whereNumber('id')
                ->name('pekerja.update');
            Route::delete('pekerja/{id}', [MaklumatPekerjaController::class, 'destroy'])
                ->whereNumber('id')
                ->name('pekerja.destroy');
        });
    });

    Route::middleware('permission:positions.view')->group(function () {
        Route::get('jawatan', [MaklumatJawatanController::class, 'index'])
            ->name('jawatan.index');
        Route::get('jawatan/create', [MaklumatJawatanController::class, 'create'])
            ->middleware('permission:positions.manage')
            ->name('jawatan.create');
        Route::get('jawatan/{id}', [MaklumatJawatanController::class, 'show'])
            ->whereNumber('id')
            ->name('jawatan.show');
        Route::middleware('permission:positions.manage')->group(function () {
            Route::post('jawatan', [MaklumatJawatanController::class, 'store'])
                ->name('jawatan.store');
            Route::get('jawatan/{id}/edit', [MaklumatJawatanController::class, 'edit'])
                ->whereNumber('id')
                ->name('jawatan.edit');
            Route::put('jawatan/{id}', [MaklumatJawatanController::class, 'update'])
                ->whereNumber('id')
                ->name('jawatan.update');
            Route::delete('jawatan/{id}', [MaklumatJawatanController::class, 'destroy'])
                ->whereNumber('id')
                ->name('jawatan.destroy');
        });
    });
    Route::get('kehadiran', [GeoAttendanceController::class, 'index'])
        ->middleware('permission:attendance.view')
        ->name('kehadiran.index');
    Route::get('kehadiran-asal', [MaklumatKehadiranController::class, 'index'])
        ->middleware('permission:attendance.view')
        ->name('kehadiran.legacy');
    Route::post('kehadiran/manual', [GeoAttendanceController::class, 'storeManual'])
        ->middleware('permission:attendance.manage')
        ->name('kehadiran.manual.store');
    Route::patch('kehadiran/{record}/pembetulan', [GeoAttendanceController::class, 'adjust'])
        ->middleware('permission:attendance.manage')
        ->name('kehadiran.adjust');
    Route::get('kehadiran/rakam', [GeoAttendanceController::class, 'clockPage'])
        ->middleware('permission:attendance.clock')
        ->name('kehadiran.clock');
    Route::post('kehadiran/rakam-masuk', [GeoAttendanceController::class, 'clockIn'])
        ->middleware(['permission:attendance.clock', 'throttle:10,1'])
        ->name('kehadiran.clock-in');
    Route::post('kehadiran/rakam-keluar', [GeoAttendanceController::class, 'clockOut'])
        ->middleware(['permission:attendance.clock', 'throttle:10,1'])
        ->name('kehadiran.clock-out');

    Route::get('profil-saya', [EmployeeProfileController::class, 'show'])
        ->middleware('permission:employee.profile.view')
        ->name('employee-profile.show');
    Route::put('profil-saya', [EmployeeProfileController::class, 'update'])
        ->middleware('permission:employee.profile.update')
        ->name('employee-profile.update');

    Route::get('cuti-saya', [EmployeeLeaveController::class, 'index'])
        ->middleware('permission:leave.self')
        ->name('employee-leave.index');
    Route::post('cuti-saya', [EmployeeLeaveController::class, 'store'])
        ->middleware('permission:leave.apply')
        ->name('employee-leave.store');
    Route::patch('cuti-saya/{leaveRequest}/batal', [EmployeeLeaveController::class, 'cancel'])
        ->middleware('permission:leave.apply')
        ->whereNumber('leaveRequest')
        ->name('employee-leave.cancel');
    Route::get('cuti-saya/{leaveRequest}/lampiran', [EmployeeLeaveController::class, 'downloadAttachment'])
        ->whereNumber('leaveRequest')
        ->name('employee-leave.attachment');
    Route::patch('cuti-saya/notifikasi/dibaca', [EmployeeLeaveController::class, 'readNotifications'])
        ->middleware('permission:leave.self')
        ->name('employee-leave.notifications.read');

    Route::get('ot-saya', [EmployeeOvertimeController::class, 'index'])
        ->middleware('permission:overtime.self')
        ->name('employee-overtime.index');
    Route::post('ot-saya', [EmployeeOvertimeController::class, 'store'])
        ->middleware('permission:overtime.apply')
        ->name('employee-overtime.store');
    Route::patch('ot-saya/{overtimeRequest}/batal', [EmployeeOvertimeController::class, 'cancel'])
        ->middleware('permission:overtime.apply')
        ->whereNumber('overtimeRequest')
        ->name('employee-overtime.cancel');
    Route::get('ot-saya/{overtimeRequest}/lampiran', [EmployeeOvertimeController::class, 'downloadAttachment'])
        ->whereNumber('overtimeRequest')
        ->name('employee-overtime.attachment');
    Route::patch('ot-saya/notifikasi/dibaca', [EmployeeOvertimeController::class, 'readNotifications'])
        ->middleware('permission:overtime.self')
        ->name('employee-overtime.notifications.read');

    Route::get('jadual-saya', [EmployeeRosterController::class, 'index'])
        ->middleware('permission:roster.self')
        ->name('employee-roster.index');
    Route::post('jadual-saya/pertukaran', [EmployeeRosterController::class, 'storeSwap'])
        ->middleware('permission:roster.swap')
        ->name('employee-roster.swaps.store');
    Route::patch('jadual-saya/pertukaran/{shiftSwapRequest}/batal', [EmployeeRosterController::class, 'cancelSwap'])
        ->middleware('permission:roster.swap')
        ->whereNumber('shiftSwapRequest')
        ->name('employee-roster.swaps.cancel');
    Route::patch('jadual-saya/notifikasi/dibaca', [EmployeeRosterController::class, 'readNotifications'])
        ->middleware('permission:roster.self')
        ->name('employee-roster.notifications.read');

    Route::get('prestasi-saya', [EmployeePerformanceController::class, 'index'])
        ->middleware('permission:performance.self')
        ->name('employee-performance.index');
    Route::put('prestasi-saya/{review}/draf', [EmployeePerformanceController::class, 'saveSelfAssessment'])
        ->middleware('permission:performance.self')
        ->whereNumber('review')
        ->name('employee-performance.save');
    Route::patch('prestasi-saya/{review}/hantar', [EmployeePerformanceController::class, 'submitSelfAssessment'])
        ->middleware('permission:performance.self')
        ->whereNumber('review')
        ->name('employee-performance.submit');
    Route::post('prestasi-saya/{review}/bukti', [EmployeePerformanceController::class, 'uploadEvidence'])
        ->middleware('permission:performance.self')
        ->whereNumber('review')
        ->name('employee-performance.evidence.store');
    Route::get('prestasi-saya/{review}/bukti/{evidence}', [EmployeePerformanceController::class, 'downloadEvidence'])
        ->middleware('permission:performance.self')
        ->whereNumber(['review', 'evidence'])
        ->name('employee-performance.evidence.download');
    Route::delete('prestasi-saya/{review}/bukti/{evidence}', [EmployeePerformanceController::class, 'deleteEvidence'])
        ->middleware('permission:performance.self')
        ->whereNumber(['review', 'evidence'])
        ->name('employee-performance.evidence.delete');
    Route::get('prestasi-saya/{review}/laporan.pdf', [EmployeePerformanceController::class, 'downloadPdf'])
        ->middleware('permission:performance.self')
        ->whereNumber('review')
        ->name('employee-performance.pdf');
    Route::patch('prestasi-saya/notifikasi/dibaca', [EmployeePerformanceController::class, 'readNotifications'])
        ->middleware('permission:performance.self')
        ->name('employee-performance.notifications.read');

    Route::get('onboarding-saya', [EmployeeOnboardingController::class, 'index'])
        ->middleware('permission:onboarding.self')
        ->name('employee-onboarding.index');
    Route::patch('onboarding-saya/tugasan/{task}', [EmployeeOnboardingController::class, 'updateTask'])
        ->middleware('permission:onboarding.self')
        ->whereNumber('task')
        ->name('employee-onboarding.tasks.update');

    Route::get('latihan-saya', [EmployeeTrainingController::class, 'index'])
        ->middleware('permission:training.self')
        ->name('employee-training.index');
    Route::post('latihan-saya', [EmployeeTrainingController::class, 'store'])
        ->middleware('permission:training.apply')
        ->name('employee-training.store');
    Route::patch('latihan-saya/{training}/batal', [EmployeeTrainingController::class, 'cancel'])
        ->middleware('permission:training.apply')
        ->whereNumber('training')
        ->name('employee-training.cancel');
    Route::post('latihan-saya/{training}/sijil', [EmployeeTrainingController::class, 'uploadCertificate'])
        ->middleware('permission:training.self')
        ->whereNumber('training')
        ->name('employee-training.certificate');
    Route::put('latihan-saya/{training}/penilaian', [EmployeeTrainingController::class, 'evaluate'])
        ->middleware('permission:training.self')
        ->whereNumber('training')
        ->name('employee-training.evaluate');
    Route::get('latihan-saya/{training}/lampiran/{attachment}', [EmployeeTrainingController::class, 'downloadAttachment'])
        ->middleware('permission:training.self')
        ->whereNumber(['training', 'attachment'])
        ->name('employee-training.attachment');
    Route::patch('latihan-saya/notifikasi/dibaca', [EmployeeTrainingController::class, 'readNotifications'])
        ->middleware('permission:training.self')
        ->name('employee-training.notifications.read');

    Route::get('dokumen-saya', [EmployeeDocumentController::class, 'index'])
        ->middleware('permission:documents.self')
        ->name('employee-documents.index');
    Route::patch('dokumen-saya/{document}/perakuan', [EmployeeDocumentController::class, 'acknowledge'])
        ->middleware('permission:documents.self')
        ->whereNumber('document')
        ->name('employee-documents.acknowledge');
    Route::get('dokumen-saya/{document}/pdf', [EmployeeDocumentController::class, 'downloadPdf'])
        ->middleware('permission:documents.self')
        ->whereNumber('document')
        ->name('employee-documents.pdf');
    Route::get('dokumen-saya/{document}/lampiran/{attachment}', [EmployeeDocumentController::class, 'downloadAttachment'])
        ->middleware('permission:documents.self')
        ->whereNumber(['document', 'attachment'])
        ->name('employee-documents.attachment');
    Route::patch('dokumen-saya/notifikasi/dibaca', [EmployeeDocumentController::class, 'readNotifications'])
        ->middleware('permission:documents.self')
        ->name('employee-documents.notifications.read');

    Route::get('aduan-saya', [EmployeeDisciplineController::class, 'index'])
        ->middleware('permission:discipline.self')
        ->name('employee-discipline.index');
    Route::post('aduan-saya', [EmployeeDisciplineController::class, 'store'])
        ->middleware('permission:discipline.apply')
        ->name('employee-discipline.store');
    Route::patch('aduan-saya/{case}/tarik-balik', [EmployeeDisciplineController::class, 'withdraw'])
        ->middleware('permission:discipline.apply')
        ->whereNumber('case')
        ->name('employee-discipline.withdraw');
    Route::post('aduan-saya/{case}/jawapan-tunjuk-sebab', [EmployeeDisciplineController::class, 'submitResponse'])
        ->middleware('permission:discipline.self')
        ->whereNumber('case')
        ->name('employee-discipline.response');
    Route::post('aduan-saya/{case}/rayuan', [EmployeeDisciplineController::class, 'appeal'])
        ->middleware('permission:discipline.self')
        ->whereNumber('case')
        ->name('employee-discipline.appeal');
    Route::get('aduan-saya/{case}/lampiran/{attachment}', [EmployeeDisciplineController::class, 'downloadAttachment'])
        ->middleware('permission:discipline.self')
        ->whereNumber(['case', 'attachment'])
        ->name('employee-discipline.attachment');
    Route::patch('aduan-saya/notifikasi/dibaca', [EmployeeDisciplineController::class, 'readNotifications'])
        ->middleware('permission:discipline.self')
        ->name('employee-discipline.notifications.read');

    Route::get('pengakhiran-saya', [EmployeeSeparationController::class, 'index'])
        ->middleware('permission:separation.self')
        ->name('employee-separation.index');
    Route::post('pengakhiran-saya', [EmployeeSeparationController::class, 'store'])
        ->middleware('permission:separation.apply')
        ->name('employee-separation.store');
    Route::patch('pengakhiran-saya/{case}/batal', [EmployeeSeparationController::class, 'cancel'])
        ->middleware('permission:separation.apply')
        ->whereNumber('case')
        ->name('employee-separation.cancel');
    Route::patch('pengakhiran-saya/{case}/tugasan/{task}/hantar', [EmployeeSeparationController::class, 'submitTask'])
        ->middleware('permission:separation.self')
        ->whereNumber(['case', 'task'])
        ->name('employee-separation.tasks.submit');
    Route::post('pengakhiran-saya/{case}/tugasan/{task}/lampiran', [EmployeeSeparationController::class, 'uploadAttachment'])
        ->middleware('permission:separation.self')
        ->whereNumber(['case', 'task'])
        ->name('employee-separation.tasks.attachments.store');
    Route::post('pengakhiran-saya/{case}/serahan-tugas', [EmployeeSeparationController::class, 'storeHandover'])
        ->middleware('permission:separation.self')
        ->whereNumber('case')
        ->name('employee-separation.handovers.store');
    Route::patch('pengakhiran-saya/{case}/serahan-tugas/{handover}/hantar', [EmployeeSeparationController::class, 'submitHandover'])
        ->middleware('permission:separation.self')
        ->whereNumber(['case', 'handover'])
        ->name('employee-separation.handovers.submit');
    Route::put('pengakhiran-saya/{case}/exit-interview', [EmployeeSeparationController::class, 'submitInterview'])
        ->middleware('permission:separation.self')
        ->whereNumber('case')
        ->name('employee-separation.interview.submit');
    Route::get('pengakhiran-saya/{case}/lampiran/{attachment}', [EmployeeSeparationController::class, 'downloadAttachment'])
        ->middleware('permission:separation.self')
        ->whereNumber(['case', 'attachment'])
        ->name('employee-separation.attachments.download');
    Route::patch('pengakhiran-saya/notifikasi/dibaca', [EmployeeSeparationController::class, 'readNotifications'])
        ->middleware('permission:separation.self')
        ->name('employee-separation.notifications.read');

    Route::get('tuntutan-saya', [EmployeeClaimController::class, 'index'])
        ->middleware('permission:claims.self')
        ->name('employee-claims.index');
    Route::post('tuntutan-saya', [EmployeeClaimController::class, 'store'])
        ->middleware('permission:claims.apply')
        ->name('employee-claims.store');
    Route::patch('tuntutan-saya/{claimRequest}/batal', [EmployeeClaimController::class, 'cancel'])
        ->middleware('permission:claims.apply')
        ->whereNumber('claimRequest')
        ->name('employee-claims.cancel');
    Route::get('tuntutan-saya/{claimRequest}/resit/{attachment}', [EmployeeClaimController::class, 'downloadAttachment'])
        ->middleware('permission:claims.self')
        ->whereNumber(['claimRequest', 'attachment'])
        ->name('employee-claims.attachment');
    Route::patch('tuntutan-saya/notifikasi/dibaca', [EmployeeClaimController::class, 'readNotifications'])
        ->middleware('permission:claims.self')
        ->name('employee-claims.notifications.read');

    Route::get('cuti', [MaklumatCutiController::class, 'index'])
        ->middleware('permission:leave.view')
        ->name('cuti.index');
    Route::get('permohonan-cuti', [LeaveRequestController::class, 'index'])
        ->name('leave-requests.index');
    Route::get('permohonan-cuti/laporan.csv', [LeaveRequestController::class, 'reportCsv'])
        ->name('leave-requests.report');
    Route::patch('permohonan-cuti/{leaveRequest}/semakan-penyelia', [LeaveRequestController::class, 'supervisorReview'])
        ->middleware('permission:leave.supervise')
        ->whereNumber('leaveRequest')
        ->name('leave-requests.supervisor-review');
    Route::patch('permohonan-cuti/{leaveRequest}/semakan', [LeaveRequestController::class, 'review'])
        ->middleware('permission:leave.approve')
        ->whereNumber('leaveRequest')
        ->name('leave-requests.review');
    Route::patch('permohonan-cuti/{leaveRequest}/batal-kelulusan', [LeaveRequestController::class, 'cancelApproved'])
        ->middleware('permission:leave.approve')
        ->whereNumber('leaveRequest')
        ->name('leave-requests.cancel-approved');
    Route::get('kerja-lebih-masa', [MaklumatOtController::class, 'index'])
        ->middleware('permission:overtime.view')
        ->name('kerja-lebih-masa.index');
    Route::get('permohonan-ot', [OvertimeRequestController::class, 'index'])
        ->name('overtime-requests.index');
    Route::get('permohonan-ot/laporan.csv', [OvertimeRequestController::class, 'reportCsv'])
        ->name('overtime-requests.report');
    Route::patch('permohonan-ot/{overtimeRequest}/semakan-penyelia', [OvertimeRequestController::class, 'supervisorReview'])
        ->middleware('permission:overtime.supervise')
        ->whereNumber('overtimeRequest')
        ->name('overtime-requests.supervisor-review');
    Route::patch('permohonan-ot/{overtimeRequest}/semakan', [OvertimeRequestController::class, 'review'])
        ->middleware('permission:overtime.approve')
        ->whereNumber('overtimeRequest')
        ->name('overtime-requests.review');
    Route::patch('permohonan-ot/{overtimeRequest}/batal-kelulusan', [OvertimeRequestController::class, 'cancelApproved'])
        ->middleware('permission:overtime.approve')
        ->whereNumber('overtimeRequest')
        ->name('overtime-requests.cancel-approved');
    Route::get('jadual-roster', [RosterController::class, 'index'])
        ->middleware('permission:roster.view')
        ->name('rosters.index');
    Route::get('jadual-roster/laporan.csv', [RosterController::class, 'export'])
        ->middleware('permission:roster.view')
        ->name('rosters.export');
    Route::post('jadual-roster/jana', [RosterController::class, 'generate'])
        ->middleware('permission:roster.manage')
        ->name('rosters.generate');
    Route::put('jadual-roster/rekod/{entry}', [RosterController::class, 'updateEntry'])
        ->middleware('permission:roster.manage')
        ->whereNumber('entry')
        ->name('rosters.entries.update');
    Route::patch('jadual-roster/{period}/terbit', [RosterController::class, 'publish'])
        ->middleware('permission:roster.publish')
        ->whereNumber('period')
        ->name('rosters.publish');
    Route::patch('jadual-roster/{period}/kunci', [RosterController::class, 'lock'])
        ->middleware('permission:roster.publish')
        ->whereNumber('period')
        ->name('rosters.lock');
    Route::patch('jadual-roster/pertukaran/{shiftSwapRequest}/semakan', [RosterController::class, 'reviewSwap'])
        ->middleware('permission:roster.supervise')
        ->whereNumber('shiftSwapRequest')
        ->name('rosters.swaps.review');
    Route::get('permohonan-tuntutan', [ClaimRequestController::class, 'index'])
        ->name('claim-requests.index');
    Route::get('permohonan-tuntutan/laporan.csv', [ClaimRequestController::class, 'reportCsv'])
        ->name('claim-requests.report');
    Route::patch('permohonan-tuntutan/{claimRequest}/semakan-penyelia', [ClaimRequestController::class, 'supervisorReview'])
        ->middleware('permission:claims.supervise')
        ->whereNumber('claimRequest')
        ->name('claim-requests.supervisor-review');
    Route::patch('permohonan-tuntutan/{claimRequest}/semakan', [ClaimRequestController::class, 'review'])
        ->middleware('permission:claims.approve')
        ->whereNumber('claimRequest')
        ->name('claim-requests.review');
    Route::patch('permohonan-tuntutan/{claimRequest}/jadual-payroll', [ClaimRequestController::class, 'schedulePayroll'])
        ->middleware('permission:claims.manage')
        ->whereNumber('claimRequest')
        ->name('claim-requests.schedule-payroll');
    Route::patch('permohonan-tuntutan/{claimRequest}/batal-kelulusan', [ClaimRequestController::class, 'cancelApproved'])
        ->middleware('permission:claims.approve')
        ->whereNumber('claimRequest')
        ->name('claim-requests.cancel-approved');
    Route::get('permohonan-tuntutan/{claimRequest}/resit/{attachment}', [ClaimRequestController::class, 'downloadAttachment'])
        ->whereNumber(['claimRequest', 'attachment'])
        ->name('claim-requests.attachment');

    Route::get('prestasi', [PerformanceReviewController::class, 'index'])
        ->middleware('permission:performance.view')
        ->name('performance.index');
    Route::get('prestasi/laporan.csv', [PerformanceReviewController::class, 'export'])
        ->middleware('permission:performance.view')
        ->name('performance.export');
    Route::post('prestasi', [PerformanceReviewController::class, 'store'])
        ->middleware('permission:performance.manage')
        ->name('performance.store');
    Route::post('prestasi/kitaran/{cycle}/jana', [PerformanceReviewController::class, 'generateCycle'])
        ->middleware('permission:performance.manage')
        ->whereNumber('cycle')
        ->name('performance.generate');
    Route::put('prestasi/{review}/penyelia', [PerformanceReviewController::class, 'supervisorReview'])
        ->middleware('permission:performance.supervise')
        ->whereNumber('review')
        ->name('performance.supervisor-review');
    Route::put('prestasi/{review}/moderasi', [PerformanceReviewController::class, 'moderate'])
        ->middleware('permission:performance.moderate')
        ->whereNumber('review')
        ->name('performance.moderate');
    Route::patch('prestasi/{review}/muktamad', [PerformanceReviewController::class, 'finalize'])
        ->middleware('permission:performance.finalize')
        ->whereNumber('review')
        ->name('performance.finalize');
    Route::put('prestasi/{review}/pip', [PerformanceReviewController::class, 'savePip'])
        ->middleware('permission:performance.manage')
        ->whereNumber('review')
        ->name('performance.pip.save');
    Route::post('prestasi/pip/{plan}/semakan', [PerformanceReviewController::class, 'storePipCheckin'])
        ->middleware('permission:performance.manage')
        ->whereNumber('plan')
        ->name('performance.pip.checkin');
    Route::get('prestasi/{review}/laporan.pdf', [PerformanceReviewController::class, 'pdf'])
        ->middleware('permission:performance.view')
        ->whereNumber('review')
        ->name('performance.pdf');
    Route::get('prestasi/{review}/bukti/{evidence}', [PerformanceReviewController::class, 'downloadEvidence'])
        ->middleware('permission:performance.view')
        ->whereNumber(['review', 'evidence'])
        ->name('performance.evidence.download');

    Route::get('pengambilan', [RecruitmentController::class, 'index'])
        ->middleware('permission:recruitment.view')
        ->name('recruitment.index');
    Route::get('pengambilan/laporan.csv', [RecruitmentController::class, 'export'])
        ->middleware('permission:recruitment.view')
        ->name('recruitment.export');
    Route::get('pengambilan/calon/{candidate}', [RecruitmentController::class, 'show'])
        ->middleware('permission:recruitment.view')
        ->whereNumber('candidate')
        ->name('recruitment.show');
    Route::patch('pengambilan/notifikasi/dibaca', [RecruitmentController::class, 'readNotifications'])
        ->middleware('permission:recruitment.view')
        ->name('recruitment.notifications.read');
    Route::middleware('permission:recruitment.manage')->group(function () {
        Route::post('pengambilan/kekosongan', [RecruitmentController::class, 'storeRequisition'])
            ->name('recruitment.requisitions.store');
        Route::put('pengambilan/kekosongan/{requisition}', [RecruitmentController::class, 'updateRequisition'])
            ->whereNumber('requisition')
            ->name('recruitment.requisitions.update');
        Route::post('pengambilan/calon', [RecruitmentController::class, 'storeCandidate'])
            ->name('recruitment.candidates.store');
        Route::put('pengambilan/calon/{candidate}', [RecruitmentController::class, 'updateCandidate'])
            ->whereNumber('candidate')
            ->name('recruitment.candidates.update');
        Route::patch('pengambilan/calon/{candidate}/peringkat', [RecruitmentController::class, 'updateCandidateStage'])
            ->whereNumber('candidate')
            ->name('recruitment.candidates.stage');
        Route::post('pengambilan/calon/{candidate}/dokumen', [RecruitmentController::class, 'uploadDocument'])
            ->whereNumber('candidate')
            ->name('recruitment.documents.store');
        Route::delete('pengambilan/calon/{candidate}/dokumen/{document}', [RecruitmentController::class, 'deleteDocument'])
            ->whereNumber(['candidate', 'document'])
            ->name('recruitment.documents.destroy');
        Route::post('pengambilan/calon/{candidate}/temu-duga', [RecruitmentController::class, 'scheduleInterview'])
            ->whereNumber('candidate')
            ->name('recruitment.interviews.store');
        Route::patch('pengambilan/calon/{candidate}/temu-duga/{interview}/status', [RecruitmentController::class, 'cancelInterview'])
            ->whereNumber(['candidate', 'interview'])
            ->name('recruitment.interviews.status');
        Route::post('pengambilan/calon/{candidate}/tawaran', [RecruitmentController::class, 'storeOffer'])
            ->whereNumber('candidate')
            ->name('recruitment.offers.store');
    });
    Route::patch('pengambilan/kekosongan/{requisition}/status', [RecruitmentController::class, 'changeRequisitionStatus'])
        ->middleware('permission:recruitment.view')
        ->whereNumber('requisition')
        ->name('recruitment.requisitions.status');
    Route::patch('pengambilan/calon/{candidate}/tawaran/{offer}/status', [RecruitmentController::class, 'changeOfferStatus'])
        ->middleware('permission:recruitment.view')
        ->whereNumber(['candidate', 'offer'])
        ->name('recruitment.offers.status');
    Route::get('pengambilan/calon/{candidate}/dokumen/{document}', [RecruitmentController::class, 'downloadDocument'])
        ->middleware('permission:recruitment.view')
        ->whereNumber(['candidate', 'document'])
        ->name('recruitment.documents.download');
    Route::put('pengambilan/calon/{candidate}/temu-duga/{interview}/scorecard', [RecruitmentController::class, 'submitScorecard'])
        ->middleware('permission:recruitment.interview')
        ->whereNumber(['candidate', 'interview'])
        ->name('recruitment.interviews.scorecard');

    Route::middleware('permission:onboarding.view')->group(function () {
        Route::get('onboarding', [OnboardingController::class, 'index'])
            ->name('onboarding.index');
        Route::get('onboarding/laporan.csv', [OnboardingController::class, 'export'])
            ->name('onboarding.export');
    });
    Route::middleware('permission:onboarding.manage')->group(function () {
        Route::put('onboarding/{onboardingCase}', [OnboardingController::class, 'updateCase'])
            ->whereNumber('onboardingCase')
            ->name('onboarding.update');
        Route::put('onboarding/{onboardingCase}/paut-pekerja', [OnboardingController::class, 'linkEmployee'])
            ->whereNumber('onboardingCase')
            ->name('onboarding.link-employee');
        Route::delete('onboarding/{onboardingCase}/paut-pekerja', [OnboardingController::class, 'unlinkEmployee'])
            ->whereNumber('onboardingCase')
            ->name('onboarding.unlink-employee');
        Route::put('onboarding/{onboardingCase}/tugasan/{task}', [OnboardingController::class, 'updateTask'])
            ->whereNumber(['onboardingCase', 'task'])
            ->name('onboarding.tasks.update');
    });
    Route::post('onboarding/{onboardingCase}/daftar-pekerja', [OnboardingController::class, 'registerEmployee'])
        ->middleware('permission:onboarding.approve')
        ->whereNumber('onboardingCase')
        ->name('onboarding.register-employee');
    Route::patch('onboarding/{onboardingCase}/status', [OnboardingController::class, 'changeCaseStatus'])
        ->middleware('permission:onboarding.view')
        ->whereNumber('onboardingCase')
        ->name('onboarding.status');

    Route::get('latihan-kompetensi', [TrainingController::class, 'index'])
        ->middleware('permission:training.view')
        ->name('training.index');
    Route::get('latihan-kompetensi/laporan.csv', [TrainingController::class, 'export'])
        ->middleware('permission:training.view')
        ->name('training.export');
    Route::patch('latihan-kompetensi/{training}/semakan-penyelia', [TrainingController::class, 'supervisorReview'])
        ->middleware('permission:training.supervise')
        ->whereNumber('training')
        ->name('training.supervisor-review');
    Route::middleware('permission:training.manage')->group(function () {
        Route::post('latihan-kompetensi/pencalonan', [TrainingController::class, 'nominate'])
            ->name('training.nominate');
        Route::put('latihan-kompetensi/{training}/penyelesaian', [TrainingController::class, 'recordCompletion'])
            ->whereNumber('training')
            ->name('training.completion');
        Route::post('latihan-kompetensi/pelan-pembangunan', [TrainingController::class, 'storeDevelopmentPlan'])
            ->name('training.development-plans.store');
        Route::patch('latihan-kompetensi/pelan-pembangunan/{plan}', [TrainingController::class, 'updateDevelopmentPlan'])
            ->whereNumber('plan')
            ->name('training.development-plans.update');
    });
    Route::patch('latihan-kompetensi/{training}/semakan', [TrainingController::class, 'review'])
        ->middleware('permission:training.approve')
        ->whereNumber('training')
        ->name('training.review');
    Route::post('latihan-kompetensi/penilaian-kompetensi', [TrainingController::class, 'saveCompetency'])
        ->middleware('permission:competency.assess')
        ->name('training.competencies.save');
    Route::get('latihan-kompetensi/{training}/lampiran/{attachment}', [TrainingController::class, 'downloadAttachment'])
        ->middleware('permission:training.view')
        ->whereNumber(['training', 'attachment'])
        ->name('training.attachment');

    Route::get('dokumen-hr', [HrDocumentController::class, 'index'])
        ->middleware('permission:documents.view')
        ->name('hr-documents.index');
    Route::get('dokumen-hr/laporan.csv', [HrDocumentController::class, 'export'])
        ->middleware('permission:documents.view')
        ->name('hr-documents.export');
    Route::patch('dokumen-hr/notifikasi/dibaca', [HrDocumentController::class, 'readNotifications'])
        ->middleware('permission:documents.view')
        ->name('hr-documents.notifications.read');
    Route::get('dokumen-hr/{document}/pdf', [HrDocumentController::class, 'downloadPdf'])
        ->middleware('permission:documents.view')
        ->whereNumber('document')
        ->name('hr-documents.pdf');
    Route::get('dokumen-hr/{document}/lampiran/{attachment}', [HrDocumentController::class, 'downloadAttachment'])
        ->middleware('permission:documents.view')
        ->whereNumber(['document', 'attachment'])
        ->name('hr-documents.attachment');
    Route::patch('dokumen-hr/{document}/semakan', [HrDocumentController::class, 'review'])
        ->middleware('permission:documents.approve')
        ->whereNumber('document')
        ->name('hr-documents.review');
    Route::middleware('permission:documents.manage')->group(function () {
        Route::post('dokumen-hr', [HrDocumentController::class, 'store'])
            ->name('hr-documents.store');
        Route::put('dokumen-hr/{document}', [HrDocumentController::class, 'update'])
            ->whereNumber('document')
            ->name('hr-documents.update');
        Route::patch('dokumen-hr/{document}/hantar', [HrDocumentController::class, 'submit'])
            ->whereNumber('document')
            ->name('hr-documents.submit');
        Route::patch('dokumen-hr/{document}/keluar', [HrDocumentController::class, 'issue'])
            ->whereNumber('document')
            ->name('hr-documents.issue');
        Route::patch('dokumen-hr/{document}/batal', [HrDocumentController::class, 'void'])
            ->whereNumber('document')
            ->name('hr-documents.void');
        Route::post('dokumen-hr/{document}/pembaharuan', [HrDocumentController::class, 'renew'])
            ->whereNumber('document')
            ->name('hr-documents.renew');
        Route::post('dokumen-hr/{document}/lampiran', [HrDocumentController::class, 'uploadAttachment'])
            ->whereNumber('document')
            ->name('hr-documents.attachments.store');
        Route::delete('dokumen-hr/{document}/lampiran/{attachment}', [HrDocumentController::class, 'deleteAttachment'])
            ->whereNumber(['document', 'attachment'])
            ->name('hr-documents.attachments.destroy');
    });

    Route::get('disiplin-aduan', [DisciplineController::class, 'index'])
        ->middleware('permission:discipline.view')
        ->name('discipline.index');
    Route::get('disiplin-aduan/laporan.csv', [DisciplineController::class, 'export'])
        ->middleware('permission:discipline.view')
        ->name('discipline.export');
    Route::patch('disiplin-aduan/notifikasi/dibaca', [DisciplineController::class, 'readNotifications'])
        ->middleware('permission:discipline.view')
        ->name('discipline.notifications.read');
    Route::patch('disiplin-aduan/{case}/triage', [DisciplineController::class, 'triage'])
        ->middleware('permission:discipline.manage')
        ->whereNumber('case')
        ->name('discipline.triage');
    Route::post('disiplin-aduan/{case}/pasukan', [DisciplineController::class, 'addMember'])
        ->middleware('permission:discipline.manage')
        ->whereNumber('case')
        ->name('discipline.members.store');
    Route::patch('disiplin-aduan/{case}/pasukan/{member}/konflik', [DisciplineController::class, 'declareConflict'])
        ->middleware('permission:discipline.investigate')
        ->whereNumber(['case', 'member'])
        ->name('discipline.members.conflict');
    Route::patch('disiplin-aduan/{case}/pasukan/{member}/gugur', [DisciplineController::class, 'recuseMember'])
        ->middleware('permission:discipline.manage')
        ->whereNumber(['case', 'member'])
        ->name('discipline.members.recuse');
    Route::post('disiplin-aduan/{case}/kronologi', [DisciplineController::class, 'addEvent'])
        ->middleware('permission:discipline.investigate')
        ->whereNumber('case')
        ->name('discipline.events.store');
    Route::post('disiplin-aduan/{case}/lampiran', [DisciplineController::class, 'uploadAttachment'])
        ->middleware('permission:discipline.investigate')
        ->whereNumber('case')
        ->name('discipline.attachments.store');
    Route::delete('disiplin-aduan/{case}/lampiran/{attachment}', [DisciplineController::class, 'deleteAttachment'])
        ->middleware('permission:discipline.investigate')
        ->whereNumber(['case', 'attachment'])
        ->name('discipline.attachments.destroy');
    Route::get('disiplin-aduan/{case}/lampiran/{attachment}', [DisciplineController::class, 'downloadAttachment'])
        ->middleware('permission:discipline.view')
        ->whereNumber(['case', 'attachment'])
        ->name('discipline.attachments.download');
    Route::patch('disiplin-aduan/{case}/dapatan', [DisciplineController::class, 'submitFinding'])
        ->middleware('permission:discipline.investigate')
        ->whereNumber('case')
        ->name('discipline.findings.submit');
    Route::patch('disiplin-aduan/{case}/tunjuk-sebab', [DisciplineController::class, 'issueShowCause'])
        ->middleware('permission:discipline.manage')
        ->whereNumber('case')
        ->name('discipline.show-cause.issue');
    Route::patch('disiplin-aduan/{case}/keputusan', [DisciplineController::class, 'decide'])
        ->middleware('permission:discipline.approve')
        ->whereNumber('case')
        ->name('discipline.decision');
    Route::patch('disiplin-aduan/{case}/tunjuk-sebab/tanpa-jawapan', [DisciplineController::class, 'proceedWithoutResponse'])
        ->middleware('permission:discipline.manage')
        ->whereNumber('case')
        ->name('discipline.show-cause.no-response');
    Route::patch('disiplin-aduan/{case}/rayuan/{appeal}', [DisciplineController::class, 'reviewAppeal'])
        ->middleware('permission:discipline.approve')
        ->whereNumber(['case', 'appeal'])
        ->name('discipline.appeals.review');
    Route::patch('disiplin-aduan/{case}/tutup', [DisciplineController::class, 'close'])
        ->middleware('permission:discipline.manage')
        ->whereNumber('case')
        ->name('discipline.close');

    Route::get('berhenti-clearance', [SeparationController::class, 'index'])
        ->middleware('permission:separation.view')
        ->name('separations.index');
    Route::get('berhenti-clearance/laporan.csv', [SeparationController::class, 'export'])
        ->middleware('permission:separation.view')
        ->name('separations.export');
    Route::patch('berhenti-clearance/notifikasi/dibaca', [SeparationController::class, 'readNotifications'])
        ->middleware('permission:separation.view')
        ->name('separations.notifications.read');
    Route::post('berhenti-clearance', [SeparationController::class, 'store'])
        ->middleware('permission:separation.manage')
        ->name('separations.store');
    Route::patch('berhenti-clearance/{case}/hantar', [SeparationController::class, 'submit'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.submit');
    Route::patch('berhenti-clearance/{case}/semakan-penyelia', [SeparationController::class, 'supervisorReview'])
        ->middleware('permission:separation.supervise')
        ->whereNumber('case')
        ->name('separations.supervisor-review');
    Route::patch('berhenti-clearance/{case}/kelulusan-hr', [SeparationController::class, 'hrReview'])
        ->middleware('permission:separation.approve')
        ->whereNumber('case')
        ->name('separations.hr-review');
    Route::patch('berhenti-clearance/{case}/batal', [SeparationController::class, 'cancel'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.cancel');
    Route::patch('berhenti-clearance/{case}/tugasan/{task}', [SeparationController::class, 'taskAction'])
        ->middleware('permission:separation.clearance')
        ->whereNumber(['case', 'task'])
        ->name('separations.tasks.action');
    Route::post('berhenti-clearance/{case}/tugasan/{task}/lampiran', [SeparationController::class, 'uploadAttachment'])
        ->middleware('permission:separation.clearance')
        ->whereNumber(['case', 'task'])
        ->name('separations.tasks.attachments.store');
    Route::post('berhenti-clearance/{case}/lampiran', [SeparationController::class, 'uploadAttachment'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.attachments.store');
    Route::get('berhenti-clearance/{case}/lampiran/{attachment}', [SeparationController::class, 'downloadAttachment'])
        ->middleware('permission:separation.view')
        ->whereNumber(['case', 'attachment'])
        ->name('separations.attachments.download');
    Route::post('berhenti-clearance/{case}/aset', [SeparationController::class, 'storeAsset'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.assets.store');
    Route::patch('berhenti-clearance/{case}/aset/{asset}', [SeparationController::class, 'updateAsset'])
        ->middleware('permission:separation.manage')
        ->whereNumber(['case', 'asset'])
        ->name('separations.assets.update');
    Route::patch('berhenti-clearance/{case}/serahan-tugas/{handover}', [SeparationController::class, 'reviewHandover'])
        ->middleware('permission:separation.clearance')
        ->whereNumber(['case', 'handover'])
        ->name('separations.handovers.review');
    Route::put('berhenti-clearance/{case}/exit-interview', [SeparationController::class, 'updateInterview'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.interview.update');
    Route::put('berhenti-clearance/{case}/final-settlement', [SeparationController::class, 'updateSettlement'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.settlement.update');
    Route::patch('berhenti-clearance/{case}/final-settlement/sahkan', [SeparationController::class, 'verifySettlement'])
        ->middleware('permission:separation.approve')
        ->whereNumber('case')
        ->name('separations.settlement.verify');
    Route::post('berhenti-clearance/{case}/dokumen', [SeparationController::class, 'generateDocument'])
        ->middleware('permission:separation.manage')
        ->whereNumber('case')
        ->name('separations.documents.store');
    Route::patch('berhenti-clearance/{case}/selesai', [SeparationController::class, 'complete'])
        ->middleware('permission:separation.approve')
        ->whereNumber('case')
        ->name('separations.complete');
    Route::get('payroll-asal', [MaklumatPayrollController::class, 'index'])
        ->middleware('permission:payroll.view')
        ->name('payroll.legacy');
    Route::get('slip-gaji-saya', [EmployeePayslipController::class, 'index'])
        ->middleware('permission:payslip.self')
        ->name('payslips.index');
    Route::get('slip-gaji-saya/{payrollEntry}/pdf', [EmployeePayslipController::class, 'downloadOwn'])
        ->middleware('permission:payslip.self')
        ->whereNumber('payrollEntry')
        ->name('payslips.download');
    Route::get('payroll', [PayrollRunController::class, 'index'])
        ->middleware('permission:payroll.view')
        ->name('payroll.index');
    Route::post('payroll', [PayrollRunController::class, 'store'])
        ->middleware('permission:payroll.manage')
        ->name('payroll.store');
    Route::get('payroll/{payrollRun}', [PayrollRunController::class, 'show'])
        ->middleware('permission:payroll.view')
        ->whereNumber('payrollRun')
        ->name('payroll.show');
    Route::get('payroll/{payrollRun}/laporan.csv', [PayrollRunController::class, 'reportCsv'])
        ->middleware('permission:payroll.view')
        ->whereNumber('payrollRun')
        ->name('payroll.report');
    Route::post('payroll/{payrollRun}/kira-semula', [PayrollRunController::class, 'recalculate'])
        ->middleware('permission:payroll.manage')
        ->whereNumber('payrollRun')
        ->name('payroll.recalculate');
    Route::post('payroll/{payrollRun}/pekerja/{entry}/pelarasan', [PayrollRunController::class, 'storeManualItem'])
        ->middleware('permission:payroll.manage')
        ->whereNumber(['payrollRun', 'entry'])
        ->name('payroll.items.store');
    Route::delete('payroll/{payrollRun}/pekerja/{entry}/pelarasan/{item}', [PayrollRunController::class, 'destroyManualItem'])
        ->middleware('permission:payroll.manage')
        ->whereNumber(['payrollRun', 'entry', 'item'])
        ->name('payroll.items.destroy');
    Route::put('payroll/{payrollRun}/pekerja/{entry}/statutori', [PayrollRunController::class, 'updateStatutory'])
        ->middleware('permission:payroll.manage')
        ->whereNumber(['payrollRun', 'entry'])
        ->name('payroll.statutory.update');
    Route::get('payroll/pekerja/{payrollEntry}/slip-gaji.pdf', [EmployeePayslipController::class, 'downloadForHr'])
        ->middleware('permission:payroll.view')
        ->whereNumber('payrollEntry')
        ->name('payroll.payslip');
    Route::patch('payroll/{payrollRun}/semakan-hr', [PayrollRunController::class, 'review'])
        ->middleware('permission:payroll.manage')
        ->whereNumber('payrollRun')
        ->name('payroll.review');
    Route::patch('payroll/{payrollRun}/lulus', [PayrollRunController::class, 'approve'])
        ->middleware('permission:payroll.approve')
        ->whereNumber('payrollRun')
        ->name('payroll.approve');
    Route::patch('payroll/{payrollRun}/muktamad', [PayrollRunController::class, 'finalize'])
        ->middleware('permission:payroll.approve')
        ->whereNumber('payrollRun')
        ->name('payroll.finalize');
    Route::patch('payroll/{payrollRun}/kembali-draf', [PayrollRunController::class, 'returnToDraft'])
        ->middleware('permission:payroll.approve')
        ->whereNumber('payrollRun')
        ->name('payroll.return-draft');
    Route::get('laporan-bulanan', [ReportBulananController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('laporan-bulanan.index');
    Route::get('laporan-bulanan/laporan.csv', [ReportBulananController::class, 'export'])
        ->middleware('permission:reports.view')
        ->name('laporan-bulanan.export');
    Route::get('laporan-bulanan-asal', [ReportBulananController::class, 'legacy'])
        ->middleware('permission:reports.view')
        ->name('laporan-bulanan.legacy');

    Route::get('audit-trail', [AuditTrailController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');
    Route::patch('audit-trail/pekerja-tidak-aktif/{id}/aktifkan', [AuditTrailController::class, 'restore'])
        ->middleware(['permission:audit.view', 'permission:employees.manage'])
        ->whereNumber('id')
        ->name('audit.employees.restore');

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('pengguna', [UserManagementController::class, 'index'])
            ->name('users.index');
        Route::get('pengguna/create', [UserManagementController::class, 'create'])
            ->name('users.create');
        Route::post('pengguna', [UserManagementController::class, 'store'])
            ->name('users.store');
        Route::get('pengguna/import-pekerja', [EmployeeUserImportController::class, 'create'])
            ->name('users.import.create');
        Route::post('pengguna/import-pekerja', [EmployeeUserImportController::class, 'store'])
            ->name('users.import.store');
        Route::get('pengguna/reset-kata-laluan', [UserPasswordBulkResetController::class, 'create'])
            ->name('users.password-reset.create');
        Route::post('pengguna/reset-kata-laluan', [UserPasswordBulkResetController::class, 'store'])
            ->name('users.password-reset.store');
        Route::get('pengguna/{user}/edit', [UserManagementController::class, 'edit'])
            ->whereNumber('user')
            ->name('users.edit');
        Route::put('pengguna/{user}', [UserManagementController::class, 'update'])
            ->whereNumber('user')
            ->name('users.update');
        Route::patch('pengguna/{user}/role', [UserManagementController::class, 'updateRole'])
            ->whereNumber('user')
            ->name('users.role.update');
    });

    Route::middleware('permission:attendance.settings')->group(function () {
        Route::get('tetapan-kehadiran', [AttendanceSettingsController::class, 'index'])
            ->name('attendance-settings.index');
        Route::post('tetapan-kehadiran/lokasi', [AttendanceSettingsController::class, 'storeOffice'])
            ->name('attendance-settings.offices.store');
        Route::put('tetapan-kehadiran/lokasi/{office}', [AttendanceSettingsController::class, 'updateOffice'])
            ->name('attendance-settings.offices.update');
        Route::patch('tetapan-kehadiran/lokasi/{office}/status', [AttendanceSettingsController::class, 'toggleOffice'])
            ->name('attendance-settings.offices.toggle');
        Route::post('tetapan-kehadiran/pautan', [AttendanceSettingsController::class, 'storeLink'])
            ->name('attendance-settings.links.store');
        Route::patch('tetapan-kehadiran/pautan/{link}/nyahaktif', [AttendanceSettingsController::class, 'deactivateLink'])
            ->name('attendance-settings.links.deactivate');
    });

    Route::middleware('permission:roster.settings')->group(function () {
        Route::get('tetapan-syif', [ScheduleSettingsController::class, 'index'])
            ->name('schedule-settings.index');
        Route::post('tetapan-syif/template', [ScheduleSettingsController::class, 'storeTemplate'])
            ->name('schedule-settings.templates.store');
        Route::put('tetapan-syif/template/{shiftTemplate}', [ScheduleSettingsController::class, 'updateTemplate'])
            ->whereNumber('shiftTemplate')
            ->name('schedule-settings.templates.update');
        Route::patch('tetapan-syif/template/{shiftTemplate}/status', [ScheduleSettingsController::class, 'toggleTemplate'])
            ->whereNumber('shiftTemplate')
            ->name('schedule-settings.templates.toggle');
        Route::post('tetapan-syif/penetapan', [ScheduleSettingsController::class, 'storeAssignment'])
            ->name('schedule-settings.assignments.store');
        Route::patch('tetapan-syif/penetapan/{assignment}/status', [ScheduleSettingsController::class, 'toggleAssignment'])
            ->whereNumber('assignment')
            ->name('schedule-settings.assignments.toggle');
    });

    Route::middleware('permission:performance.settings')->group(function () {
        Route::get('tetapan-prestasi', [PerformanceSettingsController::class, 'index'])
            ->name('performance-settings.index');
        Route::post('tetapan-prestasi/kitaran', [PerformanceSettingsController::class, 'storeCycle'])
            ->name('performance-settings.cycles.store');
        Route::put('tetapan-prestasi/kitaran/{cycle}', [PerformanceSettingsController::class, 'updateCycle'])
            ->whereNumber('cycle')
            ->name('performance-settings.cycles.update');
        Route::patch('tetapan-prestasi/kitaran/{cycle}/status', [PerformanceSettingsController::class, 'changeCycleStatus'])
            ->whereNumber('cycle')
            ->name('performance-settings.cycles.status');
        Route::post('tetapan-prestasi/template', [PerformanceSettingsController::class, 'storeTemplate'])
            ->name('performance-settings.templates.store');
        Route::put('tetapan-prestasi/template/{template}', [PerformanceSettingsController::class, 'updateTemplate'])
            ->whereNumber('template')
            ->name('performance-settings.templates.update');
        Route::patch('tetapan-prestasi/template/{template}/status', [PerformanceSettingsController::class, 'toggleTemplate'])
            ->whereNumber('template')
            ->name('performance-settings.templates.toggle');
        Route::post('tetapan-prestasi/penyelia', [PerformanceSettingsController::class, 'saveAssignment'])
            ->name('performance-settings.assignments.save');
        Route::delete('tetapan-prestasi/penyelia/{assignment}', [PerformanceSettingsController::class, 'destroyAssignment'])
            ->whereNumber('assignment')
            ->name('performance-settings.assignments.destroy');
    });

    Route::middleware('permission:recruitment.settings')->group(function () {
        Route::get('tetapan-pengambilan', [RecruitmentSettingsController::class, 'index'])
            ->name('recruitment-settings.index');
        Route::post('tetapan-pengambilan/template', [RecruitmentSettingsController::class, 'storeTemplate'])
            ->name('recruitment-settings.templates.store');
        Route::put('tetapan-pengambilan/template/{template}', [RecruitmentSettingsController::class, 'updateTemplate'])
            ->whereNumber('template')
            ->name('recruitment-settings.templates.update');
        Route::patch('tetapan-pengambilan/template/{template}/status', [RecruitmentSettingsController::class, 'toggleTemplate'])
            ->whereNumber('template')
            ->name('recruitment-settings.templates.toggle');
    });

    Route::middleware('permission:training.settings')->group(function () {
        Route::get('tetapan-latihan', [TrainingSettingsController::class, 'index'])
            ->name('training-settings.index');
        Route::post('tetapan-latihan/penyedia', [TrainingSettingsController::class, 'storeProvider'])
            ->name('training-settings.providers.store');
        Route::put('tetapan-latihan/penyedia/{provider}', [TrainingSettingsController::class, 'updateProvider'])
            ->whereNumber('provider')
            ->name('training-settings.providers.update');
        Route::patch('tetapan-latihan/penyedia/{provider}/status', [TrainingSettingsController::class, 'toggleProvider'])
            ->whereNumber('provider')
            ->name('training-settings.providers.toggle');
        Route::post('tetapan-latihan/kursus', [TrainingSettingsController::class, 'storeCourse'])
            ->name('training-settings.courses.store');
        Route::put('tetapan-latihan/kursus/{course}', [TrainingSettingsController::class, 'updateCourse'])
            ->whereNumber('course')
            ->name('training-settings.courses.update');
        Route::patch('tetapan-latihan/kursus/{course}/status', [TrainingSettingsController::class, 'toggleCourse'])
            ->whereNumber('course')
            ->name('training-settings.courses.toggle');
        Route::post('tetapan-latihan/sesi', [TrainingSettingsController::class, 'storeSession'])
            ->name('training-settings.sessions.store');
        Route::put('tetapan-latihan/sesi/{session}', [TrainingSettingsController::class, 'updateSession'])
            ->whereNumber('session')
            ->name('training-settings.sessions.update');
        Route::patch('tetapan-latihan/sesi/{session}/status', [TrainingSettingsController::class, 'changeSessionStatus'])
            ->whereNumber('session')
            ->name('training-settings.sessions.status');
        Route::post('tetapan-latihan/bajet', [TrainingSettingsController::class, 'saveBudget'])
            ->name('training-settings.budgets.save');
        Route::post('tetapan-latihan/kompetensi', [TrainingSettingsController::class, 'storeCompetency'])
            ->name('training-settings.competencies.store');
        Route::put('tetapan-latihan/kompetensi/{competency}', [TrainingSettingsController::class, 'updateCompetency'])
            ->whereNumber('competency')
            ->name('training-settings.competencies.update');
        Route::patch('tetapan-latihan/kompetensi/{competency}/status', [TrainingSettingsController::class, 'toggleCompetency'])
            ->whereNumber('competency')
            ->name('training-settings.competencies.toggle');
        Route::post('tetapan-latihan/keperluan', [TrainingSettingsController::class, 'storeRequirement'])
            ->name('training-settings.requirements.store');
        Route::put('tetapan-latihan/keperluan/{requirement}', [TrainingSettingsController::class, 'updateRequirement'])
            ->whereNumber('requirement')
            ->name('training-settings.requirements.update');
        Route::delete('tetapan-latihan/keperluan/{requirement}', [TrainingSettingsController::class, 'destroyRequirement'])
            ->whereNumber('requirement')
            ->name('training-settings.requirements.destroy');
        Route::post('tetapan-latihan/penyelia', [TrainingSettingsController::class, 'saveAssignment'])
            ->name('training-settings.assignments.save');
    });

    Route::middleware('permission:documents.settings')->group(function () {
        Route::get('tetapan-dokumen', [DocumentSettingsController::class, 'index'])
            ->name('document-settings.index');
        Route::post('tetapan-dokumen/template', [DocumentSettingsController::class, 'storeTemplate'])
            ->name('document-settings.templates.store');
        Route::put('tetapan-dokumen/template/{template}', [DocumentSettingsController::class, 'updateTemplate'])
            ->whereNumber('template')
            ->name('document-settings.templates.update');
        Route::patch('tetapan-dokumen/template/{template}/status', [DocumentSettingsController::class, 'toggleTemplate'])
            ->whereNumber('template')
            ->name('document-settings.templates.toggle');
        Route::post('tetapan-dokumen/siri', [DocumentSettingsController::class, 'saveSequence'])
            ->name('document-settings.sequences.save');
    });

    Route::middleware('permission:discipline.settings')->group(function () {
        Route::get('tetapan-disiplin', [DisciplineSettingsController::class, 'index'])
            ->name('discipline-settings.index');
        Route::post('tetapan-disiplin/kategori', [DisciplineSettingsController::class, 'store'])
            ->name('discipline-settings.categories.store');
        Route::put('tetapan-disiplin/kategori/{category}', [DisciplineSettingsController::class, 'update'])
            ->whereNumber('category')
            ->name('discipline-settings.categories.update');
        Route::patch('tetapan-disiplin/kategori/{category}/status', [DisciplineSettingsController::class, 'toggle'])
            ->whereNumber('category')
            ->name('discipline-settings.categories.toggle');
    });

    Route::middleware('permission:separation.settings')->group(function () {
        Route::get('tetapan-clearance', [SeparationSettingsController::class, 'index'])
            ->name('separation-settings.index');
        Route::post('tetapan-clearance/template', [SeparationSettingsController::class, 'store'])
            ->name('separation-settings.templates.store');
        Route::put('tetapan-clearance/template/{template}', [SeparationSettingsController::class, 'update'])
            ->whereNumber('template')
            ->name('separation-settings.templates.update');
        Route::patch('tetapan-clearance/template/{template}/status', [SeparationSettingsController::class, 'toggle'])
            ->whereNumber('template')
            ->name('separation-settings.templates.toggle');
        Route::post('tetapan-clearance/template/{template}/item', [SeparationSettingsController::class, 'storeItem'])
            ->whereNumber('template')
            ->name('separation-settings.items.store');
        Route::put('tetapan-clearance/template/{template}/item/{item}', [SeparationSettingsController::class, 'updateItem'])
            ->whereNumber(['template', 'item'])
            ->name('separation-settings.items.update');
        Route::delete('tetapan-clearance/template/{template}/item/{item}', [SeparationSettingsController::class, 'destroyItem'])
            ->whereNumber(['template', 'item'])
            ->name('separation-settings.items.destroy');
    });

    Route::middleware('permission:leave.settings')->group(function () {
        Route::get('tetapan-cuti', [LeaveSettingsController::class, 'index'])
            ->name('leave-settings.index');
        Route::post('tetapan-cuti/jenis', [LeaveSettingsController::class, 'storeType'])
            ->name('leave-settings.types.store');
        Route::put('tetapan-cuti/jenis/{leaveType}', [LeaveSettingsController::class, 'updateType'])
            ->name('leave-settings.types.update');
        Route::patch('tetapan-cuti/jenis/{leaveType}/status', [LeaveSettingsController::class, 'toggleType'])
            ->name('leave-settings.types.toggle');
        Route::post('tetapan-cuti/kelayakan', [LeaveSettingsController::class, 'saveEntitlement'])
            ->name('leave-settings.entitlements.save');
        Route::post('tetapan-cuti/penyelia', [LeaveSettingsController::class, 'saveAssignment'])
            ->name('leave-settings.assignments.save');
        Route::delete('tetapan-cuti/penyelia/{assignment}', [LeaveSettingsController::class, 'destroyAssignment'])
            ->name('leave-settings.assignments.destroy');
        Route::post('tetapan-cuti/cuti-umum', [LeaveSettingsController::class, 'storeHoliday'])
            ->name('leave-settings.holidays.store');
        Route::delete('tetapan-cuti/cuti-umum/{holiday}', [LeaveSettingsController::class, 'destroyHoliday'])
            ->name('leave-settings.holidays.destroy');
    });

    Route::middleware('permission:overtime.settings')->group(function () {
        Route::get('tetapan-ot', [OvertimeSettingsController::class, 'index'])
            ->name('overtime-settings.index');
        Route::post('tetapan-ot/jenis', [OvertimeSettingsController::class, 'storeType'])
            ->name('overtime-settings.types.store');
        Route::put('tetapan-ot/jenis/{overtimeType}', [OvertimeSettingsController::class, 'updateType'])
            ->name('overtime-settings.types.update');
        Route::patch('tetapan-ot/jenis/{overtimeType}/status', [OvertimeSettingsController::class, 'toggleType'])
            ->name('overtime-settings.types.toggle');
        Route::post('tetapan-ot/penyelia', [OvertimeSettingsController::class, 'saveAssignment'])
            ->name('overtime-settings.assignments.save');
        Route::delete('tetapan-ot/penyelia/{assignment}', [OvertimeSettingsController::class, 'destroyAssignment'])
            ->name('overtime-settings.assignments.destroy');
    });

    Route::middleware('permission:claims.settings')->group(function () {
        Route::get('tetapan-tuntutan', [ClaimSettingsController::class, 'index'])
            ->name('claim-settings.index');
        Route::post('tetapan-tuntutan/jenis', [ClaimSettingsController::class, 'storeType'])
            ->name('claim-settings.types.store');
        Route::put('tetapan-tuntutan/jenis/{claimType}', [ClaimSettingsController::class, 'updateType'])
            ->whereNumber('claimType')
            ->name('claim-settings.types.update');
        Route::patch('tetapan-tuntutan/jenis/{claimType}/status', [ClaimSettingsController::class, 'toggleType'])
            ->whereNumber('claimType')
            ->name('claim-settings.types.toggle');
        Route::post('tetapan-tuntutan/penyelia', [ClaimSettingsController::class, 'saveAssignment'])
            ->name('claim-settings.assignments.save');
        Route::delete('tetapan-tuntutan/penyelia/{assignment}', [ClaimSettingsController::class, 'destroyAssignment'])
            ->whereNumber('assignment')
            ->name('claim-settings.assignments.destroy');
        Route::post('tetapan-tuntutan/had-khas', [ClaimSettingsController::class, 'saveLimitOverride'])
            ->name('claim-settings.limits.save');
        Route::delete('tetapan-tuntutan/had-khas/{override}', [ClaimSettingsController::class, 'destroyLimitOverride'])
            ->whereNumber('override')
            ->name('claim-settings.limits.destroy');
    });

    Route::middleware('permission:payroll.settings')->group(function () {
        Route::get('tetapan-payroll', [PayrollSettingsController::class, 'index'])
            ->name('payroll-settings.index');
        Route::put('tetapan-payroll/pengiraan', [PayrollSettingsController::class, 'updateSettings'])
            ->name('payroll-settings.update');
        Route::post('tetapan-payroll/komponen', [PayrollSettingsController::class, 'storeComponent'])
            ->name('payroll-settings.components.store');
        Route::put('tetapan-payroll/komponen/{payrollComponent}', [PayrollSettingsController::class, 'updateComponent'])
            ->whereNumber('payrollComponent')
            ->name('payroll-settings.components.update');
        Route::patch('tetapan-payroll/komponen/{payrollComponent}/status', [PayrollSettingsController::class, 'toggleComponent'])
            ->whereNumber('payrollComponent')
            ->name('payroll-settings.components.toggle');
        Route::post('tetapan-payroll/profil-gaji', [PayrollSettingsController::class, 'saveSalaryProfile'])
            ->name('payroll-settings.salary-profiles.save');
        Route::post('tetapan-payroll/komponen-pekerja', [PayrollSettingsController::class, 'saveEmployeeComponent'])
            ->name('payroll-settings.employee-components.save');
        Route::patch('tetapan-payroll/komponen-pekerja/{employeePayrollComponent}/status', [PayrollSettingsController::class, 'toggleEmployeeComponent'])
            ->whereNumber('employeePayrollComponent')
            ->name('payroll-settings.employee-components.toggle');
        Route::get('tetapan-statutori', [StatutorySettingsController::class, 'index'])
            ->name('statutory-settings.index');
        Route::put('tetapan-statutori/kadar', [StatutorySettingsController::class, 'updateRates'])
            ->name('statutory-settings.rates.update');
        Route::put('tetapan-statutori/slip-gaji', [StatutorySettingsController::class, 'updatePayslip'])
            ->name('statutory-settings.payslip.update');
        Route::post('tetapan-statutori/profil-pekerja', [StatutorySettingsController::class, 'saveProfile'])
            ->name('statutory-settings.profiles.save');
    });
});

require __DIR__ . '/settings.php';
