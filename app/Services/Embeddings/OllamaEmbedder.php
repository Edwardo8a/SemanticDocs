<?php

namespace App\Services\Embeddings;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OllamaEmbedder
{
    protected string $host;
    protected string $model;
    protected int $dimension;

    public function __construct(?string $host = null, ?string $model = null, ?int $dimension = null)
    {
        $this->host = rtrim($host ?? config('services.ollama.host', 'http://localhost:11434'), '/');
        $this->model = $model ?? config('services.ollama.embed_model', 'nomic-embed-text');
        $this->dimension = $dimension ?? (int) config('services.ollama.embed_dim', 768);
    }

    /**
     * Get the configured embedding dimension.
     */
    public function getDimension(): int
    {
        return $this->dimension;
    }

    /**
     * Get the configured model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Generate an embedding vector for a single string.
     *
     * @return array<float>
     */
    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);

        if (empty($batch[0])) {
            throw new RuntimeException("No embedding returned for text input.");
        }

        return $batch[0];
    }

    /**
     * Generate embedding vectors for an array of strings.
     *
     * @param array<string> $texts
     * @return array<array<float>>
     */
    public function embedBatch(array $texts): array
    {
        if (empty($texts)) {
            return [];
        }

        $response = Http::baseUrl($this->host)
            ->timeout(60)
            ->acceptJson()
            ->post('/api/embed', [
                'model' => $this->model,
                'input' => count($texts) === 1 ? $texts[0] : array_values($texts),
            ]);

        if (!$response->successful()) {
            Log::error('Ollama embed request failed', [
                'model' => $this->model,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Ollama embed error: " . $response->body());
        }

        $embeddings = $response->json('embeddings', []);

        if (empty($embeddings)) {
            throw new RuntimeException("Ollama returned empty embeddings response.");
        }

        return $embeddings;
    }
}
