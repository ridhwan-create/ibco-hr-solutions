<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\BulkResetUserPasswordsRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserPasswordBulkResetController extends Controller
{
    public function create(Request $request): Response
    {
        $users = User::query()
            ->with([
                'roleAssignments',
                'employeeLink' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('officeLocation:id,name'),
            ])
            ->whereKeyNot($request->user()->getAuthIdentifier())
            ->whereHas(
                'roleAssignments',
                fn ($query) => $query
                    ->where('role', UserRole::Employee->value),
            )
            ->whereHas(
                'employeeLink',
                fn ($query) => $query->where('is_active', true),
            )
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $link = $user->employeeLink;

                return [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roleValues(),
                    'role_labels' => collect($user->resolvedRoles())
                        ->map(fn (UserRole $role) => $role->label())
                        ->all(),
                    'employee_id' => $link?->employee_id,
                    'office' => $link?->officeLocation?->name,
                ];
            })
            ->values();

        return Inertia::render('users/reset-passwords', [
            'users' => $users,
            'resetResult' => $request->session()
                ->get('bulk_password_reset_result'),
        ]);
    }

    public function store(
        BulkResetUserPasswordsRequest $request,
    ): RedirectResponse {
        $selectedIds = collect($request->validated('user_ids'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $result = DB::transaction(function () use ($request, $selectedIds) {
            $users = User::query()
                ->with('roleAssignments')
                ->whereIn('id', $selectedIds)
                ->whereKeyNot($request->user()->getAuthIdentifier())
                ->whereHas(
                    'roleAssignments',
                    fn ($query) => $query
                        ->where('role', UserRole::Employee->value),
                )
                ->whereHas(
                    'employeeLink',
                    fn ($query) => $query->where('is_active', true),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($users->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'user_ids' => 'Sebahagian pengguna tidak lagi layak untuk reset pukal. Muat semula halaman dan semak pilihan.',
                ]);
            }

            $credentials = [];

            foreach ($users as $user) {
                $temporaryPassword = Str::password(16);

                $user->forceFill([
                    'password' => $temporaryPassword,
                    'remember_token' => Str::random(60),
                ])->save();

                AuditLogger::record(
                    $request,
                    'user.password.bulk_reset',
                    'users',
                    $user->getKey(),
                    newValues: [
                        'method' => 'temporary_password',
                        'source' => 'bulk_password_reset',
                    ],
                );

                $credentials[] = [
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'temporary_password' => $temporaryPassword,
                ];
            }

            return [
                'reset_count' => count($credentials),
                'credentials' => $credentials,
            ];
        });

        return redirect()
            ->route('users.password-reset.create')
            ->with('bulk_password_reset_result', $result)
            ->with('toast', [
                'type' => 'success',
                'message' => sprintf(
                    '%d kata laluan pengguna berjaya ditetapkan semula.',
                    $result['reset_count'],
                ),
            ]);
    }
}
