<?php

namespace Database\Seeders;

use App\Models\ClearanceTemplateItem;
use App\Models\DocumentTemplate;
use App\Models\SeparationTemplate;
use Illuminate\Database\Seeder;

class SeparationClearanceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $definition) {
            $items = $definition['items'];
            unset($definition['items']);
            $template = SeparationTemplate::query()->updateOrCreate(
                ['code' => $definition['code']],
                [...$definition, 'is_active' => true],
            );

            foreach ($items as $index => $item) {
                ClearanceTemplateItem::query()->updateOrCreate(
                    [
                        'separation_template_id' => $template->getKey(),
                        'title' => $item['title'],
                    ],
                    [...$item, 'sort_order' => ($index + 1) * 10],
                );
            }
        }

        foreach ($this->documentTemplates() as $template) {
            DocumentTemplate::query()->updateOrCreate(
                ['code' => $template['code']],
                [...$template, 'sequence_key' => 'DEFAULT', 'is_active' => true],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        $items = [
            [
                'title' => 'Notis dan maklumat peribadi',
                'description' => 'Sahkan notis, alamat, e-mel dan nombor telefon selepas perkhidmatan.',
                'owner_type' => 'employee',
                'due_offset_days' => -14,
                'is_mandatory' => true,
                'employee_action_required' => true,
                'evidence_required' => false,
            ],
            [
                'title' => 'Serahan tugas dan fail kerja',
                'description' => 'Pastikan semua projek, akses, fail dan tindakan tertunggak diserahkan kepada pegawai penerima.',
                'owner_type' => 'supervisor',
                'due_offset_days' => -5,
                'is_mandatory' => true,
                'employee_action_required' => true,
                'evidence_required' => true,
            ],
            [
                'title' => 'Pemulangan peralatan ICT dan penutupan akses',
                'description' => 'Semak komputer, telefon, token, lesen, e-mel dan semua akses sistem.',
                'owner_type' => 'ict',
                'due_offset_days' => 0,
                'is_mandatory' => true,
                'employee_action_required' => false,
                'evidence_required' => true,
            ],
            [
                'title' => 'Pemulangan aset dan pas keselamatan',
                'description' => 'Semak kad akses, kunci, parkir, perabot atau aset pentadbiran.',
                'owner_type' => 'administration',
                'due_offset_days' => 0,
                'is_mandatory' => true,
                'employee_action_required' => false,
                'evidence_required' => false,
            ],
            [
                'title' => 'Semakan tuntutan, pendahuluan dan hutang',
                'description' => 'Sahkan semua tuntutan, pendahuluan, pinjaman atau baki perlu dibayar.',
                'owner_type' => 'finance',
                'due_offset_days' => 3,
                'is_mandatory' => true,
                'employee_action_required' => false,
                'evidence_required' => false,
            ],
            [
                'title' => 'Gaji akhir dan faedah statutori',
                'description' => 'Semak gaji akhir, baki cuti, potongan, PCB dan caruman berkaitan.',
                'owner_type' => 'payroll',
                'due_offset_days' => 7,
                'is_mandatory' => true,
                'employee_action_required' => false,
                'evidence_required' => false,
            ],
            [
                'title' => 'Exit interview dan dokumen HR',
                'description' => 'Lengkapkan exit interview, surat rasmi serta alamat penghantaran dokumen akhir.',
                'owner_type' => 'hr',
                'due_offset_days' => 7,
                'is_mandatory' => true,
                'employee_action_required' => false,
                'evidence_required' => false,
            ],
        ];

        return [
            [
                'code' => 'RESIGNATION',
                'name' => 'Berhenti Secara Sukarela',
                'description' => 'Aliran notis berhenti yang dimulakan oleh pekerja.',
                'separation_type' => 'resignation',
                'minimum_notice_days' => 30,
                'employee_can_apply' => true,
                'exit_interview_required' => true,
                'final_settlement_required' => true,
                'items' => $items,
            ],
            [
                'code' => 'CONTRACT-END',
                'name' => 'Tamat Kontrak',
                'description' => 'Clearance bagi kontrak yang tamat pada tarikh dipersetujui.',
                'separation_type' => 'contract_end',
                'minimum_notice_days' => 0,
                'employee_can_apply' => false,
                'exit_interview_required' => true,
                'final_settlement_required' => true,
                'items' => $items,
            ],
            [
                'code' => 'RETIREMENT',
                'name' => 'Persaraan',
                'description' => 'Clearance persaraan wajib atau pilihan.',
                'separation_type' => 'retirement',
                'minimum_notice_days' => 0,
                'employee_can_apply' => false,
                'exit_interview_required' => false,
                'final_settlement_required' => true,
                'items' => $items,
            ],
            [
                'code' => 'TERMINATION',
                'name' => 'Penamatan Perkhidmatan',
                'description' => 'Clearance bagi penamatan, pengurangan tenaga kerja atau sebab organisasi.',
                'separation_type' => 'termination',
                'minimum_notice_days' => 0,
                'employee_can_apply' => false,
                'exit_interview_required' => false,
                'final_settlement_required' => true,
                'items' => $items,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentTemplates(): array
    {
        $variables = [
            'employee_name', 'employee_number', 'department_name',
            'position_name', 'effective_date', 'signatory_name',
            'signatory_position', 'company_name', 'case_number',
            'separation_type', 'last_working_date',
        ];

        return [
            [
                'code' => 'EMP-RESIGN-ACK',
                'name' => 'Penerimaan Notis Berhenti Kerja',
                'category' => 'resignation',
                'subject_template' => 'Penerimaan Notis Berhenti Kerja – {{employee_name}}',
                'body_template' => "Pihak syarikat mengesahkan penerimaan notis berhenti kerja bagi {{employee_name}} ({{employee_number}}).\n\nTarikh akhir perkhidmatan yang diluluskan ialah {{last_working_date}}. Semua urusan serahan tugas, pemulangan aset dan clearance hendaklah diselesaikan sebelum penutupan kes {{case_number}}.",
                'available_variables' => $variables,
                'requires_approval' => true,
                'acknowledgement_required' => true,
                'confidentiality' => 'confidential',
            ],
            [
                'code' => 'EMP-CLEARANCE',
                'name' => 'Pengesahan Selesai Clearance',
                'category' => 'clearance',
                'subject_template' => 'Pengesahan Selesai Clearance – {{employee_name}}',
                'body_template' => "Dengan ini disahkan bahawa proses clearance bagi {{employee_name}} ({{employee_number}}) untuk kes {{case_number}} telah diselesaikan.\n\nTarikh akhir perkhidmatan: {{last_working_date}}. Pengesahan ini tertakluk kepada rekod final settlement dan dokumen rasmi organisasi.",
                'available_variables' => $variables,
                'requires_approval' => true,
                'acknowledgement_required' => false,
                'confidentiality' => 'confidential',
            ],
        ];
    }
}
