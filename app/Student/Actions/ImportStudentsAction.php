<?php

namespace App\Student\Actions;

use App\Student\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class ImportStudentsAction
{
    public function execute($uploadedFile): void
    {
        $rows = Excel::toArray(new \stdClass, $uploadedFile)[0];

        // Usamos transacción para asegurar la integridad
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                // Sumamos 1 al índice para que el Log te dé la fila exacta en Excel
                $filaExcel = $index + 1;

                // Saltamos las 6 primeras filas (membrete, logos, cabeceras)
                if ($index < 6) continue;

                $documentNumber = trim((string)($row[5] ?? ''));
                $fullName = trim($row[2] ?? '');

                // Validación básica de fila vacía
                if (empty($documentNumber) || empty($fullName)) continue;

                // Capturamos el código (Alumno o Postulante)
                $studentCode = trim(!empty($row[35]) ? (string)$row[35] : (string)($row[8] ?? ''));

                // Si no tiene ningún código, PostgreSQL nos dará error. Lo logueamos y saltamos.
                if (empty($studentCode)) {
                    Log::warning("Fila {$filaExcel} ignorada: El alumno {$fullName} (DNI: {$documentNumber}) no tiene Código asignado en el Excel.");
                    continue;
                }

                try {
                    Student::updateOrCreate(
                        ['document_number' => $documentNumber],
                        [
                            'student_code'    => $studentCode,
                            'full_name'       => $fullName,
                            'gender'          => strtoupper(trim($row[3] ?? '')),
                            'email'           => trim($row[11] ?? ''),
                            'phone'           => trim($row[10] ?? ''),
                            'address'         => trim($row[6] ?? ''),
                            'admission_mode'  => trim($row[1] ?? ''),
                            'program'         => trim($row[20] ?? ''),
                            'campus'          => trim($row[23] ?? ''),
                            'modality'        => trim($row[24] ?? ''),
                            'shift'           => trim($row[25] ?? ''),
                            'status'          => trim($row[26] ?? ''),
                            'graduation_year' => trim($row[37] ?? ''),
                        ]
                    );
                } catch (QueryException $e) {
                    // 23505 es el código SQLSTATE para violaciones Unique en PostgreSQL
                    if ($e->getCode() == '23505') {
                        Log::error("Fila {$filaExcel} conflicto: El código '{$studentCode}' ya pertenece a otro alumno en la BD. Alumno actual: {$fullName}");
                    } else {
                        Log::error("Fila {$filaExcel} Error BD ({$fullName}): " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    Log::error("Fila {$filaExcel} Error General ({$fullName}): " . $e->getMessage());
                }
            }
        });
    }
}
