<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_index_page_loads_successfully(): void
    {
        $user = User::factory()->create();

        Document::create([
            'user_id' => $user->id,
            'title' => 'Doc Test 1',
            'original_name' => 'doc1.pdf',
            'path' => 'documents/test.pdf',
            'status' => Document::STATUS_READY,
            'pages' => 5,
            'chunks_count' => 12,
        ]);

        $response = $this->get(route('documents.index'));

        $response->assertStatus(200);
        $response->assertSee('Gestión de Documentos');
        $response->assertSee('Doc Test 1');
        $response->assertSee('doc1.pdf');
    }

    public function test_can_upload_document_and_dispatch_process_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('manual_de_usuario.pdf', 100, 'application/pdf');

        $response = $this->post(route('documents.store'), [
            'file' => $file,
            'title' => 'Manual de Usuario',
        ]);

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('documents', [
            'title' => 'Manual de Usuario',
            'original_name' => 'manual_de_usuario.pdf',
            'status' => Document::STATUS_PENDING,
        ]);

        Queue::assertPushed(ProcessDocument::class);
    }

    public function test_upload_validates_required_file_and_allowed_extensions(): void
    {
        $response = $this->post(route('documents.store'), [
            'file' => null,
        ]);

        $response->assertSessionHasErrors('file');

        $invalidFile = UploadedFile::fake()->create('virus.exe', 100);

        $response = $this->post(route('documents.store'), [
            'file' => $invalidFile,
        ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_can_destroy_document_and_remove_vectors(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/test.pdf', 'dummy content');

        $user = User::factory()->create();

        $document = Document::create([
            'user_id' => $user->id,
            'title' => 'Documento para borrar',
            'original_name' => 'test.pdf',
            'path' => 'documents/test.pdf',
            'status' => Document::STATUS_READY,
        ]);

        $mockQdrant = Mockery::mock(QdrantClient::class);
        $mockQdrant->shouldReceive('deletePointsByFilter')
            ->once()
            ->andReturn(true);

        $this->app->instance(QdrantClient::class, $mockQdrant);

        $response = $this->delete(route('documents.destroy', $document));

        $response->assertRedirect(route('documents.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('documents', [
            'id' => $document->id,
        ]);

        Storage::disk('local')->assertMissing('documents/test.pdf');
    }
}
