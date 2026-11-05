<?php

namespace App\Services\Qdrant;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class QdrantClient
{
    protected string $host;

    protected ?string $apiKey;

    public function __construct(?string $host = null, ?string $apiKey = null)
    {
        $this->host = rtrim($host ?? config('services.qdrant.host', 'http://localhost:6333'), '/');
        $this->apiKey = $apiKey ?? config('services.qdrant.key');
    }

    /**
     * Prepare HTTP client instance with auth header if configured.
     */
    protected function client()
    {
        $client = Http::baseUrl($this->host)
            ->timeout(30)
            ->acceptJson();

        if ($this->apiKey) {
            $client = $client->withHeaders(['api-key' => $this->apiKey]);
        }

        return $client;
    }

    /**
     * Check if a collection exists.
     */
    public function hasCollection(string $collection): bool
    {
        $response = $this->client()->get("/collections/{$collection}");

        return $response->successful();
    }

    /**
     * Create a new collection in Qdrant.
     */
    public function createCollection(string $collection, int $vectorSize, string $distance = 'Cosine'): bool
    {
        $payload = [
            'vectors' => [
                'size' => $vectorSize,
                'distance' => ucfirst(strtolower($distance)),
            ],
        ];

        $response = $this->client()->put("/collections/{$collection}", $payload);

        if (! $response->successful()) {
            Log::error('Failed to create Qdrant collection', [
                'collection' => $collection,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Qdrant createCollection error: '.$response->body());
        }

        return true;
    }

    /**
     * Create payload field index for faster filtering (e.g. user_id, document_id).
     */
    public function createPayloadIndex(string $collection, string $fieldName, string $schemaType = 'keyword'): bool
    {
        $response = $this->client()->put("/collections/{$collection}/index", [
            'field_name' => $fieldName,
            'field_schema' => $schemaType,
        ]);

        return $response->successful();
    }

    /**
     * Upsert points into a collection.
     * Each point format:
     * [
     *   'id' => (string UUID or int),
     *   'vector' => [float, ...],
     *   'payload' => ['user_id' => ..., 'text' => ..., ...]
     * ]
     */
    public function upsertPoints(string $collection, array $points, bool $wait = true): bool
    {
        $url = "/collections/{$collection}/points".($wait ? '?wait=true' : '');
        $response = $this->client()->put($url, [
            'points' => $points,
        ]);

        if (! $response->successful()) {
            Log::error('Failed to upsert points to Qdrant', [
                'collection' => $collection,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Qdrant upsertPoints error: '.$response->body());
        }

        return true;
    }

    /**
     * Search nearest points by vector with optional payload filter.
     */
    public function search(
        string $collection,
        array $vector,
        int $limit = 5,
        ?array $filter = null,
        float $scoreThreshold = 0.0
    ): array {
        $payload = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'with_vector' => false,
        ];

        if ($scoreThreshold > 0.0) {
            $payload['score_threshold'] = $scoreThreshold;
        }

        if (! empty($filter)) {
            $payload['filter'] = $filter;
        }

        $response = $this->client()->post("/collections/{$collection}/points/search", $payload);

        if (! $response->successful()) {
            Log::error('Failed to search Qdrant points', [
                'collection' => $collection,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Qdrant search error: '.$response->body());
        }

        return $response->json('result', []);
    }

    /**
     * Delete points matching filter (e.g. document_id).
     */
    public function deletePointsByFilter(string $collection, array $filter): bool
    {
        $response = $this->client()->post("/collections/{$collection}/points/delete", [
            'filter' => $filter,
        ]);

        return $response->successful();
    }

    /**
     * Delete entire collection.
     */
    public function deleteCollection(string $collection): bool
    {
        $response = $this->client()->delete("/collections/{$collection}");

        return $response->successful();
    }
}
