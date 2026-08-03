<?php

namespace Database\Seeders;

use App\Models\ComplaintCategory;
use Illuminate\Database\Seeder;

class DisciplineSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['MISCONDUCT', 'Salah Laku', 'Pelanggaran peraturan, prosedur atau tatakelakuan organisasi.', 'medium', 30, true],
            ['HARASSMENT', 'Gangguan & Buli', 'Gangguan, buli, diskriminasi atau tingkah laku tidak wajar.', 'high', 21, true],
            ['INTEGRITY', 'Integriti & Penipuan', 'Penipuan, rasuah, konflik kepentingan atau salah guna aset.', 'critical', 14, true],
            ['ATTENDANCE', 'Kehadiran & Disiplin Masa', 'Ketidakhadiran, kelewatan berulang atau meninggalkan tugas.', 'medium', 30, true],
            ['INSUBORDINATION', 'Ingkar Arahan', 'Keengganan mematuhi arahan kerja yang sah dan munasabah.', 'high', 21, true],
            ['SAFETY', 'Keselamatan & Kesihatan', 'Pelanggaran keselamatan atau tindakan yang mewujudkan risiko.', 'high', 14, true],
            ['GRIEVANCE', 'Aduan Tempat Kerja', 'Ketidakpuasan atau pertikaian berkaitan persekitaran kerja.', 'medium', 30, false],
            ['OTHER', 'Lain-lain', 'Aduan lain yang memerlukan penilaian HR.', 'medium', 30, true],
        ];

        foreach ($categories as [$code, $name, $description, $severity, $sla, $showCause]) {
            ComplaintCategory::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'default_severity' => $severity,
                    'sla_days' => $sla,
                    'appeal_days' => 14,
                    'requires_show_cause' => $showCause,
                    'allow_protected_identity' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
