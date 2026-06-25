<?php

namespace Database\Seeders;

use App\Student\Models\Certificate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Constancia de Estudios - Pregrado',
                'code' => 'CDE-PRE',
                'sequence_suffix' => 'R',
                'sequence_start' => 1,
                'source' => database_path('fix_word/CONSTANCIA DE ESTUDIOS - PRE.docx'),
                'stored_name' => 'constancia_estudios_pre.docx',
            ],
            [
                'name' => 'Constancia de Estudio - Posgrado',
                'code' => 'CDE-POS',
                'sequence_suffix' => 'PG',
                'sequence_start' => 1,
                'source' => database_path('fix_word/CONSTANCIA DE ESTUDIO - POS.docx'),
                'stored_name' => 'constancia_estudio_pos.docx',
            ],
        ];

        foreach ($templates as $template) {
            if (!File::exists($template['source'])) {
                continue;
            }

            Storage::makeDirectory('templates');
            $storedPath = 'templates/' . $template['stored_name'];
            Storage::put($storedPath, File::get($template['source']));

            Certificate::updateOrCreate(
                ['code' => $template['code']],
                [
                    'name' => $template['name'],
                    'sequence_suffix' => $template['sequence_suffix'],
                    'sequence_start' => $template['sequence_start'],
                    'file_name' => $template['stored_name'],
                    'file_path' => $storedPath,
                ]
            );
        }
    }
}
