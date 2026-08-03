<?php

namespace App\Support;

use App\Models\EmployeeRecord;
use App\Models\EmployeeUserLink;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingEmployeeResolver
{
    public function forUser(User|int $user): ?object
    {
        $userId = $user instanceof User ? $user->getKey() : $user;
        $employeeId = EmployeeUserLink::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->value('employee_id');

        return $employeeId ? $this->forEmployee((int) $employeeId) : null;
    }

    public function forEmployee(int $employeeId): ?object
    {
        return $this->forEmployees([$employeeId])->first();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int, object>
     */
    public function forEmployees(array $employeeIds): Collection
    {
        if ($employeeIds === []) {
            return collect();
        }

        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja as employee')
            ->leftJoin('maklumatjawatan as position', function ($join) {
                $join->on('position.id_pekerja', '=', 'employee.id')
                    ->where('position.rcd_enable', 1);
            })
            ->leftJoin('xdepartment as department', 'position.id_department', '=', 'department.id')
            ->where('employee.rcd_enable', 1)
            ->whereIn('employee.id', $employeeIds)
            ->orderByDesc('position.id')
            ->get([
                'employee.id',
                'employee.employeeID as employee_number',
                'employee.nama as name',
                'position.id_department as department_id',
                'department.description as department_name',
                'position.jawatan as position_name',
            ])
            ->unique('id')
            ->values();
        $localRecords = EmployeeRecord::query()
            ->whereIn('directory_id', $employeeIds)
            ->whereIn('status', ['pending_activation', 'active'])
            ->get();
        $departmentNames = DB::connection('ibco')
            ->table('xdepartment')
            ->whereIn('id', $localRecords->pluck('department_id')->filter())
            ->pluck('description', 'id');
        $local = $localRecords
            ->map(fn (EmployeeRecord $employee) => (object) [
                'id' => $employee->directory_id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
                'department_id' => $employee->department_id,
                'department_name' => $departmentNames[$employee->department_id]
                    ?? null,
                'position_name' => $employee->position_name,
            ]);

        return $legacy->concat($local)->unique('id')->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function linkedOptions(): Collection
    {
        $links = EmployeeUserLink::query()
            ->where('is_active', true)
            ->with('user:id,name,email')
            ->get()
            ->keyBy('employee_id');

        return $this->forEmployees($links->keys()->all())
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'user_id' => $links[$employee->id]?->user_id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
                'email' => $links[$employee->id]?->user?->email,
                'department_id' => $employee->department_id,
                'department_name' => $employee->department_name,
                'position_name' => $employee->position_name,
            ]);
    }
}
