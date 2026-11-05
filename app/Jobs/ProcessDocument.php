<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Documents\Chunker;
use App\Services\Documents\TextExtractor;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutos para procesar documentos grandes

    public int $tries = 2;

    public function __construct(public Document $document) {}

    /**
     * Execute the job.
     */
    public function handle(
        TextExtractor $extractor,
        Chunker $chunker,
        OllamaEmbedder $embedder,
        QdrantClient $qdrant
    ): void {
        Log::info("Iniciando procesamiento del documento #{$this->document->id}: {$this->document->title}");
        $this->document->markAsProcessing();

        try {
            // 1. Obtener ruta del archivo
            $filePath = Storage::disk('local')->path($this->document->path);

            if (! file_exists($filePath)) {
                if (file_exists(storage_path('app/'.$this->document->path))) {
                    $filePath = storage_path('app/'.$this->document->path);
                } elseif (file_exists($this->document->path)) {
                    $filePath = $this->document->path;
                } else {
                    throw new \RuntimeException("El archivo físico no existe en la ruta de almacenamiento: {$filePath}");
                }
            }

            // 2. Extraer páginas de texto
            $pages = $extractor->extract($filePath);

            if (empty($pages)) {
                throw new \RuntimeException('El documento no contiene páginas legibles o está vacío.');
            }

            // 3. Generar fragmentos (chunking)
            $chunks = $chunker->chunkPages($pages);

            if (empty($chunks)) {
                throw new \RuntimeException('No se pudieron generar fragmentos de texto a partir del documento.');
            }

            // 4. Asegurar que la colección existe en Qdrant
            $collection = config('services.qdrant.collection', 'document_chunks');
            $dimension = $embedder->getDimension();

            if (! $qdrant->hasCollection($collection)) {
                $qdrant->createCollection($collection, $dimension, 'Cosine');
                $qdrant->createPayloadIndex($collection, 'user_id', 'integer');
                $qdrant->createPayloadIndex($collection, 'document_id', 'integer');
            }

            // 5. Generar embeddings por lotes e insertar en Qdrant
            $batchSize = 10;
            $chunksCount = count($chunks);
            $userId = $this->document->user_id ?? 1;

            for ($i = 0; $i < $chunksCount; $i += $batchSize) {
                $chunkBatch = array_slice($chunks, $i, $batchSize);
                $batchTexts = array_column($chunkBatch, 'text');

                $vectors = $embedder->embedBatch($batchTexts);

                $points = [];
                foreach ($chunkBatch as $index => $chunk) {
                    $vector = $vectors[$index] ?? null;
                    if (! $vector) {
                        continue;
                    }

                    $points[] = [
                        'id' => Str::uuid()->toString(),
                        'vector' => $vector,
                        'payload' => [
                            'user_id' => $userId,
                            'document_id' => $this->document->id,
                            'chunk_index' => $chunk['chunk_index'],
                            'page' => $chunk['page'],
                            'title' => $this->document->title,
                            'text' => $chunk['text'],
                        ],
                    ];
                }

                if (! empty($points)) {
                    $qdrant->upsertPoints($collection, $points, wait: true);
                }
            }

            // 6. Marcar como completado
            $this->document->markAsReady(
                pages: count($pages),
                chunksCount: $chunksCount
            );

            Log::info("Documento #{$this->document->id} procesado exitosamente. Páginas: ".count($pages).", Chunks: {$chunksCount}");
        } catch (Throwable $e) {
            Log::error("Error procesando documento #{$this->document->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            $this->document->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $message = $exception ? $exception->getMessage() : 'Error desconocido durante el procesamiento en cola.';
        $this->document->markAsFailed($message);
    }
}
