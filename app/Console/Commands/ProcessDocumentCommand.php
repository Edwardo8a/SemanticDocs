<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessDocumentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semanticdocs:ingest 
                            {file : Ruta al archivo PDF o de texto} 
                            {--title= : Título opcional del documento} 
                            {--user=1 : ID de usuario asociado}
                            {--sync : Procesar de forma sincrónica en lugar de encolar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingesta un documento PDF o de texto, genera fragmentos y embeddings, y los guarda en Qdrant';

    /**
     * Execute the console command.
     */
    public function handle(QdrantClient $qdrant, OllamaEmbedder $embedder): int
    {
        $filePath = $this->argument('file');

        if (! File::exists($filePath)) {
            $this->error("El archivo no existe: {$filePath}");

            return Command::FAILURE;
        }

        $originalName = pathinfo($filePath, PATHINFO_BASENAME);
        $title = $this->option('title') ?: pathinfo($filePath, PATHINFO_FILENAME);
        $requestedUserId = (int) $this->option('user');
        $user = User::find($requestedUserId) ?? User::first();
        $userId = $user?->id;

        // Copiar archivo al almacenamiento interno de la app
        $storedFileName = Str::uuid()->toString().'_'.$originalName;
        $storedPath = Storage::disk('local')->putFileAs('documents', new \Illuminate\Http\File($filePath), $storedFileName);

        $this->info('=== Ingesta de Documento (Etapa 2) ===');
        $this->line("• Archivo origen: <comment>{$originalName}</comment>");
        $this->line("• Título        : <comment>{$title}</comment>");
        $this->line("• Usuario ID    : <comment>{$userId}</comment>");

        // 1. Crear registro en PostgreSQL
        $document = Document::create([
            'user_id' => $userId,
            'title' => $title,
            'original_name' => $originalName,
            'path' => $storedPath,
            'status' => Document::STATUS_PENDING,
        ]);

        $this->info("✔ Registro creado en BD PostgreSQL con ID #{$document->id} (Estado: pending).");

        // 2. Despachar Job
        $isSync = $this->option('sync') || config('queue.default') === 'sync';

        if ($isSync) {
            $this->warn('Procesando documento inmediatamente (sincrónico)...');
            ProcessDocument::dispatchSync($document);
        } else {
            $this->info("Despachando Job 'ProcessDocument' a la cola...");
            ProcessDocument::dispatch($document);
            $this->line("Nota: El job se procesará cuando la cola esté activa ('php artisan queue:work').");
        }

        // Recargar modelo actualizado
        $document->refresh();

        $this->newLine();
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID', $document->id],
                ['Título', $document->title],
                ['Estado', $document->status],
                ['Páginas', $document->pages],
                ['Fragmentos (Chunks)', $document->chunks_count],
                ['Error', $document->error_message ?: 'Ninguno'],
            ]
        );

        if ($document->isReady()) {
            $this->info('✔ Documento procesado y vectorizado exitosamente en Qdrant.');
        } elseif ($document->isFailed()) {
            $this->error("✖ El procesamiento del documento falló: {$document->error_message}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
