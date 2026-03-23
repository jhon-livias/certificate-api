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
                
                // Saltamos SOLO la fila 1 porque ahora es una plantilla limpia con encabezados
                if ($index < 1) continue;

                // --- MAPEO DE COLUMNAS (Ajusta los números según tu template.xlsx) ---
                // 0 = Columna A, 1 = Columna B, 2 = Columna C, etc.
                
                $documentNumber = trim((string)($row[0] ?? '')); // DNI
                $studentCode    = trim((string)($row[1] ?? '')); // Código
                $fullName       = trim((string)($row[2] ?? '')); // Nombre Completo
                
                // Si la fila está vacía, la ignoramos
                if (empty($documentNumber) || empty($studentCode) || empty($fullName)) {
                    if (!empty($documentNumber) || !empty($studentCode)) {
                        Log::warning("Fila {$filaExcel} ignorada: Faltan datos clave (DNI, Código o Nombre).");
                    }
                    continue;
                }

                try {
                    Student::updateOrCreate(
                        ['document_number' => $documentNumber], // Busca por DNI
                        [
                            'student_code'    => $studentCode,
                            'full_name'       => $fullName,
                            'gender'          => strtoupper(trim((string)($row[3] ?? ''))),
                            'email'           => trim((string)($row[4] ?? '')),
                            'phone'           => trim((string)($row[5] ?? '')),
                            'address'         => trim((string)($row[6] ?? '')),
                            'admission_mode'  => trim((string)($row[7] ?? '')),
                            'program'         => trim((string)($row[8] ?? '')),
                            'campus'          => trim((string)($row[9] ?? '')),
                            'modality'        => trim((string)($row[10] ?? '')),
                            'shift'           => trim((string)($row[11] ?? '')),
                            'status'          => trim((string)($row[12] ?? '')),
                            'graduation_year' => trim((string)($row[13] ?? '')),
                        ]
                    );

                    // Limpiamos la caché de este estudiante si existía
                    Cache::forget("student_{$studentCode}");

                } catch (QueryException $e) {
                    if ($e->getCode() == '23505') {
                        Log::warning("Fila {$filaExcel} conflicto: Código '{$studentCode}' duplicado.");
                    } else {
                        Log::error("Fila {$filaExcel} ERROR SQL: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error("Fila {$filaExcel} ERROR GENERAL: " . $e->getMessage());
                }
            }
        });

        // Borramos el archivo temporal cuando termina
        Storage::delete($this->filePath);
    }
}