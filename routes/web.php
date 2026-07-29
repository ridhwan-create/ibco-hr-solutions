<?php

use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AttendanceSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\EmployeeUserImportController;
use App\Http\Controllers\GeoAttendanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveSettingsController;
use App\Http\Controllers\MaklumatCutiController;
use App\Http\Controllers\MaklumatJawatanController;
use App\Http\Controllers\MaklumatKehadiranController;
use App\Http\Controllers\MaklumatOtController;
use App\Http\Controllers\MaklumatPayrollController;
use App\Http\Controllers\MaklumatPekerjaController;
use App\Http\Controllers\ReportBulananController;
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
        ->middleware('permission:leave.manage')
        ->whereNumber('leaveRequest')
        ->name('leave-requests.review');
    Route::patch('permohonan-cuti/{leaveRequest}/batal-kelulusan', [LeaveRequestController::class, 'cancelApproved'])
        ->middleware('permission:leave.manage')
        ->whereNumber('leaveRequest')
        ->name('leave-requests.cancel-approved');
    Route::get('kerja-lebih-masa', [MaklumatOtController::class, 'index'])
        ->middleware('permission:overtime.view')
        ->name('kerja-lebih-masa.index');
    Route::get('payroll', [MaklumatPayrollController::class, 'index'])
        ->middleware('permission:payroll.view')
        ->name('payroll.index');
    Route::get('laporan-bulanan', [ReportBulananController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('laporan-bulanan.index');

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
});

require __DIR__ . '/settings.php';
