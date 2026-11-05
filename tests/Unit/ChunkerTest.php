<?php

namespace Tests\Unit;

use App\Services\Documents\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function test_chunks_short_text_as_single_chunk(): void
    {
        $chunker = new Chunker(targetWords: 100, overlapWords: 10);
        $pages = [
            [
                'page' => 1,
                'text' => 'Este es un texto corto que no debe dividirse.',
            ],
        ];

        $chunks = $chunker->chunkPages($pages);

        $this->assertCount(1, $chunks);
        $this->assertSame(1, $chunks[0]['page']);
        $this->assertSame(0, $chunks[0]['chunk_index']);
        $this->assertSame('Este es un texto corto que no debe dividirse.', $chunks[0]['text']);
    }

    public function test_chunks_long_text_with_overlap(): void
    {
        $chunker = new Chunker(targetWords: 10, overlapWords: 2);
        // Generar 25 palabras
        $words = range(1, 25);
        $text = implode(' ', array_map(fn ($n) => "palabra{$n}", $words));

        $pages = [
            [
                'page' => 1,
                'text' => $text,
            ],
        ];

        $chunks = $chunker->chunkPages($pages);

        $this->assertGreaterThan(1, count($chunks));
        $this->assertSame(0, $chunks[0]['chunk_index']);
        $this->assertSame(1, $chunks[1]['chunk_index']);

        // Verificar que el traslape existe entre el final del chunk 0 y el inicio del chunk 1
        $this->assertStringContainsString('palabra9', $chunks[0]['text']);
        $this->assertStringContainsString('palabra9', $chunks[1]['text']);
    }

    public function test_handles_empty_pages_gracefully(): void
    {
        $chunker = new Chunker;
        $pages = [
            ['page' => 1, 'text' => ''],
            ['page' => 2, 'text' => '    '],
        ];

        $chunks = $chunker->chunkPages($pages);

        $this->assertEmpty($chunks);
    }
}
