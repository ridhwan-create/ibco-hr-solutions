import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Laporan Bulanan',
    description: 'Laporan bulanan yang direkodkan oleh pekerja.',
    routePath: '/laporan-bulanan',
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
    breadcrumbs: [{ title: 'Laporan Bulanan', href: '/laporan-bulanan' }],
};
