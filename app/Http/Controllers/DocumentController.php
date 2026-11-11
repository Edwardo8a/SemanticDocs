<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Services\Qdrant\QdrantClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Display a listing of documents.
     */
    public function index(): View
    {
        $userId = auth()->id();

        $documents = Document::where('user_id', $userId)
            ->latest()
            ->paginate(10);

        $stats = [
            'total' => Document::where('user_id', $userId)->count(),
            'ready' => Document::where('user_id', $userId)->where('status', Document::STATUS_READY)->count(),
            'processing' => Document::where('user_id', $userId)->whereIn('status', [Document::STATUS_PENDING, Document::STATUS_PROCESSING])->count(),
            'total_chunks' => (int) Document::where('user_id', $userId)->sum('chunks_count'),
        ];

        return view('documents.index', compact('documents', 'stats'));
    }

    /**
     * Store a newly uploaded document and dispatch processing job.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,txt,md', 'max:25600'],
            'title' => ['nullable', 'string', 'max:255'],
        ], [
            'file.required' => 'Debes seleccionar un archivo para subir.',
            'file.mimes' => 'Solo se permiten archivos en formato PDF, TXT o MD.',
            'file.max' => 'El tamaño máximo permitido es de 25 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $originalName = $uploadedFile->getClientOriginalName();
        $title = $request->input('title') ?: pathinfo($originalName, PATHINFO_FILENAME);
        $userId = auth()->id();

        // Almacenar archivo con identificador único
        $storedFileName = Str::uuid()->toString().'_'.$originalName;
        $storedPath = Storage::disk('local')->putFileAs('documents', $uploadedFile, $storedFileName);

        $document = Document::create([
            'user_id' => $userId,
            'title' => $title,
            'original_name' => $originalName,
            'path' => $storedPath,
            'status' => Document::STATUS_PENDING,
        ]);

        // Despachar a la cola de procesamiento
        ProcessDocument::dispatch($document);

        return redirect()
            ->route('documents.index')
            ->with('success', "El documento '{$title}' ha sido cargado e ingresado a la cola de procesamiento.");
    }

    /**
     * Retry processing a failed or pending document.
     */
    public function retry(Document $document): RedirectResponse
    {
        if ($document->user_id !== auth()->id()) {
            abort(403, 'No estás autorizado para reprocesar este documento.');
        }

        $document->update([
            'status' => Document::STATUS_PENDING,
            'error_message' => null,
        ]);

        ProcessDocument::dispatch($document);

        return redirect()
            ->route('documents.index')
            ->with('success', "Se ha reenviado el documento '{$document->title}' a la cola de procesamiento.");
    }

    /**
     * Delete the document from PostgreSQL, disk storage, and Qdrant.
     */
    public function destroy(Document $document, QdrantClient $qdrant): RedirectResponse
    {
        // Aislamiento de usuario: verificar que el documento pertenezca al usuario autenticado
        if ($document->user_id !== auth()->id()) {
            abort(403, 'No estás autorizado para eliminar este documento.');
        }

        $collection = config('services.qdrant.collection', 'document_chunks');

        // 1. Eliminar vectores en Qdrant
        try {
            $qdrant->deletePointsByFilter($collection, [
                'must' => [
                    [
                        'key' => 'document_id',
                        'match' => ['value' => $document->id],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning("No se pudieron eliminar puntos en Qdrant para doc #{$document->id}: ".$e->getMessage());
        }

        // 2. Eliminar archivo en almacenamiento
        if (Storage::disk('local')->exists($document->path)) {
            Storage::disk('local')->delete($document->path);
        }

        $title = $document->title;

        // 3. Eliminar registro en base de datos
        $document->delete();

        return redirect()
            ->route('documents.index')
            ->with('success', "Documento '{$title}' y sus fragmentos vectoriales han sido eliminados.");
    }
}
