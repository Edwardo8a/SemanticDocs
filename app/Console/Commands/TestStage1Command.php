<?php

namespace App\Console\Commands;

use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestStage1Command extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semanticdocs:test-stage1 
                            {--query= : Pregunta personalizada para buscar por significado} 
                            {--reset : Forzar la recreación de la colección y reinsertar textos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Etapa 1: Prueba de Qdrant y Embeddings con textos en español y búsqueda semántica';

    /**
     * Execute the console command.
     */
    public function handle(QdrantClient $qdrant, OllamaEmbedder $embedder): int
    {
        $collection = config('services.qdrant.collection', 'document_chunks');
        $dimension = $embedder->getDimension();
        $model = $embedder->getModel();

        $this->info("=== SemanticDocs: Verificación Etapa 1 ===");
        $this->line("• Colección Qdrant : <comment>{$collection}</comment>");
        $this->line("• Modelo Embeddings: <comment>{$model}</comment> ({$dimension} dims)");

        // 1. Gestión de la Colección en Qdrant
        $exists = $qdrant->hasCollection($collection);

        if ($this->option('reset') && $exists) {
            $this->warn("Eliminando colección previa '{$collection}'...");
            $qdrant->deleteCollection($collection);
            $exists = false;
        }

        if (!$exists) {
            $this->info("Creando colección '{$collection}' en Qdrant...");
            $qdrant->createCollection($collection, $dimension, 'Cosine');
            $qdrant->createPayloadIndex($collection, 'user_id', 'integer');
            $qdrant->createPayloadIndex($collection, 'document_id', 'integer');
            $this->info("✔ Colección e índices creados exitosamente.");
            $shouldIndex = true;
        } else {
            $this->line("✔ La colección ya existe.");
            $shouldIndex = $this->option('reset');
        }

        // 2. Fragmentos de prueba (Reglamento Académico en español)
        $sampleDocs = [
            [
                'title' => 'Reglamento de Servicios Escolares',
                'page' => 12,
                'text' => 'Artículo 45. La baja temporal de una o varias asignaturas deberá ser solicitada por el estudiante ante el Departamento de Servicios Escolares dentro de las primeras dos semanas hábiles del cuatrimestre en curso.',
            ],
            [
                'title' => 'Reglamento de Servicios Escolares',
                'page' => 14,
                'text' => 'Artículo 51. La baja definitiva de la institución puede ser solicitada en cualquier momento mediante escrito formal firmado por el estudiante y entrega de la constancia de no adeudo con biblioteca y laboratorios.',
            ],
            [
                'title' => 'Manual de Titulación y Egreso',
                'page' => 4,
                'text' => 'Para iniciar los trámites de titulación profesional, es requisito indispensable haber cubierto el 100% de los créditos curriculares, contar con la carta de liberación de servicio social y acreditación de un segundo idioma nivel B1.',
            ],
            [
                'title' => 'Normativa de Servicio Social y Estadías',
                'page' => 8,
                'text' => 'El servicio social podrá iniciarse una vez acreditado al menos el 70% del plan de estudios, cumpliendo un total de 480 horas en un periodo no menor a seis meses ni mayor a un año.',
            ],
            [
                'title' => 'Reglamento de Evaluación y Asistencia',
                'page' => 20,
                'text' => 'Para tener derecho a examen ordinario, el alumno deberá registrar un mínimo de 80% de asistencias en el periodo cuatrimestral. Las inasistencias por causas de salud deberán justificarse en un plazo máximo de 72 horas.',
            ],
            [
                'title' => 'Reglamento de Evaluación y Asistencia',
                'page' => 22,
                'text' => 'La evaluación extraordinaria se aplicará a los estudiantes que obtengan una calificación final entre 6.0 y 6.9, previo pago del arancel correspondiente y registro en el sistema antes de la fecha límite.',
            ],
            [
                'title' => 'Reglamento de Biblioteca',
                'page' => 5,
                'text' => 'El préstamo a domicilio de libros de texto se otorga por un plazo de 3 días hábiles renovable por una ocasión, siempre y cuando no exista lista de espera para el ejemplar solicitado.',
            ],
            [
                'title' => 'Convocatoria de Becas Institucionales',
                'page' => 2,
                'text' => 'Podrán aspirar a la beca de excelencia académica los estudiantes regulares con un promedio mínimo de 9.5 en el ciclo inmediato anterior y sin ninguna asignatura reprobada.',
            ],
            [
                'title' => 'Guía de Pagos y Reinscripciones',
                'page' => 3,
                'text' => 'El pago de la cuota de reinscripción semestral debe realizarse en las instituciones bancarias autorizadas utilizando la línea de captura generada en el portal del alumno antes del día 15 de agosto.',
            ],
        ];

        if ($shouldIndex) {
            $this->info("Generando embeddings e insertando " . count($sampleDocs) . " fragmentos de prueba...");
            $points = [];
            $bar = $this->output->createProgressBar(count($sampleDocs));

            foreach ($sampleDocs as $index => $doc) {
                $vector = $embedder->embed($doc['text']);
                $points[] = [
                    'id' => Str::uuid()->toString(),
                    'vector' => $vector,
                    'payload' => [
                        'user_id' => 1,
                        'document_id' => 101,
                        'chunk_index' => $index,
                        'title' => $doc['title'],
                        'page' => $doc['page'],
                        'text' => $doc['text'],
                    ],
                ];
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();

            $qdrant->upsertPoints($collection, $points);
            $this->info("✔ Fragmentos vectorizados e insertados en Qdrant con éxito.");
        }

        // 3. Ejecutar pruebas de búsqueda semántica
        $testQueries = $this->option('query')
            ? [$this->option('query')]
            : [
                '¿cuándo puedo dar de baja una materia?',
                '¿qué necesito para graduarme y tener mi título?',
                '¿cómo justifico una falta si estuve enfermo?',
                '¿cuánto tiempo me prestan un libro en la biblioteca?',
            ];

        $this->newLine();
        $this->info("=== Resultados de Búsqueda Semántica ===");

        foreach ($testQueries as $qText) {
            $this->line("\n<fg=cyan;options=bold>Pregunta:</> \"{$qText}\"");

            $qVector = $embedder->embed($qText);

            // Filtro por usuario obligatorio como dicta la arquitectura
            $filter = [
                'must' => [
                    [
                        'key' => 'user_id',
                        'match' => ['value' => 1],
                    ],
                ],
            ];

            $results = $qdrant->search($collection, $qVector, limit: 2, filter: $filter);

            $rows = [];
            foreach ($results as $hit) {
                $payload = $hit['payload'] ?? [];
                $score = number_format($hit['score'] ?? 0, 4);
                $rows[] = [
                    $score,
                    $payload['title'] ?? 'N/A',
                    'Pág. ' . ($payload['page'] ?? '1'),
                    Str::limit($payload['text'] ?? '', 85),
                ];
            }

            $this->table(['Similitud (Coseno)', 'Documento', 'Página', 'Fragmento recuperado'], $rows);
        }

        $this->info("\n✔ ¡Etapa 1 completada con éxito! El pipeline Vectorial (Qdrant + Ollama + Laravel) funciona correctamente.");
        return Command::SUCCESS;
    }
}
