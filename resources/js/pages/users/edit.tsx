import { Head } from '@inertiajs/react';
import type { UserRole } from '@/types';
import UserForm from './partials/user-form';
import type {
    SystemUserFormData,
    SystemUserFormOptions,
} from './partials/user-form';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    roles: UserRole[];
    is_current_user: boolean;
    employee_id: number | null;
    office_location_id: number | null;
};

type EditUserProps = {
    managedUser: ManagedUser;
    options: SystemUserFormOptions;
};

export default function EditUser({ managedUser, options }: EditUserProps) {
    const initialValues: SystemUserFormData = {
        name: managedUser.name,
        email: managedUser.email,
        roles: managedUser.roles,
        employee_id: managedUser.employee_id
            ? String(managedUser.employee_id)
            : '',
        office_location_id: managedUser.office_location_id
            ? String(managedUser.office_location_id)
            : '',
        password: '',
        password_confirmation: '',
    };

    return (
        <>
            <Head title={`Edit ${managedUser.name}`} />
            <UserForm
                mode="edit"
                initialValues={initialValues}
                options={options}
                userId={managedUser.id}
                protectSuperAdmin={
                    managedUser.is_current_user &&
                    managedUser.roles.includes('super_admin')
                }
            />
        </>
    );
}

EditUser.layout = {
    breadcrumbs: [
        { title: 'Roles & Permissions', href: '/pengguna' },
        { title: 'Kemas Kini Pengguna', href: '#' },
    ],
};
