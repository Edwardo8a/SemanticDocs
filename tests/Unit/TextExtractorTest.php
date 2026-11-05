<?php

namespace Tests\Unit;

use App\Services\Documents\TextExtractor;
use PHPUnit\Framework\TestCase;

class TextExtractorTest extends TestCase
{
    public function test_cleans_excessive_whitespace_and_newlines(): void
    {
        $extractor = new TextExtractor;
        $dirtyText = "Línea 1   con     espacios.\r\n\r\n\r\n\r\nLínea 2.\t\tTabulado.";

        $cleaned = $extractor->cleanText($dirtyText);

        $this->assertStringNotContainsString("\r", $cleaned);
        $this->assertStringNotContainsString('   ', $cleaned);
        $this->assertStringContainsString('Línea 1 con espacios.', $cleaned);
        $this->assertStringContainsString('Línea 2. Tabulado.', $cleaned);
    }

    public function test_extracts_from_plain_text_file(): void
    {
        $extractor = new TextExtractor;
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_doc_');
        file_put_contents($tmpFile, 'Contenido de prueba para extracción.');

        try {
            $pages = $extractor->extract($tmpFile);

            $this->assertCount(1, $pages);
            $this->assertSame(1, $pages[0]['page']);
            $this->assertSame('Contenido de prueba para extracción.', $pages[0]['text']);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }
}
