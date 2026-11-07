<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_loads_successfully_without_query(): void
    {
        $response = $this->get(route('search.index'));

        $response->assertStatus(200);
        $response->assertSee('Busca en tus documentos');
        $response->assertSee('Comienza una búsqueda');
    }

    public function test_search_returns_relevant_chunks(): void
    {
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'title' => 'Reglamento Académico',
            'original_name' => 'reglamento.pdf',
            'path' => 'documents/reglamento.pdf',
            'status' => Document::STATUS_READY,
        ]);

        $mockEmbedder = Mockery::mock(OllamaEmbedder::class);
        $mockEmbedder->shouldReceive('embed')
            ->once()
            ->with('¿cómo dar de baja una materia?')
            ->andReturn([0.1, 0.2, 0.3]);

        $mockQdrant = Mockery::mock(QdrantClient::class);
        $mockQdrant->shouldReceive('search')
            ->once()
            ->andReturn([
                [
                    'id' => 'point-uuid-1',
                    'score' => 0.785,
                    'payload' => [
                        'user_id' => $user->id,
                        'document_id' => $document->id,
                        'chunk_index' => 0,
                        'page' => 12,
                        'title' => 'Reglamento Académico',
                        'text' => 'Artículo 45. La baja temporal de una materia se tramita en las dos primeras semanas.',
                    ],
                ],
            ]);

        $this->app->instance(OllamaEmbedder::class, $mockEmbedder);
        $this->app->instance(QdrantClient::class, $mockQdrant);

        $response = $this->get(route('search.index', ['q' => '¿cómo dar de baja una materia?']));

        $response->assertStatus(200);
        $response->assertSee('Reglamento Académico');
        $response->assertSee('Página 12');
        $response->assertSee('Artículo 45. La baja temporal');
        $response->assertSee('78.5%');
    }
}
