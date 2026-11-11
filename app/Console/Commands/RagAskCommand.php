<?php

namespace App\Console\Commands;

use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use App\Services\RAG\RagService;
use Illuminate\Console\Command;

class RagAskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semanticdocs:ask 
                            {question : La pregunta en lenguaje natural} 
                            {--limit=4 : Número máximo de fragmentos a recuperar} 
                            {--doc= : Filtrar por ID de documento}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Realiza una pregunta a tus documentos indexados utilizando RAG (Qdrant + Ollama)';

    /**
     * Execute the console command.
     */
    public function handle(QdrantClient $qdrant, OllamaEmbedder $embedder, RagService $rag): int
    {
        $question = (string) $this->argument('question');
        $limit = (int) $this->option('limit');
        $docId = $this->option('doc');
        $collection = config('services.qdrant.collection', 'document_chunks');

        $this->info('=== SemanticDocs: Consulta RAG ===');
        $this->line("Pregunta: <comment>{$question}</comment>");
        $this->line("Generando embedding con <comment>{$embedder->getModel()}</comment>...");

        $queryVector = $embedder->embed($question);

        $filter = null;
        if ($docId) {
            $filter = [
                'must' => [
                    [
                        'key' => 'document_id',
                        'match' => ['value' => (int) $docId],
                    ],
                ],
            ];
        }

        $this->line("Consultando vectores en Qdrant ({$collection})...");
        $hits = $qdrant->search($collection, $queryVector, limit: $limit, filter: $filter, scoreThreshold: 0.35);

        if (empty($hits)) {
            $this->warn('No se encontraron fragmentos con suficiente similitud vectorial.');

            return Command::FAILURE;
        }

        $chunks = [];
        $rows = [];

        foreach ($hits as $idx => $hit) {
            $payload = $hit['payload'] ?? [];
            $score = round((float) ($hit['score'] ?? 0), 4);
            $title = $payload['title'] ?? 'Sin título';
            $page = $payload['page'] ?? 1;
            $text = $payload['text'] ?? '';

            $chunks[] = [
                'document_title' => $title,
                'page' => $page,
                'content' => $text,
                'score' => $score,
            ];

            $rows[] = [
                '#'.($idx + 1),
                $score,
                $title,
                'Pág. '.$page,
                mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 80).'...',
            ];
        }

        $this->newLine();
        $this->table(['#', 'Similitud', 'Documento', 'Página', 'Extracto'], $rows);

        $this->newLine();
        $this->line("Generando respuesta RAG con <comment>{$rag->getModel()}</comment>...");

        $startTime = microtime(true);
        $ragResult = $rag->generateAnswer($question, $chunks, maxChunks: $limit);
        $elapsed = round(microtime(true) - $startTime, 2);

        if (! $ragResult) {
            $this->error('Ocurrió un error al contactar al modelo LLM en Ollama.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('=== RESPUESTA SINTETIZADA CON IA (RAG) ===');
        $this->line($ragResult['answer']);
        $this->newLine();
        $this->line("<comment>Modelo: {$ragResult['model']} | Tiempo de respuesta: {$elapsed}s</comment>");

        return Command::SUCCESS;
    }
}
