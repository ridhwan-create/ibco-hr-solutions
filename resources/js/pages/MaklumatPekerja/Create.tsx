import { Head } from '@inertiajs/react';
import PekerjaForm from './partials/pekerja-form';
import type {
    EmployeeFormData,
    EmployeeFormOptions,
} from './partials/pekerja-form';

type CreateEmployeeProps = {
    options: EmployeeFormOptions;
};

const initialValues: EmployeeFormData = {
    employeeID: '',
    nric: '',
    nama: '',
    alamat: '',
    jantina: '',
    tarikhlahir: '',
    agama: '',
    bangsa: '',
    kewarganegaraan: 'Malaysia',
    statusperkahwinan: '',
    notel: '',
    email: '',
    status: '',
};

export default function CreateEmployee({ options }: CreateEmployeeProps) {
    return (
        <>
            <Head title="Tambah Pekerja" />
            <PekerjaForm
                mode="create"
                options={options}
                initialValues={initialValues}
            />
        </>
    );
}

CreateEmployee.layout = {
    breadcrumbs: [
        { title: 'Pekerja', href: '/pekerja' },
        { title: 'Tambah Pekerja', href: '/pekerja/create' },
    ],
};
