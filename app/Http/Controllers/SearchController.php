<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Handle semantic search queries and display results.
     */
    public function index(Request $request, QdrantClient $qdrant, OllamaEmbedder $embedder): View
    {
        $userId = auth()->id();
        $collection = config('services.qdrant.collection', 'document_chunks');
        $queryText = trim((string) $request->input('q', ''));
        $selectedDocumentId = $request->input('document_id');

        // Documentos disponibles del usuario para el filtro opcional
        $userDocuments = Document::where('user_id', $userId)
            ->where('status', Document::STATUS_READY)
            ->orderBy('title')
            ->get(['id', 'title']);

        $results = [];
        $latencyMs = 0;
        $error = null;

        if (! empty($queryText)) {
            $startTime = microtime(true);

            try {
                // 1. Generar embedding de la pregunta del usuario
                $queryVector = $embedder->embed($queryText);

                // 2. Construir filtro obligatorio por usuario (aislamiento estricto multiusuario)
                $mustConditions = [
                    [
                        'key' => 'user_id',
                        'match' => ['value' => $userId],
                    ],
                ];

                if (! empty($selectedDocumentId) && is_numeric($selectedDocumentId)) {
                    $mustConditions[] = [
                        'key' => 'document_id',
                        'match' => ['value' => (int) $selectedDocumentId],
                    ];
                }

                $filter = ['must' => $mustConditions];

                // 3. Consultar a Qdrant
                $hits = $qdrant->search(
                    collection: $collection,
                    vector: $queryVector,
                    limit: 8,
                    filter: $filter,
                    scoreThreshold: 0.30
                );

                foreach ($hits as $hit) {
                    $payload = $hit['payload'] ?? [];
                    $score = (float) ($hit['score'] ?? 0);

                    $results[] = [
                        'id' => $hit['id'] ?? null,
                        'score' => round($score, 4),
                        'score_percent' => min(100, max(0, round($score * 100, 1))),
                        'title' => $payload['title'] ?? 'Documento sin título',
                        'document_id' => $payload['document_id'] ?? null,
                        'page' => $payload['page'] ?? 1,
                        'chunk_index' => $payload['chunk_index'] ?? 0,
                        'text' => $payload['text'] ?? '',
                    ];
                }

                $latencyMs = round((microtime(true) - $startTime) * 1000, 1);
            } catch (\Throwable $e) {
                Log::error('Error en búsqueda semántica: '.$e->getMessage(), [
                    'exception' => $e,
                ]);
                $error = 'Ocurrió un error al consultar el motor de búsqueda vectorial. Verifica que Ollama y Qdrant estén activos.';
            }
        }

        return view('search.index', [
            'query' => $queryText,
            'selectedDocumentId' => $selectedDocumentId,
            'results' => $results,
            'userDocuments' => $userDocuments,
            'latencyMs' => $latencyMs,
            'error' => $error,
        ]);
    }
}
