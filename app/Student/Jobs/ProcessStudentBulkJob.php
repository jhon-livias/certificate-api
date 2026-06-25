<?php

namespace App\Student\Jobs;

use App\Student\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

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
        Log::info("Archivo de importación encontrado en: {$fullPath}");

        $rows = Excel::toArray(new \stdClass, $fullPath)[0] ?? [];

        if (empty($rows)) {
            Log::warning('El archivo de importación no contiene filas.');
            Storage::delete($this->filePath);
            return;
        }

        $normalizedRows = array_map(fn (array $row) => $this->normalizeRow($row), $rows);
        $columnMap = $this->resolveColumnMap($normalizedRows[0]);

        DB::transaction(function () use ($normalizedRows, $columnMap) {
            foreach ($normalizedRows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                $studentData = $this->mapRowToStudent($row, $columnMap);
                $documentNumber = $studentData['document_number'] ?? '';

                if ($documentNumber === '') {
                    Log::warning('Fila ' . ($index + 1) . ' ignorada: falta document_number.');
                    continue;
                }

                try {
                    Student::updateOrCreate(
                        ['document_number' => $documentNumber],
                        $studentData
                    );

                    Cache::forget("student_{$documentNumber}");
                } catch (QueryException $e) {
                    if ($e->getCode() == '23505') {
                        Log::warning('Fila ' . ($index + 1) . " conflicto: document_number '{$documentNumber}' duplicado.");
                    } else {
                        Log::error('Fila ' . ($index + 1) . ' ERROR SQL: ' . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error('Fila ' . ($index + 1) . ' ERROR GENERAL: ' . $e->getMessage());
                }
            }
        });

        Storage::delete($this->filePath);
    }

    private function normalizeRow(array $row): array
    {
        if (count($row) === 1 && is_string($row[0]) && str_contains($row[0], ',')) {
            return str_getcsv($row[0]);
        }

        return $row;
    }

    private function resolveColumnMap(array $headerRow): array
    {
        $headers = array_map(
            fn ($value) => strtoupper(trim((string) $value)),
            $headerRow
        );

        $find = function (array $candidates) use ($headers): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $headers, true);
                if ($index !== false) {
                    return $index;
                }
            }

            return null;
        };

        return [
            'student_code' => $find(['STUDENT_CODE', 'CODIGO', 'CODIGO ALUMNO', 'CODIGO ESTUDIANTE']),
            'document_number' => $find(['DOCUMENT_NUMBER', 'DNI', 'NUMERO DOCUMENTO', 'NRO DOCUMENTO']),
            'full_name' => $find(['FULL_NAME', 'NOMBRE COMPLETO', 'APELLIDOS Y NOMBRES', 'APELLIDOS Y NOMBRE', 'NOMBRE']),
            'program' => $find(['PROGRAM', 'PROGRAMA', 'CARRERA', 'PROGRAMA / CARRERA']),
            'email' => $find(['EMAIL', 'CORREO', 'CORREO ELECTRONICO']),
            'modality' => $find(['MODALITY', 'MODALIDAD']),
            'faculty' => $find(['FACULTY', 'FACULTAD']),
            'gender' => $find(['GENDER', 'SEXO', 'GENERO']),
            'phone' => $find(['PHONE', 'TELEFONO', 'CELULAR']),
            'address' => $find(['ADDRESS', 'DIRECCION']),
            'admission_mode' => $find(['ADMISSION_MODE', 'MODALIDAD DE INGRESO', 'MODO DE ADMISION']),
            'status' => $find(['STATUS', 'ESTADO']),
            'academic_cycle' => $find(['CYCLE', 'ACADEMIC_CYCLE', 'CICLO ACADEMICO', 'CICLO', 'PERIODO']),
            'current_semester' => $find(['CURRENT_SEMESTER', 'SEMESTRE', 'SEMESTRE LECTIVO', 'SEMESTRE_LECTIVO']),
            'graduation_date' => $find(['GRADUATION_DATE', 'FECHA DE EGRESO', 'ANIO DE EGRESO']),
        ];
    }

    private function mapRowToStudent(array $row, array $columnMap): array
    {
        $value = fn (?int $index) => $index === null ? '' : trim((string) ($row[$index] ?? ''));

        return array_filter([
            'student_code' => $value($columnMap['student_code']),
            'document_number' => $value($columnMap['document_number']),
            'full_name' => $value($columnMap['full_name']),
            'program' => $value($columnMap['program']),
            'email' => $value($columnMap['email']),
            'modality' => $value($columnMap['modality']),
            'faculty' => $value($columnMap['faculty']),
            'gender' => $value($columnMap['gender']),
            'phone' => $value($columnMap['phone']),
            'address' => $value($columnMap['address']),
            'admission_mode' => $value($columnMap['admission_mode']),
            'status' => $value($columnMap['status']) ?: 'Activo',
            'academic_cycle' => $value($columnMap['academic_cycle']),
            'current_semester' => $value($columnMap['current_semester']),
            'graduation_date' => $value($columnMap['graduation_date']),
        ], fn ($fieldValue) => $fieldValue !== '');
    }
}
