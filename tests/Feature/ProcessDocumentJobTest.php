<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\Chunker;
use App\Services\Documents\TextExtractor;
use App\Services\Embeddings\OllamaEmbedder;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_document_job_extracts_and_vectorizes_successfully(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.txt', 'Contenido del reglamento escolar para prueba de embeddings.');

        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'title' => 'Documento Test',
            'original_name' => 'test.txt',
            'path' => 'documents/test.txt',
            'status' => Document::STATUS_PENDING,
        ]);

        $mockQdrant = Mockery::mock(QdrantClient::class);
        $mockQdrant->shouldReceive('hasCollection')->andReturn(true);
        $mockQdrant->shouldReceive('upsertPoints')
            ->once()
            ->with('document_chunks', Mockery::type('array'), true)
            ->andReturn(true);

        $mockEmbedder = Mockery::mock(OllamaEmbedder::class);
        $mockEmbedder->shouldReceive('getDimension')->andReturn(768);
        $mockEmbedder->shouldReceive('embedBatch')
            ->once()
            ->andReturn([[0.1, 0.2, 0.3]]);

        $extractor = new TextExtractor;
        $chunker = new Chunker;

        $job = new ProcessDocument($document);
        $job->handle($extractor, $chunker, $mockEmbedder, $mockQdrant);

        $document->refresh();

        $this->assertTrue($document->isReady());
        $this->assertSame(1, $document->pages);
        $this->assertSame(1, $document->chunks_count);
        $this->assertNull($document->error_message);
    }

    public function test_process_document_job_handles_missing_file_as_failed(): void
    {
        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'title' => 'Inexistente',
            'original_name' => 'inexistente.pdf',
            'path' => 'documents/inexistente.pdf',
            'status' => Document::STATUS_PENDING,
        ]);

        $mockQdrant = Mockery::mock(QdrantClient::class);
        $mockEmbedder = Mockery::mock(OllamaEmbedder::class);
        $extractor = new TextExtractor;
        $chunker = new Chunker;

        $job = new ProcessDocument($document);

        $this->expectException(\Throwable::class);

        try {
            $job->handle($extractor, $chunker, $mockEmbedder, $mockQdrant);
        } finally {
            $document->refresh();
            $this->assertTrue($document->isFailed());
            $this->assertNotNull($document->error_message);
        }
    }
}
