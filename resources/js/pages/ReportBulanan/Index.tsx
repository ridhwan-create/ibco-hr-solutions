import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Laporan Asal',
    description:
        'Rekod laporan bulanan lama daripada db_spp untuk rujukan sahaja.',
    routePath: '/laporan-bulanan-asal',
    searchPlaceholder: 'Cari nama, ID atau kandungan laporan...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama_pekerja', label: 'Nama' },
        { key: 'tarikh_mula', label: 'Tarikh Mula', type: 'date' },
        { key: 'tarikh_akhir', label: 'Tarikh Akhir', type: 'date' },
        { key: 'laporan', label: 'Laporan' },
    ],
};

export default function LaporanBulananIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

LaporanBulananIndex.layout = {
    breadcrumbs: [{ title: 'Laporan Asal', href: '/laporan-bulanan-asal' }],
};
