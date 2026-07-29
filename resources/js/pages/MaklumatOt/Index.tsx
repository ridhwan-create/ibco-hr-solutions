import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Kerja Lebih Masa',
    description: 'Rekod kerja lebih masa yang telah dimasukkan.',
    routePath: '/kerja-lebih-masa',
    searchPlaceholder: 'Cari nama, ID, jenis OT atau catatan...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama_pekerja', label: 'Nama' },
        { key: 'jenis_ot', label: 'Jenis OT' },
        { key: 'tarikh', label: 'Tarikh', type: 'date' },
        { key: 'waktu_masuk', label: 'Waktu Mula', type: 'time' },
        { key: 'waktu_keluar', label: 'Waktu Tamat', type: 'time' },
        { key: 'catatan', label: 'Catatan' },
    ],
};

export default function OtIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

OtIndex.layout = {
    breadcrumbs: [{ title: 'Kerja Lebih Masa', href: '/kerja-lebih-masa' }],
};
