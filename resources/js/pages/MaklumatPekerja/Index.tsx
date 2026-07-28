import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage, useForm, router } from '@inertiajs/react';
import { PageProps } from '@inertiajs/core';
// Import AppLayout dibuang dari komponen utama kerana ia diuruskan oleh global layout
import { Button } from '@/components/ui/button';
import HeadingSmall from '@/components/heading-small';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Edit, Trash2, Search, Eye } from 'lucide-react';
import { useManualDebounce } from '@/hooks/useManualDebounce';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Senarai Pekerja', href: '/pekerja' },
];

interface Pekerja {
    id: number;
    employeeID: string;
    nama: string;
    nric: string;
    notel: string;
}

interface Props extends PageProps {
    pekerja: {
        data: Pekerja[];
        current_page: number;
        last_page: number;
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
    };
    filters: {
        search?: string;
    };
    flash?: {
        success?: string;
        error?: string;
    };
}

export default function PekerjaIndex() {
    const { props } = usePage<Props>();
    const { pekerja, flash, filters } = props;

    const { data, setData } = useForm({
        search: filters.search || '',
    });

    useManualDebounce(() => {
        router.get('/pekerja', { search: data.search }, { preserveState: true, replace: true });
    }, 500, [data.search]);

    const handleDelete = (id: number) => {
        if (confirm('Adakah anda pasti ingin memadam rekod pekerja ini?')) {
            router.delete(`/pekerja/${id}`);
        }
    };

    return (
        <>
            <Head title="Senarai Pekerja" />

            <div className='p-4 space-y-6'>
                {/* Flash Messages */}
                {flash?.success && <div className="bg-green-100 border border-green-400 text-green-700 px-3 py-2 rounded text-sm">{flash.success}</div>}
                {flash?.error && <div className="bg-red-100 border border-red-400 text-red-700 px-3 py-2 rounded text-sm">{flash.error}</div>}

                {/* Header & Search Actions */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <HeadingSmall title="Maklumat Pekerja" description="Senarai semua rekod maklumat pekerja" />

                    <div className="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
                        <div className="relative flex-grow">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <input
                                type="text"
                                placeholder="Cari Nama, ID, IC..."
                                className="pl-9 w-full border rounded px-3 py-2 text-sm"
                                value={data.search}
                                onChange={(e) => setData('search', e.target.value)}
                            />
                        </div>
                        <div className="flex gap-2">
                            <Button asChild size="sm" className="flex-1">
                                <Link href="/pekerja/create">+ Baru</Link>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Desktop Table */}
                <div className="hidden md:block overflow-hidden shadow ring-0 ring-gray-200 sm:rounded-lg">
                    <Table>
                        <TableHeader className="bg-muted">
                            <TableRow>
                                <TableHead>No.</TableHead>
                                <TableHead>ID Pekerja</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>NRIC</TableHead>
                                <TableHead>No Tel</TableHead>
                                <TableHead className="text-center">Tindakan</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {pekerja.data.length > 0 ? pekerja.data.map((p, index) => (
                                <TableRow key={p.id}>
                                    <TableCell>{(pekerja.current_page - 1) * 10 + index + 1}</TableCell>
                                    <TableCell className="font-medium">{p.employeeID}</TableCell>
                                    <TableCell>{p.nama}</TableCell>
                                    <TableCell>{p.nric}</TableCell>
                                    <TableCell>{p.notel || '-'}</TableCell>
                                    <TableCell className="text-center">
                                        <div className="flex items-center space-x-3 justify-center">
                                            <Link href={`/pekerja/${p.id}`} className="text-blue-600 hover:text-blue-900" title="Papar">
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                            <Link href={`/pekerja/${p.id}/edit`} className="text-indigo-600 hover:text-indigo-900" title="Edit">
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                            <button onClick={() => handleDelete(p.id)} className="text-red-600 hover:text-red-900" title="Padam">
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            )) : (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center py-4 text-sm text-muted-foreground">
                                        Tiada rekod pekerja ditemui.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Mobile Cards */}
                <div className="md:hidden space-y-3">
                    {pekerja.data.length > 0 ? pekerja.data.map((p, index) => (
                        <Card key={p.id}>
                            <CardHeader className="p-4 pb-2">
                                <CardTitle className="text-base flex justify-between items-start">
                                    <span>{(pekerja.current_page - 1) * 10 + index + 1}. {p.nama}</span>
                                    <div className="flex gap-3">
                                        <Link href={`/pekerja/${p.id}`} className="text-blue-600 hover:text-blue-900">
                                            <Eye className="h-4 w-4" />
                                        </Link>
                                        <Link href={`/pekerja/${p.id}/edit`} className="text-indigo-600 hover:text-indigo-900">
                                            <Edit className="h-4 w-4" />
                                        </Link>
                                        <button onClick={() => handleDelete(p.id)} className="text-red-600 hover:text-red-900">
                                            <Trash2 className="h-4 w-4" />
                                        </button>
                                    </div>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-4 pt-0 text-sm">
                                <div className="grid grid-cols-2 gap-2 mt-2">
                                    <div>
                                        <p className="text-muted-foreground">ID Pekerja</p>
                                        <p className="font-medium">{p.employeeID}</p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">NRIC</p>
                                        <p>{p.nric}</p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-muted-foreground">No. Telefon</p>
                                        <p>{p.notel || '-'}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )) : (
                        <div className="text-center py-4 text-sm text-muted-foreground">
                            Tiada rekod pekerja ditemui.
                        </div>
                    )}
                </div>

                {/* Pagination Desktop */}
                {pekerja.links && pekerja.links.length > 3 && (
                    <nav className="flex items-center justify-between px-4 py-3 bg-muted border-t border-gray-200 sm:px-6 rounded-b-lg">
                        <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div className="flex gap-x-2 items-baseline">
                                <span className="text-sm">
                                    Menunjukkan{' '}
                                    <span className="font-medium">{(pekerja.current_page - 1) * 10 + 1}</span> hingga{' '}
                                    <span className="font-medium">
                                        {Math.min(pekerja.current_page * 10, pekerja.data.length + ((pekerja.current_page - 1) * 10))}
                                    </span>{' '}
                                    daripada{' '}
                                    <span className="font-medium">
                                        {pekerja.data.length + ((pekerja.current_page - 1) * 10)}
                                    </span>{' '}
                                    rekod
                                </span>
                            </div>
                            <div className="flex gap-1">
                                {pekerja.links.map((link, index) => (
                                    <button
                                        key={index}
                                        type="button"
                                        disabled={!link.url}
                                        onClick={() => {
                                            if (link.url) {
                                                const url = new URL(link.url, window.location.origin);
                                                const page = url.searchParams.get('page') ?? '1';

                                                router.get('/pekerja', {
                                                    search: data.search,
                                                    page: page,
                                                }, {
                                                    preserveState: true,
                                                    replace: true,
                                                    only: ['pekerja'],
                                                });
                                            }
                                        }}
                                        className={`relative inline-flex items-center px-3 py-1 border text-sm font-medium rounded-md ${link.active
                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                            } ${!link.url && 'pointer-events-none opacity-50'}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>

                        {/* Pagination Mobile */}
                        <div className="sm:hidden flex items-center justify-between w-full">
                            <button
                                type="button"
                                disabled={pekerja.current_page === 1}
                                onClick={() => {
                                    router.get('/pekerja', {
                                        search: data.search,
                                        page: pekerja.current_page - 1,
                                    }, {
                                        preserveState: true,
                                        replace: true,
                                        only: ['pekerja'],
                                    });
                                }}
                                className={`relative inline-flex items-center px-3 py-1 border text-sm font-medium rounded-md ${pekerja.current_page === 1 ? 'pointer-events-none opacity-50' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'}`}
                            >
                                Previous
                            </button>
                            <span className="text-sm text-gray-700">
                                Halaman {pekerja.current_page} / {pekerja.last_page}
                            </span>
                            <button
                                type="button"
                                disabled={pekerja.current_page === pekerja.last_page}
                                onClick={() => {
                                    router.get('/pekerja', {
                                        search: data.search,
                                        page: pekerja.current_page + 1,
                                    }, {
                                        preserveState: true,
                                        replace: true,
                                        only: ['pekerja'],
                                    });
                                }}
                                className={`relative inline-flex items-center px-3 py-1 border text-sm font-medium rounded-md ${pekerja.current_page === pekerja.last_page ? 'pointer-events-none opacity-50' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'}`}
                            >
                                Next
                            </button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}

// Layout configuration untuk breadcrumbs diletakkan di luar komponen
PekerjaIndex.layout = {
    breadcrumbs: breadcrumbs,
};