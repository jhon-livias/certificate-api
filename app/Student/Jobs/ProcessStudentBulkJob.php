<?php

namespace App\Student\Jobs;

use App\Student\Models\Student;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;

class ProcessStudentBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $filePath) {}

    public function handle(): void
    {
        if (!Storage::exists($this->filePath)) {
            Log::error("El archivo no se guardó en el disco local: {$this->filePath}");
            return;
        }

        $fullPath = Storage::path($this->filePath);
        Log::info("Excel encontrado exitosamente en: " . $fullPath);

        // Convertimos el Excel a array
        $rows = Excel::toArray(new \stdClass, $fullPath)[0];

        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $filaExcel = $index + 1;

                if ($index < 1) continue;

                $dni = trim((string)($row[2] ?? ''));

                if (empty($dni)) {
                    if (!empty($dni)) {
                        Log::warning("Fila {$filaExcel} ignorada: Faltan datos clave (DNI).");
                    }
                    continue;
                }

                try {
                    Student::updateOrCreate(
                        ['dni' => $dni],
                        [
                            'name'          => trim((string)($row[0] ?? '')),
                            'surname'       => trim((string)($row[1] ?? '')),
                            'program_type'  => trim((string)($row[3] ?? '')),
                            'program'       => trim((string)($row[4] ?? '')),
                            'period'        => trim((string)($row[5] ?? '')),
                            'email'         => trim((string)($row[6] ?? '')),
                            'status'        => trim((string)($row[7] ?? '')),
                        ]
                    );

                    Cache::forget("student_{$dni}");
                } catch (QueryException $e) {
                    if ($e->getCode() == '23505') {
                        Log::warning("Fila {$filaExcel} conflicto: DNI '{$dni}' duplicado.");
                    } else {
                        Log::error("Fila {$filaExcel} ERROR SQL: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error("Fila {$filaExcel} ERROR GENERAL: " . $e->getMessage());
                }
            }
        });

        Storage::delete($this->filePath);
    }
}
