<?php

namespace App\Services\RAG;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagService
{
    protected string $host;

    protected string $model;

    protected int $timeout;

    public function __construct(?string $host = null, ?string $model = null, ?int $timeout = null)
    {
        $this->host = rtrim($host ?: config('services.ollama.host', 'http://localhost:11434'), '/');
        $this->model = $model ?: config('services.ollama.rag_model', 'llama3.2:1b');
        $this->timeout = $timeout ?: (int) config('services.ollama.timeout', 45);
    }

    /**
     * Generate an answer based strictly on retrieved document chunks.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array{answer: string, model: string, sources: array<int, array{title: string, page: int|string, score: float}>, duration_ms: ?float}|null
     */
    public function generateAnswer(string $question, array $chunks, int $maxChunks = 4): ?array
    {
        $cleanQuestion = trim($question);
        if ($cleanQuestion === '' || empty($chunks)) {
            return null;
        }

        $selectedChunks = array_slice($chunks, 0, $maxChunks);
        $context = $this->buildContext($selectedChunks);
        $prompt = $this->buildPrompt($cleanQuestion, $context);

        $sources = array_map(function (array $chunk) {
            return [
                'title' => $chunk['document_title'] ?? 'Documento',
                'page' => $chunk['page'] ?? 1,
                'score' => round((float) ($chunk['score'] ?? 0), 4),
            ];
        }, $selectedChunks);

        try {
            $startTime = microtime(true);

            $response = Http::timeout($this->timeout)->post("{$this->host}/api/generate", [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => 0.2,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning("Ollama RAG error ({$response->status()}): {$response->body()}");

                return null;
            }

            $data = $response->json();
            $answer = trim($data['response'] ?? '');

            if ($answer === '') {
                return null;
            }

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'answer' => $answer,
                'model' => $this->model,
                'sources' => $sources,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar respuesta RAG con Ollama: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Build the context string from retrieved chunks.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     */
    protected function buildContext(array $chunks): string
    {
        $sections = [];

        foreach ($chunks as $index => $chunk) {
            $num = $index + 1;
            $title = $chunk['document_title'] ?? 'Documento';
            $page = $chunk['page'] ?? 1;
            $content = trim($chunk['content'] ?? '');

            $sections[] = "[Fragmento {$num}] Documento: {$title} (Pág. {$page})\n{$content}";
        }

        return implode("\n\n---\n\n", $sections);
    }

    /**
     * Build the system and user prompt in Spanish.
     */
    protected function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
Eres el asistente de búsqueda documental inteligente de SemanticDocs.
Tu tarea es responder la pregunta del usuario utilizando estricta y exclusivamente los fragmentos de contexto proporcionados abajo.

Reglas obligatorias:
1. Responde en español de manera clara, concisa y estructurada.
2. Fundamenta tu respuesta citando el nombre del documento y número de página de donde obtuviste la información (ejemplo: "[Reglamento UTSJR, Pág. 8]").
3. Si los fragmentos no contienen la información para responder la pregunta, di textualmente: "La información solicitada no se encuentra disponible en los documentos consultados."
4. No asumas ni inventes datos que no figuren en el contexto.

=== CONTEXTO DISPONIBLE ===
{$context}
=== FIN CONTEXTO ===

Pregunta del usuario: {$question}

Respuesta fundamentada:
PROMPT;
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
