import { Head, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Circle,
    Clock3,
    ListChecks,
    Play,
    UserRoundCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Task = {
    id: number;
    title: string;
    description: string | null;
    category: string;
    assignee_role: string;
    assignee_user_id: number | null;
    assignee: string | null;
    due_date: string;
    is_required: boolean;
    status: string;
    completion_notes: string | null;
    can_update: boolean;
};
type Props = {
    onboarding: {
        id: number;
        candidate_name: string;
        position: string;
        template: string | null;
        manager: string | null;
        buddy: string | null;
        start_date: string;
        status: string;
        progress: number;
        tasks: Task[];
    } | null;
};

const statusLabel: Record<string, string> = {
    pending: 'Belum Bermula',
    active: 'Aktif',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    in_progress: 'Dalam Tindakan',
    waived: 'Dikecualikan',
};

function TaskUpdateDialog({ task }: { task: Task }) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        status:
            task.status === 'completed'
                ? 'completed'
                : ('in_progress' as string),
        completion_notes: task.completion_notes ?? '',
    });
    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(`/onboarding-saya/tugasan/${task.id}`, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <Play />
                    Kemas Kini
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{task.title}</DialogTitle>
                    <DialogDescription>
                        Tandakan tugasan dalam tindakan atau selesai.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>Status</Label>
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData('status', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="in_progress">
                                    Dalam Tindakan
                                </SelectItem>
                                <SelectItem value="completed">
                                    Selesai
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Catatan Penyelesaian</Label>
                        <textarea
                            value={form.data.completion_notes}
                            onChange={(event) =>
                                form.setData(
                                    'completion_notes',
                                    event.target.value,
                                )
                            }
                            className="min-h-28 w-full rounded-md border bg-background px-3 py-2 text-sm"
                            placeholder="Nyatakan tindakan atau bukti penyelesaian..."
                        />
                        <InputError message={form.errors.completion_notes} />
                    </div>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Tutup
                            </Button>
                        </DialogClose>
                        <Button disabled={form.processing}>
                            <CheckCircle2 />
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeOnboarding({ onboarding }: Props) {
    return (
        <>
            <Head title="Onboarding Saya" />
            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Onboarding Saya</h1>
                    <p className="text-sm text-muted-foreground">
                        Checklist lapor diri dan tugasan yang perlu anda
                        lengkapkan.
                    </p>
                </div>

                {!onboarding ? (
                    <Card>
                        <CardContent className="py-16 text-center">
                            <ListChecks className="mx-auto mb-3 size-10 text-muted-foreground" />
                            <p className="font-medium">
                                Tiada kes onboarding aktif
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Checklist akan dipaparkan selepas HR memautkan
                                akaun anda.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <div className="grid gap-4 md:grid-cols-4">
                            <Card className="md:col-span-2">
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <CardTitle>
                                                {onboarding.position}
                                            </CardTitle>
                                            <CardDescription>
                                                {onboarding.template ??
                                                    'Checklist onboarding'}
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline">
                                            {statusLabel[onboarding.status] ??
                                                onboarding.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-2 flex items-center justify-between text-sm">
                                        <span>Kemajuan keseluruhan</span>
                                        <strong>{onboarding.progress}%</strong>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-muted">
                                        <div
                                            className="h-full bg-emerald-500"
                                            style={{
                                                width: `${onboarding.progress}%`,
                                            }}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex items-center gap-3 p-5">
                                    <CalendarDays className="size-8 text-sky-600" />
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Tarikh Mula
                                        </p>
                                        <p className="font-medium">
                                            {onboarding.start_date}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardContent className="flex items-center gap-3 p-5">
                                    <UserRoundCheck className="size-8 text-violet-600" />
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Pengurus / Buddy
                                        </p>
                                        <p className="font-medium">
                                            {onboarding.manager ?? '—'}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {onboarding.buddy ??
                                                'Buddy belum ditetapkan'}
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <Card>
                            <CardHeader>
                                <CardTitle>Checklist Tugasan</CardTitle>
                                <CardDescription>
                                    Tugasan bertanda Pekerja boleh dikemas kini
                                    sendiri.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-3 md:grid-cols-2">
                                {onboarding.tasks.map((task) => {
                                    const complete = [
                                        'completed',
                                        'waived',
                                    ].includes(task.status);
                                    const overdue =
                                        !complete &&
                                        new Date(`${task.due_date}T23:59:59`) <
                                            new Date();

                                    return (
                                        <div
                                            key={task.id}
                                            className={`rounded-lg border p-4 ${
                                                overdue
                                                    ? 'border-red-500/40 bg-red-500/5'
                                                    : ''
                                            }`}
                                        >
                                            <div className="flex items-start gap-3">
                                                {complete ? (
                                                    <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                                ) : task.status ===
                                                  'in_progress' ? (
                                                    <Clock3 className="mt-0.5 size-5 shrink-0 text-sky-600" />
                                                ) : (
                                                    <Circle className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <p className="font-medium">
                                                            {task.title}
                                                        </p>
                                                        <Badge variant="outline">
                                                            {statusLabel[
                                                                task.status
                                                            ] ?? task.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {task.description ||
                                                            'Tiada penerangan.'}
                                                    </p>
                                                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                                        <span>
                                                            Tarikh akhir:{' '}
                                                            {task.due_date}
                                                            {task.is_required
                                                                ? ' · Wajib'
                                                                : ''}
                                                        </span>
                                                        {task.can_update &&
                                                            !complete && (
                                                                <TaskUpdateDialog
                                                                    task={task}
                                                                />
                                                            )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </>
    );
}
