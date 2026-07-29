import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Maklumat Cuti',
    description: 'Senarai permohonan dan baki cuti pekerja.',
    routePath: '/cuti',
    searchPlaceholder: 'Cari nama, ID, jenis atau status cuti...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama_pekerja', label: 'Nama' },
        { key: 'jenis_cuti', label: 'Jenis Cuti' },
        { key: 'tarikh_mula', label: 'Tarikh Mula', type: 'date' },
        { key: 'tarikh_tamat', label: 'Tarikh Tamat', type: 'date' },
        { key: 'bilangan_hari', label: 'Bil. Hari', align: 'center' },
        {
            key: 'status_permohonan',
            label: 'Status',
            type: 'badge',
        },
    ],
};

export default function CutiIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

CutiIndex.layout = {
    breadcrumbs: [{ title: 'Cuti', href: '/cuti' }],
};
