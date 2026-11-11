<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use App\Services\RAG\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Handle semantic search queries and display results with optional RAG response.
     */
    public function index(
        Request $request,
        QdrantClient $qdrant,
        OllamaEmbedder $embedder,
        RagService $rag
    ): View {
        $userId = auth()->id();
        $collection = config('services.qdrant.collection', 'document_chunks');
        $queryText = trim((string) $request->input('q', ''));
        $selectedDocumentId = $request->input('document_id');
        $useRag = $request->has('q') ? $request->boolean('rag', true) : true;

        // Ampliar tiempo límite de ejecución para inferencia de LLM en CPU
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        // Documentos disponibles del usuario para el filtro opcional
        $userDocuments = Document::where('user_id', $userId)
            ->where('status', Document::STATUS_READY)
            ->orderBy('title')
            ->get(['id', 'title']);

        $results = [];
        $ragAnswer = null;
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

                // 4. Generación Aumentada por Recuperación (RAG) con LLM local
                if ($useRag && ! empty($results)) {
                    $ragChunks = array_map(function ($r) {
                        return [
                            'document_title' => $r['title'],
                            'page' => $r['page'],
                            'content' => $r['text'],
                            'score' => $r['score'],
                        ];
                    }, $results);

                    $ragAnswer = $rag->generateAnswer($queryText, $ragChunks, maxChunks: 2);
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
            'useRag' => $useRag,
            'results' => $results,
            'ragAnswer' => $ragAnswer,
            'userDocuments' => $userDocuments,
            'latencyMs' => $latencyMs,
            'error' => $error,
        ]);
    }
}
