<?php

namespace Tests\Feature;

use App\Services\RAG\RagService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagServiceTest extends TestCase
{
    public function test_generate_answer_returns_null_when_question_or_chunks_empty(): void
    {
        $rag = new RagService;

        $this->assertNull($rag->generateAnswer('', [['content' => 'algo']]));
        $this->assertNull($rag->generateAnswer('¿Pregunta?', []));
    }

    public function test_generate_answer_calls_ollama_and_formats_rag_response(): void
    {
        Http::fake([
            '*/api/generate' => Http::response([
                'model' => 'llama3.2:1b',
                'response' => 'Para dar de baja una materia debes solicitarla ante Servicios Escolares [Reglamento UTSJR, Pág. 12].',
                'done' => true,
            ], 200),
        ]);

        $rag = new RagService(host: 'http://localhost:11434', model: 'llama3.2:1b');

        $chunks = [
            [
                'document_title' => 'Reglamento UTSJR',
                'page' => 12,
                'content' => 'Artículo 45. La baja temporal de una materia se solicita por escrito.',
                'score' => 0.85,
            ],
            [
                'document_title' => 'Reglamento UTSJR',
                'page' => 14,
                'content' => 'Artículo 51. La baja definitiva requiere formato oficial.',
                'score' => 0.78,
            ],
        ];

        $result = $rag->generateAnswer('¿Cómo doy de baja una materia?', $chunks);

        $this->assertNotNull($result);
        $this->assertSame('llama3.2:1b', $result['model']);
        $this->assertStringContainsString('Servicios Escolares', $result['answer']);
        $this->assertCount(2, $result['sources']);
        $this->assertSame('Reglamento UTSJR', $result['sources'][0]['title']);
        $this->assertSame(12, $result['sources'][0]['page']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://localhost:11434/api/generate'
                && $data['model'] === 'llama3.2:1b'
                && str_contains($data['prompt'], 'Reglamento UTSJR')
                && str_contains($data['prompt'], '¿Cómo doy de baja una materia?');
        });
    }

    public function test_generate_answer_handles_ollama_failure_gracefully(): void
    {
        Http::fake([
            '*/api/generate' => Http::response(['error' => 'Model busy'], 500),
        ]);

        $rag = new RagService;

        $chunks = [
            [
                'document_title' => 'Doc Test',
                'page' => 1,
                'content' => 'Texto de prueba.',
                'score' => 0.6,
            ],
        ];

        $result = $rag->generateAnswer('¿Pregunta?', $chunks);

        $this->assertNull($result);
    }
}
