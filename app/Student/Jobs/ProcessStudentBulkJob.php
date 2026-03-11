<?php

namespace App\Student\Jobs;

use App\Student\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStudentBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $rows;

    /**
     * Create a new job instance.
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Filas recibidas en el Job: ' . count($this->rows));
        $studentsBatch = [];

        foreach ($this->rows as $index => $row) {

            // Solución al problema del CSV
            if (count($row) === 1 && is_string($row[0]) && str_contains($row[0], ',')) {
                $row = str_getcsv($row[0]);
            }

            // Saltamos cabecera y filas vacías
            if ($index === 0 || empty(array_filter($row))) {
                continue;
            }

            $studentCode = $row[0] ?? null;

            if (empty($studentCode)) {
                continue;
            }

            // Armamos el array con TODOS los registros, incluyendo duplicados
            $studentsBatch[] = [
                'student_code'    => $studentCode,
                'document_number' => $row[1] ?? null,
                'full_name'       => $row[2] ?? null,
                'program'         => $row[3] ?? null,
                'email'           => $row[4] ?? null,
            ];
        }

        if (empty($studentsBatch)) {
            return;
        }

        // Insertamos TODO sin importar si hay duplicados
        foreach (array_chunk($studentsBatch, 100) as $chunk) {
            Student::insert($chunk);
        }
    }
}
