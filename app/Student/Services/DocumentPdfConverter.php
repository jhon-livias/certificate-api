<?php

namespace App\Student\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentPdfConverter
{
    public function convertDocx(string $docxRelativePath): string
    {
        $absoluteDocxPath = Storage::path($docxRelativePath);

        if (!is_file($absoluteDocxPath)) {
            throw new RuntimeException('No se encontró el documento Word temporal para convertir.');
        }

        app(DocxPdfPreprocessor::class)->prepare($absoluteDocxPath);

        $outdir = Storage::path('generated_certificates');
        $pdfRelativePath = preg_replace('/\.docx$/i', '.pdf', $docxRelativePath);
        $expectedPdfPath = Storage::path($pdfRelativePath);

        if (is_file($expectedPdfPath)) {
            @unlink($expectedPdfPath);
        }

        $soffice = $this->resolveLibreOfficeBinary();
        $command = sprintf(
            '"%s" --headless --nologo --nofirststartwizard --convert-to pdf --outdir "%s" "%s"',
            $soffice,
            $outdir,
            $absoluteDocxPath
        );

        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0 || !is_file($expectedPdfPath)) {
            Log::error('LibreOffice PDF conversion failed', [
                'command' => $command,
                'output' => $output,
                'return_var' => $returnVar,
            ]);

            throw new RuntimeException(
                'No se pudo convertir la constancia a PDF. Instale LibreOffice o configure LIBREOFFICE_PATH en .env.'
            );
        }

        return $pdfRelativePath;
    }

    private function resolveLibreOfficeBinary(): string
    {
        $configured = env('LIBREOFFICE_PATH');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            ]
            : [
                '/usr/bin/soffice',
                '/usr/local/bin/soffice',
                '/snap/bin/libreoffice',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $which = PHP_OS_FAMILY === 'Windows' ? 'where soffice 2>nul' : 'which soffice 2>/dev/null';
        $resolved = trim((string) shell_exec($which));

        if ($resolved !== '') {
            $firstLine = strtok($resolved, PHP_EOL);
            if ($firstLine && is_file($firstLine)) {
                return $firstLine;
            }
        }

        throw new RuntimeException(
            'LibreOffice no está instalado. Descárguelo desde https://www.libreoffice.org o defina LIBREOFFICE_PATH en .env.'
        );
    }
}
