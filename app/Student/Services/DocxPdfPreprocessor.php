<?php

namespace App\Student\Services;

use ZipArchive;

class DocxPdfPreprocessor
{
    private const XML_PARTS = [
        'word/document.xml',
        'word/header1.xml',
        'word/header2.xml',
        'word/header3.xml',
        'word/footer1.xml',
        'word/footer2.xml',
        'word/footer3.xml',
    ];

    private const SIGNATURE_BG = [243, 255, 255];

    public function prepare(string $absoluteDocxPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($absoluteDocxPath) !== true) {
            return;
        }

        $signatureMediaPath = $this->findSignatureMediaPath($zip);
        if ($signatureMediaPath !== null) {
            $pngData = $zip->getFromName($signatureMediaPath);
            if ($pngData !== false) {
                $processed = $this->makeSignatureBackgroundTransparent($pngData);
                if ($processed !== null) {
                    $zip->addFromString($signatureMediaPath, $processed);
                }
            }
        }

        foreach (self::XML_PARTS as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            $zip->addFromString($part, $this->fixSignatureBlockLayout($this->sanitizeXml($xml)));
        }

        $zip->close();
    }

    public function sanitizeXml(string $xml): string
    {
        return preg_replace_callback(
            '/<a:blip(\b[^>]*)>(.*?)<\/a:blip>/su',
            function (array $match): string {
                $inner = $match[2];

                if (!str_contains($inner, 'imgLayer') && !str_contains($inner, 'clrChange')) {
                    return $match[0];
                }

                $inner = preg_replace('/<a:extLst>.*?<\/a:extLst>/su', '', $inner) ?? $inner;

                if ($this->isSignatureBlip($inner)) {
                    $inner = preg_replace('/<a:clrChange>.*?<\/a:clrChange>/su', '', $inner) ?? $inner;
                    $inner = preg_replace('/<a:biLevel\b[^>]*\/>/u', '', $inner) ?? $inner;

                    return '<a:blip' . $match[1] . '>' . $inner . '</a:blip>';
                }

                $inner = preg_replace('/<a:clrChange>.*?<\/a:clrChange>/su', '', $inner) ?? $inner;

                if (!str_contains($inner, '<a:biLevel')) {
                    $inner = '<a:biLevel thresh="75000"/>' . $inner;
                }

                return '<a:blip' . $match[1] . '>' . $inner . '</a:blip>';
            },
            $xml
        ) ?? $xml;
    }

    private function findSignatureMediaPath(ZipArchive $zip): ?string
    {
        $xml = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');

        if ($xml === false || $rels === false) {
            return null;
        }

        preg_match_all('/<a:blip(\b[^>]*)>(.*?)<\/a:blip>/su', $xml, $blips, PREG_SET_ORDER);

        foreach ($blips as $blip) {
            if (!$this->isSignatureBlip($blip[2])) {
                continue;
            }

            if (!preg_match('/r:embed="(rId\d+)"/', $blip[1], $idMatch)) {
                continue;
            }

            if (preg_match('/Id="' . $idMatch[1] . '".*?Target="([^"]+)"/', $rels, $targetMatch)) {
                return 'word/' . ltrim($targetMatch[1], '/');
            }
        }

        return null;
    }

    private function makeSignatureBackgroundTransparent(string $pngData): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($pngData);
        if ($image === false) {
            return null;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($this->isBackgroundPixel($red, $green, $blue)) {
                    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }

        ob_start();
        imagepng($image);
        $output = ob_get_clean();
        imagedestroy($image);

        return $output ?: null;
    }

    private function isBackgroundPixel(int $red, int $green, int $blue): bool
    {
        return abs($red - self::SIGNATURE_BG[0]) <= 18
            && abs($green - self::SIGNATURE_BG[1]) <= 18
            && abs($blue - self::SIGNATURE_BG[2]) <= 18;
    }

    private function isSignatureBlip(string $inner): bool
    {
        return (bool) preg_match('/<a:srgbClr val="F3FFFF"/', $inner);
    }

    private function fixSignatureBlockLayout(string $xml): string
    {
        $xml = preg_replace_callback(
            '/<wp:anchor\b[^>]*behindDoc="0"[^>]*>(?:(?!<\/wp:anchor>).)*<a:biLevel(?:(?!<\/wp:anchor>).)*<\/wp:anchor>/su',
            function (array $match): string {
                return str_replace('behindDoc="0"', 'behindDoc="1"', $match[0]);
            },
            $xml
        ) ?? $xml;

        return preg_replace(
            '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*(?:<v:shape id="AutoShape 2"|<v:line\b)(?:(?!<\/w:p>).)*<\/w:p>\s*'
            . '<w:p\b[^>]*>(?:(?!<\/w:p>).)*Lic\. Ruth Elsa Gutiérrez Cortez(?:(?!<\/w:p>).)*<\/w:p>\s*'
            . '<w:p\b[^>]*>(?:(?!<\/w:p>).)*Director de Registro Académico(?:(?!<\/w:p>).)*<\/w:p>\s*'
            . '<w:p\b[^>]*>(?:(?!<\/w:p>).)*Universidad Privada de Trujillo(?:(?!<\/w:p>).)*<\/w:p>/su',
            $this->buildCenteredSignatureTable(),
            $xml,
            1
        ) ?? $xml;
    }

    private function buildCenteredSignatureTable(): string
    {
        $tableWidth = 4140;
        $tableIndent = (int) ((8838 - $tableWidth) / 2);
        $runProperties = '<w:rFonts w:ascii="Arial" w:eastAsia="Calibri" w:hAnsi="Arial" w:cs="Arial"/>'
            . '<w:b/><w:sz w:val="20"/><w:szCs w:val="20"/><w:lang w:val="es-ES"/>';

        $line = '<w:tr><w:tc><w:tcPr><w:tcW w:w="' . $tableWidth . '" w:type="dxa"/>'
            . '<w:tcBorders><w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>'
            . '<w:top w:val="nil"/><w:left w:val="nil"/><w:right w:val="nil"/></w:tcBorders></w:tcPr>'
            . '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="atLeast"/></w:pPr></w:p></w:tc></w:tr>';

        $textLine = static fn (string $text): string => '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="atLeast"/>'
            . '<w:jc w:val="center"/></w:pPr><w:r><w:rPr>' . $runProperties . '</w:rPr><w:t>'
            . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';

        $textRows = $textLine('Lic. Ruth Elsa Gutiérrez Cortez')
            . $textLine('Director de Registro Académico')
            . $textLine('Universidad Privada de Trujillo');

        return '<w:tbl><w:tblPr><w:tblW w:w="' . $tableWidth . '" w:type="dxa"/>'
            . '<w:tblInd w:w="' . $tableIndent . '" w:type="dxa"/>'
            . '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/>'
            . '<w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="' . $tableWidth . '"/></w:tblGrid>'
            . $line
            . '<w:tr><w:tc><w:tcPr><w:tcW w:w="' . $tableWidth . '" w:type="dxa"/></w:tcPr>'
            . $textRows
            . '</w:tc></w:tr></w:tbl>';
    }
}
