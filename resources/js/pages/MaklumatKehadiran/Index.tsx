import RecordsIndex from '@/components/records-index';
import type {
    RecordPageConfig,
    RecordsIndexPageProps,
} from '@/components/records-index';

const config: RecordPageConfig = {
    title: 'Kehadiran Asal (db_spp)',
    description:
        'Paparan rujukan baca sahaja bagi rekod waktu masuk dan keluar daripada sistem asal.',
    routePath: '/kehadiran-asal',
    searchPlaceholder: 'Cari nama, ID, pilihan jam atau catatan...',
    columns: [
        { key: 'employee_id', label: 'ID Pekerja' },
        { key: 'nama_pekerja', label: 'Nama' },
        { key: 'pilihan_jam', label: 'Pilihan Jam' },
        { key: 'waktu_masuk', label: 'Waktu Masuk', type: 'datetime' },
        { key: 'waktu_keluar', label: 'Waktu Keluar', type: 'datetime' },
        { key: 'catatan', label: 'Catatan' },
    ],
};

export default function KehadiranIndex(props: RecordsIndexPageProps) {
    return <RecordsIndex {...props} config={config} />;
}

KehadiranIndex.layout = {
    breadcrumbs: [
        { title: 'Masa & Kehadiran', href: '/kehadiran' },
        { title: 'Kehadiran Asal (db_spp)', href: '/kehadiran-asal' },
    ],
};
