<?php

namespace Database\Seeders;

use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class HrDocumentSeeder extends Seeder
{
    public function run(): void
    {
        DocumentSequence::query()->updateOrCreate(
            ['sequence_key' => 'DEFAULT'],
            [
                'name' => 'Surat HR Umum',
                'prefix' => 'IBCO/HR',
                'format' => '{{PREFIX}}/{{YEAR}}/{{SEQ:05}}',
                'reset_annually' => true,
                'is_active' => true,
            ],
        );

        foreach ($this->templates() as $template) {
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
        $common = [
            'available_variables' => [
                'employee_name', 'employee_number', 'department_name',
                'position_name', 'reference_number', 'issue_date',
                'effective_date', 'expiry_date', 'signatory_name',
                'signatory_position', 'company_name',
            ],
            'requires_approval' => true,
            'acknowledgement_required' => true,
            'confidentiality' => 'confidential',
        ];

        return [
            [
                ...$common,
                'code' => 'EMP-CONTRACT',
                'name' => 'Kontrak Perkhidmatan',
                'category' => 'contract',
                'subject_template' => 'Kontrak Perkhidmatan – {{employee_name}}',
                'body_template' => "Dengan hormatnya kami menawarkan kontrak perkhidmatan kepada {{employee_name}} bagi jawatan {{position_name}} di {{department_name}}.\n\nKontrak ini berkuat kuasa pada {{effective_date}} dan tertakluk kepada terma serta polisi syarikat yang sedang berkuat kuasa.",
            ],
            [
                ...$common,
                'code' => 'EMP-CONFIRM',
                'name' => 'Pengesahan Jawatan',
                'category' => 'confirmation',
                'subject_template' => 'Pengesahan Jawatan {{position_name}}',
                'body_template' => "Sukacita dimaklumkan bahawa perkhidmatan {{employee_name}} disahkan dalam jawatan {{position_name}} berkuat kuasa {{effective_date}}.\n\nSemoga saudara/saudari terus memberikan perkhidmatan yang cemerlang.",
            ],
            [
                ...$common,
                'code' => 'EMP-SALARY',
                'name' => 'Pelarasan Gaji',
                'category' => 'salary_revision',
                'subject_template' => 'Pelarasan Gaji Berkuat Kuasa {{effective_date}}',
                'body_template' => "Dimaklumkan bahawa pihak pengurusan telah meluluskan pelarasan gaji bagi {{employee_name}} berkuat kuasa {{effective_date}}.\n\nButiran kewangan adalah sulit dan hendaklah dirujuk bersama penyata gaji rasmi.",
                'confidentiality' => 'restricted',
            ],
            [
                ...$common,
                'code' => 'EMP-PROMOTION',
                'name' => 'Kenaikan Pangkat',
                'category' => 'promotion',
                'subject_template' => 'Kenaikan Pangkat – {{employee_name}}',
                'body_template' => "Tahniah. Pihak pengurusan meluluskan kenaikan pangkat {{employee_name}} ke jawatan {{position_name}} berkuat kuasa {{effective_date}}.\n\nKami yakin saudara/saudari akan melaksanakan tanggungjawab baharu dengan cemerlang.",
            ],
            [
                ...$common,
                'code' => 'EMP-TRANSFER',
                'name' => 'Pertukaran / Penempatan',
                'category' => 'transfer',
                'subject_template' => 'Pertukaran Penempatan Berkuat Kuasa {{effective_date}}',
                'body_template' => "Dimaklumkan bahawa {{employee_name}} akan ditempatkan di {{department_name}} sebagai {{position_name}} berkuat kuasa {{effective_date}}.\n\nSila selesaikan urusan serah tugas sebelum tarikh kuat kuasa.",
            ],
            [
                ...$common,
                'code' => 'EMP-WARNING',
                'name' => 'Surat Amaran',
                'category' => 'warning',
                'subject_template' => 'Surat Amaran – {{employee_name}}',
                'body_template' => "Surat ini merupakan amaran rasmi berhubung perkara yang telah dimaklumkan kepada {{employee_name}}.\n\nSaudara/saudari dikehendaki mengambil tindakan pembetulan serta mematuhi semua polisi organisasi.",
                'confidentiality' => 'restricted',
            ],
            [
                ...$common,
                'code' => 'EMP-SHOWCAUSE',
                'name' => 'Surat Tunjuk Sebab',
                'category' => 'show_cause',
                'subject_template' => 'Arahan Tunjuk Sebab – {{employee_name}}',
                'body_template' => "Saudara/saudari dikehendaki mengemukakan penjelasan bertulis berhubung perkara yang dinyatakan dalam surat ini dalam tempoh yang ditetapkan.\n\nKegagalan memberikan maklum balas boleh menyebabkan tindakan lanjut diambil.",
                'confidentiality' => 'restricted',
            ],
            [
                ...$common,
                'code' => 'EMP-TERMINATION',
                'name' => 'Penamatan Perkhidmatan',
                'category' => 'termination',
                'subject_template' => 'Penamatan Perkhidmatan – {{employee_name}}',
                'body_template' => "Dengan ini dimaklumkan bahawa perkhidmatan {{employee_name}} akan tamat berkuat kuasa {{effective_date}}.\n\nSaudara/saudari hendaklah menyelesaikan serah tugas, pemulangan aset dan urusan clearance sebelum tarikh akhir perkhidmatan.",
                'confidentiality' => 'restricted',
            ],
        ];
    }
}
