import { Head } from '@inertiajs/react';
import type { UserRole } from '@/types';
import UserForm from './partials/user-form';
import type {
    SystemUserFormData,
    SystemUserFormOptions,
} from './partials/user-form';

type CreateUserProps = {
    options: SystemUserFormOptions;
    defaultRoles: UserRole[];
};

export default function CreateUser({ options, defaultRoles }: CreateUserProps) {
    const initialValues: SystemUserFormData = {
        name: '',
        email: '',
        roles: defaultRoles,
        employee_id: '',
        office_location_id: '',
        password: '',
        password_confirmation: '',
    };

    return (
        <>
            <Head title="Tambah Pengguna" />
            <UserForm
                mode="create"
                initialValues={initialValues}
                options={options}
            />
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Roles & Permissions', href: '/pengguna' },
        { title: 'Tambah Pengguna', href: '/pengguna/create' },
    ],
};
