<?php

namespace App\Services\Documents;

use Exception;
use RuntimeException;
use Smalot\PdfParser\Parser;

class TextExtractor
{
    protected Parser $pdfParser;

    public function __construct(?Parser $pdfParser = null)
    {
        $this->pdfParser = $pdfParser ?? new Parser;
    }

    /**
     * Extract text and pages from a PDF or text file.
     *
     * @param  string  $filePath  Absolute path or path in filesystem
     * @return array<int, array{page: int, text: string}>
     */
    public function extract(string $filePath): array
    {
        if (! file_exists($filePath)) {
            throw new RuntimeException("El archivo no existe en la ruta: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return $this->extractFromPdf($filePath);
        }

        return $this->extractFromText($filePath);
    }

    /**
     * Extract page-by-page text from a PDF file.
     *
     * @return array<int, array{page: int, text: string}>
     */
    protected function extractFromPdf(string $filePath): array
    {
        try {
            $pdf = $this->pdfParser->parseFile($filePath);
            $pages = $pdf->getPages();
            $extracted = [];

            foreach ($pages as $index => $page) {
                $pageNumber = $index + 1;
                $rawText = $page->getText();
                $cleanText = $this->cleanText($rawText);

                if (! empty($cleanText)) {
                    $extracted[] = [
                        'page' => $pageNumber,
                        'text' => $cleanText,
                    ];
                }
            }

            if (empty($extracted)) {
                // Si getPages no devolvió texto por página, intentar texto global
                $globalText = $this->cleanText($pdf->getText());
                if (! empty($globalText)) {
                    $extracted[] = [
                        'page' => 1,
                        'text' => $globalText,
                    ];
                } else {
                    throw new RuntimeException('No se pudo extraer texto legible del PDF. Podría ser un documento escaneado (imagen) sin capa de texto.');
                }
            }

            return $extracted;
        } catch (Exception $e) {
            throw new RuntimeException('Error extrayendo texto del PDF: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract content from plain text / markdown.
     *
     * @return array<int, array{page: int, text: string}>
     */
    protected function extractFromText(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $clean = $this->cleanText($content);

        if (empty($clean)) {
            throw new RuntimeException('El archivo de texto está vacío.');
        }

        return [
            [
                'page' => 1,
                'text' => $clean,
            ],
        ];
    }

    /**
     * Clean and normalize text.
     */
    public function cleanText(string $text): string
    {
        // Normalizar saltos de línea
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Reemplazar múltiples saltos de línea por un doble salto
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Reemplazar tabulaciones y múltiples espacios horizontales por un solo espacio
        $text = preg_replace('/[ \t]+/', ' ', $text);

        return trim($text);
    }
}
