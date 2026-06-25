<?php

namespace App\Student\Services;

use App\Student\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class WordDocumentService
{
    public function fillStudentCertificate(
        string $templatePath,
        Student $student,
        string $certificateCode,
        array $extraReplacements = [],
    ): string {
        $replacements = array_merge([
            'APELLIDOS_Y_NOMBRES' => $student->full_name ?? '',
            'DNI' => $student->document_number ?? '',
            'CODIGO' => $student->student_code ?? '',
            'NOMBRE_DE_CARRERAPROGRAMA' => $student->program ?? '',
            'MODALIDAD' => $student->modality ?? '',
            'FACULTAD' => $student->faculty ?? '',
            'CICLO' => $this->formatCycle($student),
            'SEMESTRE_LECTIVO' => $this->formatSemester($student),
        ], $extraReplacements);

        $outputRelativePath = 'generated_certificates/CONSTANCIA_' . ($student->document_number ?? 'SN') . '_' . time() . '.docx';
        Storage::makeDirectory('generated_certificates');

        $absoluteOutputPath = Storage::path($outputRelativePath);
        copy($templatePath, $absoluteOutputPath);

        $zip = new ZipArchive();
        $zip->open($absoluteOutputPath);
        $xml = $zip->getFromName('word/document.xml');

        foreach ($replacements as $field => $value) {
            $xml = $this->replaceMergeField($xml, $field, $value);
        }

        $xml = $this->replaceCertificateCode($xml, $certificateCode);
        $xml = $this->replaceIssueDate($xml);

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $outputRelativePath;
    }

    public function convertToPdf(string $docxRelativePath, DocumentPdfConverter $converter): string
    {
        return $converter->convertDocx($docxRelativePath);
    }

    public function buildPreview(string $templatePath, string $certificateCode): string
    {
        $sampleData = [
            'APELLIDOS_Y_NOMBRES' => 'APELLIDOS Y NOMBRES DEL ESTUDIANTE',
            'DNI' => '12345678',
            'CODIGO' => '2026011125',
            'NOMBRE_DE_CARRERAPROGRAMA' => 'Nombre de la Carrera o Programa',
            'MODALIDAD' => 'Presencial',
            'FACULTAD' => 'Nombre de la Facultad',
            'CICLO' => '8',
            'SEMESTRE_LECTIVO' => '2026-I',
        ];

        $outputRelativePath = 'generated_certificates/PREVIEW_' . time() . '.docx';
        Storage::makeDirectory('generated_certificates');

        $absoluteOutputPath = Storage::path($outputRelativePath);
        copy($templatePath, $absoluteOutputPath);

        $zip = new ZipArchive();
        $zip->open($absoluteOutputPath);
        $xml = $zip->getFromName('word/document.xml');

        foreach ($sampleData as $field => $value) {
            $xml = $this->replaceMergeField($xml, $field, $value);
        }

        $xml = $this->replaceCertificateCode($xml, $certificateCode);
        $xml = $this->replaceIssueDate($xml);

        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        return $outputRelativePath;
    }

    private function replaceMergeField(string $xml, string $field, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return str_replace('«' . $field . '»', $escaped, $xml);
    }

    private function replaceCertificateCode(string $xml, string $certificateCode): string
    {
        $escaped = htmlspecialchars($certificateCode, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = preg_replace(
            '/(<w:t[^>]*>)CDE N° 0000(<\/w:t>)(\s*<\/w:r>\s*<w:r[^>]*>\s*<w:rPr>.*?<\/w:rPr>\s*<w:t[^>]*>)0(<\/w:t>)(\s*<\/w:r>\s*<w:r[^>]*>\s*<w:rPr>.*?<\/w:rPr>\s*<w:t[^>]*>) - 2026 R\.A\.\/UPRIT - R (<\/w:t>)/su',
            '$1' . $escaped . '$6',
            $xml,
            1
        ) ?? $xml;

        $xml = preg_replace(
            '/CDE N° \d+ - 2026 R\.A\.\/UPRIT [–\-] (R|PG)/u',
            $escaped,
            $xml,
            1
        ) ?? $xml;

        return $xml;
    }

    private function replaceIssueDate(string $xml): string
    {
        Carbon::setLocale('es');

        $day = htmlspecialchars(now()->format('j'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $month = htmlspecialchars(ucfirst(now()->translatedFormat('F')), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $year = htmlspecialchars(now()->format('Y'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return preg_replace_callback(
            '/<w:p\b[^>]*>(?=.*<w:jc w:val="right"\/>)(?:(?!<\/w:p>).)*<w:t[^>]*>Trujillo, <\/w:t>(?:(?!<\/w:p>).)*<\/w:p>/su',
            function (array $match) use ($day, $month, $year): string {
                $paragraph = $match[0];

                $paragraph = preg_replace(
                    '/(<w:t[^>]*>)xx(<\/w:t>)/u',
                    '${1}' . $day . '${2}',
                    $paragraph,
                    1
                ) ?? $paragraph;

                $paragraph = preg_replace(
                    '/(<w:t[^>]*>)x{4,5}(<\/w:t>)/u',
                    '${1}' . $month . '${2}',
                    $paragraph,
                    1
                ) ?? $paragraph;

                $paragraph = preg_replace(
                    '/(<w:t[^>]*>) del 202(<\/w:t>)(\s*<\/w:r>\s*<w:r[^>]*>\s*<w:rPr>.*?<\/w:rPr>\s*<w:t[^>]*>)x(<\/w:t>)/su',
                    '${1} del ' . $year . '${2}',
                    $paragraph,
                    1
                ) ?? $paragraph;

                return $paragraph;
            },
            $xml,
            1
        ) ?? $xml;
    }

    private function formatSemester(Student $student): string
    {
        return $student->current_semester
            ?? $student->start_semester
            ?? $student->academic_cycle
            ?? '';
    }

    private function formatCycle(Student $student): string
    {
        $value = (string) ($student->academic_cycle ?? $student->current_semester ?? '');

        if (preg_match('/^(\d+)/', $value, $match)) {
            return $match[1];
        }

        if (preg_match('/^([IVXLC]+)/i', $value, $match)) {
            return strtoupper($match[1]);
        }

        return $value;
    }
}
