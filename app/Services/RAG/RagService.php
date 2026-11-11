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
        $this->timeout = $timeout ?: (int) config('services.ollama.timeout', 25);
    }

    /**
     * Generate an answer based strictly on retrieved document chunks using Ollama chat endpoint.
     *
     * @param  array<int, array<string, mixed>>  $chunks
     * @return array{answer: string, model: string, sources: array<int, array{title: string, page: int|string, score: float}>, duration_ms: ?float}|null
     */
    public function generateAnswer(string $question, array $chunks, int $maxChunks = 3): ?array
    {
        $cleanQuestion = trim($question);
        if ($cleanQuestion === '' || empty($chunks)) {
            return null;
        }

        $selectedChunks = array_slice($chunks, 0, $maxChunks);
        $context = $this->buildContext($selectedChunks);

        $systemPrompt = 'Eres el asistente de búsqueda documental de SemanticDocs. Responde a la pregunta del usuario en español de forma clara, directa y fundamentada basándote en los fragmentos de documentos provistos. Cita el documento y la página correspondiente. Si la información no se encuentra en los fragmentos, indícalo de manera amable.';

        $userMessage = "Fragmentos de contexto:\n{$context}\n\nPregunta: {$cleanQuestion}\n\nRespuesta estructurada:";

        $sources = array_map(function (array $chunk) {
            return [
                'title' => $chunk['document_title'] ?? 'Documento',
                'page' => $chunk['page'] ?? 1,
                'score' => round((float) ($chunk['score'] ?? 0), 4),
            ];
        }, $selectedChunks);

        try {
            $startTime = microtime(true);

            $response = Http::timeout($this->timeout)->post("{$this->host}/api/chat", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'stream' => false,
                'options' => [
                    'temperature' => 0.2,
                    'num_predict' => 250,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning("Ollama RAG error ({$response->status()}): {$response->body()}");

                return null;
            }

            $data = $response->json();
            $answer = trim($data['message']['content'] ?? '');

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

            // Acotar longitud por fragmento para evitar saturar contexto en CPU
            if (mb_strlen($content) > 750) {
                $content = mb_substr($content, 0, 750).'...';
            }

            $sections[] = "[Fragmento {$num}] Documento: {$title} (Pág. {$page})\n{$content}";
        }

        return implode("\n\n---\n\n", $sections);
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
