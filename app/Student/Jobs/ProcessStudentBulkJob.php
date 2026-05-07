<?php

namespace App\Student\Jobs;

use App\Student\Models\Student;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
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

    private function normalizeRow(array $row): array
    {
        // CSVs mal generados suelen venir como: ["a,b,c,...", "", "", ...]
        $nonEmpty = array_values(array_filter($row, static fn ($v) => trim((string)$v) !== ''));
        if (count($nonEmpty) === 1 && is_string($nonEmpty[0]) && str_contains($nonEmpty[0], ',')) {
            return array_map(static fn ($v) => trim((string)$v), str_getcsv($nonEmpty[0]));
        }

        return array_map(static fn ($v) => trim((string)$v), $row);
    }

    private function headerIndex(array $headers): array
    {
        $map = [];
        foreach ($headers as $i => $h) {
            $key = strtoupper(trim((string)$h));
            if ($key !== '') {
                $map[$key] = $i;
            }
        }
        return $map;
    }

    private function col(array $row, array $idx, string $header): ?string
    {
        $key = strtoupper($header);
        if (!isset($idx[$key])) {
            return null;
        }
        $value = $row[$idx[$key]] ?? null;
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function handle(): void
    {
        if (!Storage::exists($this->filePath)) {
            Log::error("El archivo no se guardó en el disco local: {$this->filePath}");
            return;
        }

        $fullPath = Storage::path($this->filePath);
        Log::info("Archivo de import encontrado en: " . $fullPath);

        // Convertimos el Excel/CSV a array (sheet 0)
        $rows = Excel::toArray(new \stdClass, $fullPath)[0] ?? [];
        if (count($rows) === 0) {
            Log::warning("Import vacío: {$this->filePath}");
            Storage::delete($this->filePath);
            return;
        }

        $headerRow = $this->normalizeRow((array)($rows[0] ?? []));
        $idx = $this->headerIndex($headerRow);

        // Requeridos mínimos para que coincida con database/csv/students.csv
        foreach (['FULL_NAME', 'DNI', 'STUDENT_CODE'] as $required) {
            if (!isset($idx[$required])) {
                Log::error("Encabezado requerido faltante '{$required}'. Encabezados detectados: " . implode(', ', array_keys($idx)));
                Storage::delete($this->filePath);
                return;
            }
        }

        $headerRow = $this->normalizeRow((array)($rows[0] ?? []));
        $idx = $this->headerIndex($headerRow);

        DB::transaction(function () use ($rows, $idx) {
            foreach ($rows as $index => $row) {
                $filaExcel = $index + 1;
                
                // Saltamos encabezados
                if ($index < 1) continue;

                $row = $this->normalizeRow((array)$row);

                $fullName       = $this->col($row, $idx, 'FULL_NAME');
                $documentNumber = $this->col($row, $idx, 'DNI');
                $studentCode    = $this->col($row, $idx, 'STUDENT_CODE');
                
                // Si la fila está vacía, la ignoramos
                if (empty($documentNumber) || empty($studentCode) || empty($fullName)) {
                    if (!empty($documentNumber) || !empty($studentCode)) {
                        Log::warning("Fila {$filaExcel} ignorada: Faltan datos clave (DNI, Código o Nombre).");
                    }
                    continue;
                }

                try {
                    // Resolver por DNI o por código (ambos son unique)
                    $existing = Student::where('document_number', $documentNumber)
                        ->orWhere('student_code', $studentCode)
                        ->first();

                    if ($existing && $existing->document_number !== $documentNumber && $existing->student_code === $studentCode) {
                        Log::warning("Fila {$filaExcel} conflicto: STUDENT_CODE '{$studentCode}' ya existe con otro DNI.");
                        continue;
                    }

                    $data = [
                        'document_number' => $documentNumber,
                        'student_code' => $studentCode,
                        'full_name' => $fullName,
                        'program' => $this->col($row, $idx, 'PROGRAM'),
                        'modality' => $this->col($row, $idx, 'MODALITY'),
                        'faculty' => $this->col($row, $idx, 'FACULTY'),
                        'start_semester' => $this->col($row, $idx, 'START_SEMESTER'),
                        'start_date' => $this->parseDate($this->col($row, $idx, 'START_DATE')),
                        'academic_cycle' => $this->col($row, $idx, 'ACADEMIC_CYCLE'),
                        'current_semester' => $this->col($row, $idx, 'CURRENT_SEMESTER'),
                        'graduation_semester' => $this->col($row, $idx, 'GRADUATION_SEMESTER'),
                        'graduation_date' => $this->parseDate($this->col($row, $idx, 'GRADUATION_DATE')),
                        'credits' => ($c = $this->col($row, $idx, 'CREDITS')) !== null ? (int)$c : null,
                        // Si no viene, por defecto "ACTIVO"
                        'status' => $this->col($row, $idx, 'STATUS') ?? 'ACTIVO',
                    ];

                    $student = $existing ?? new Student();
                    $student->fill($data);
                    $student->save();

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