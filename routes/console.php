<?php

use App\Models\EmployeeRecord;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    EmployeeRecord::query()
        ->with('user')
        ->where('status', 'pending_activation')
        ->whereDate('start_date', '<=', today())
        ->chunkById(100, function ($employees): void {
            foreach ($employees as $employee) {
                DB::transaction(function () use ($employee): void {
                    $employee->activate();
                    $employee->user?->forceFill(['account_status' => 'active'])->save();
                });
            }
        });
})->dailyAt('00:05')->name('activate-due-employees')->withoutOverlapping();
