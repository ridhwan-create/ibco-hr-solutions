import { Head } from '@inertiajs/react';
import PekerjaForm from './partials/pekerja-form';
import type {
    EmployeeFormData,
    EmployeeFormOptions,
} from './partials/pekerja-form';

type EmployeeRecord = {
    id: number;
} & Partial<EmployeeFormData>;

type EditEmployeeProps = {
    pekerja: EmployeeRecord;
    options: EmployeeFormOptions;
};

const toFormValues = (pekerja: EmployeeRecord): EmployeeFormData => ({
    employeeID: String(pekerja.employeeID ?? ''),
    nric: String(pekerja.nric ?? ''),
    nama: String(pekerja.nama ?? ''),
    alamat: String(pekerja.alamat ?? ''),
    jantina: String(pekerja.jantina ?? ''),
    tarikhlahir: String(pekerja.tarikhlahir ?? '').slice(0, 10),
    agama: String(pekerja.agama ?? ''),
    bangsa: String(pekerja.bangsa ?? ''),
    kewarganegaraan: String(pekerja.kewarganegaraan ?? ''),
    statusperkahwinan: String(pekerja.statusperkahwinan ?? ''),
    notel: String(pekerja.notel ?? ''),
    email: String(pekerja.email ?? ''),
    status: String(pekerja.status ?? ''),
});

export default function EditEmployee({ pekerja, options }: EditEmployeeProps) {
    return (
        <>
            <Head title={`Edit ${pekerja.nama || 'Pekerja'}`} />
            <PekerjaForm
                mode="edit"
                options={options}
                initialValues={toFormValues(pekerja)}
                employeeId={pekerja.id}
            />
        </>
    );
}

EditEmployee.layout = {
    breadcrumbs: [
        { title: 'Pekerja', href: '/pekerja' },
        { title: 'Edit Pekerja', href: '#' },
    ],
};
