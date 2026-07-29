import { Head } from '@inertiajs/react';
import JawatanForm from './partials/jawatan-form';
import type {
    PositionFormData,
    PositionFormOptions,
} from './partials/jawatan-form';

type PositionRecord = {
    id: number;
} & Partial<PositionFormData>;

type EmployeeRecord = {
    id: number;
    employee_id: string | null;
    nama: string | null;
};

type EditPositionProps = {
    jawatan: PositionRecord;
    pekerja: EmployeeRecord;
    options: PositionFormOptions;
};

function toFormValues(jawatan: PositionRecord): PositionFormData {
    return {
        id_pekerja: String(jawatan.id_pekerja ?? ''),
        date_lapordiri: String(jawatan.date_lapordiri ?? '').slice(0, 10),
        date_tempohcubaan: String(jawatan.date_tempohcubaan ?? '').slice(0, 10),
        id_department: String(jawatan.id_department ?? ''),
        jawatan: String(jawatan.jawatan ?? ''),
        salary: String(jawatan.salary ?? ''),
        id_bank: String(jawatan.id_bank ?? ''),
        noakaun: String(jawatan.noakaun ?? ''),
        noepf: String(jawatan.noepf ?? ''),
        nosocso: String(jawatan.nosocso ?? ''),
        jumlahcuti: String(jawatan.jumlahcuti ?? ''),
    };
}

export default function EditPosition({
    jawatan,
    pekerja,
    options,
}: EditPositionProps) {
    const employeeLabel = [pekerja.employee_id, pekerja.nama]
        .filter(Boolean)
        .join(' — ');

    return (
        <>
            <Head title={`Tukar Jawatan ${pekerja.nama ?? ''}`} />
            <JawatanForm
                mode="edit"
                options={options}
                initialValues={toFormValues(jawatan)}
                positionId={jawatan.id}
                employeeLabel={employeeLabel}
            />
        </>
    );
}

EditPosition.layout = {
    breadcrumbs: [
        { title: 'Jawatan', href: '/jawatan' },
        { title: 'Tukar Jawatan', href: '#' },
    ],
};
