import { Head } from '@inertiajs/react';
import JawatanForm from './partials/jawatan-form';
import type {
    PositionFormData,
    PositionFormOptions,
} from './partials/jawatan-form';

type CreatePositionProps = {
    options: PositionFormOptions;
    selectedEmployeeId: string;
};

export default function CreatePosition({
    options,
    selectedEmployeeId,
}: CreatePositionProps) {
    const initialValues: PositionFormData = {
        id_pekerja: selectedEmployeeId,
        date_lapordiri: '',
        date_tempohcubaan: '',
        id_department: '',
        jawatan: '',
        salary: '',
        id_bank: '',
        noakaun: '',
        noepf: '',
        nosocso: '',
        jumlahcuti: '',
    };

    return (
        <>
            <Head title="Tambah Penempatan" />
            <JawatanForm
                mode="create"
                options={options}
                initialValues={initialValues}
            />
        </>
    );
}

CreatePosition.layout = {
    breadcrumbs: [
        { title: 'Jawatan', href: '/jawatan' },
        { title: 'Tambah Penempatan', href: '/jawatan/create' },
    ],
};
