import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Maklumat Payroll',
    description: 'Senarai rekod payroll mengikut pekerja dan tempoh gaji.',
    routePath: '/payroll',
    searchPlaceholder: 'Cari nama, ID, NRIC atau bulan...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama', label: 'Nama' },
        { key: 'nric', label: 'NRIC' },
        { key: 'tempoh_gaji', label: 'Tempoh Gaji', type: 'date' },
        { key: 'bulan', label: 'Bulan', align: 'center' },
        { key: 'no_kwsp', label: 'No. KWSP' },
        { key: 'no_socso', label: 'No. PERKESO' },
        { key: 'no_akaun', label: 'No. Akaun' },
    ],
};

export default function PayrollIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

PayrollIndex.layout = {
    breadcrumbs: [{ title: 'Payroll', href: '/payroll' }],
};
