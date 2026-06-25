<?php

namespace Database\Seeders;

use App\Student\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StudentCsvSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('csv/students.csv');

        if (!File::exists($csvPath)) {
            return;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return;
        }

        $headers = array_map(function ($value) {
            $normalized = strtolower(trim((string) $value));

            return ltrim($normalized, "\xEF\xBB\xBF");
        }, fgetcsv($handle) ?: []);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, array_map(
                fn ($value) => trim((string) $value),
                $row
            ));

            $documentNumber = $data['document_number'] ?? '';
            if ($documentNumber === '') {
                continue;
            }

            unset($data['document_number']);

            Student::updateOrCreate(
                ['document_number' => $documentNumber],
                array_merge($data, ['status' => $data['status'] ?? 'Activo'])
            );
        }

        fclose($handle);
    }
}
