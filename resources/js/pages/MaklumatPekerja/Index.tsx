import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Maklumat Pekerja',
    description: 'Senarai pekerja aktif yang direkodkan dalam sistem.',
    routePath: '/pekerja',
    detailRoutePath: '/pekerja',
    createRoutePath: '/pekerja/create',
    editRoutePath: '/pekerja',
    deleteRoutePath: '/pekerja',
    entityLabel: 'pekerja',
    searchPlaceholder: 'Cari nama, ID, NRIC atau e-mel...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama', label: 'Nama' },
        { key: 'nric', label: 'NRIC' },
        { key: 'no_telefon', label: 'No. Telefon' },
        { key: 'email', label: 'E-mel' },
        { key: 'status', label: 'Status', type: 'badge' },
    ],
};

export default function PekerjaIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

PekerjaIndex.layout = {
    breadcrumbs: [{ title: 'Pekerja', href: '/pekerja' }],
};
