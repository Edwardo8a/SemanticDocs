@extends('layouts.app')

@section('title', 'Gestión de Documentos')

@section('content')
<div class="space-y-8">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Gestión de Documentos</h1>
            <p class="text-sm text-slate-600 mt-1">Sube archivos PDF o texto para procesarlos, fragmentarlos y almacenarlos en la base vectorial Qdrant.</p>
        </div>
        <div>
            <a href="{{ route('search.index') }}" class="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 hover:text-brand-600 hover:border-brand-300 font-semibold text-sm shadow-xs transition-colors">
                <svg class="w-4 h-4 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <span>Ir al Buscador</span>
            </a>
        </div>
    </div>

    <!-- Stats Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Documentos</span>
            <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">{{ $stats['total'] }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Listos / Indexados</span>
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-700 mt-1">{{ $stats['ready'] }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <span class="text-xs font-semibold uppercase tracking-wider text-amber-500">En Cola / Procesando</span>
            <div class="text-2xl sm:text-3xl font-extrabold text-amber-600 mt-1">{{ $stats['processing'] }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
            <span class="text-xs font-semibold uppercase tracking-wider text-brand-600">Vectores en Qdrant</span>
            <div class="text-2xl sm:text-3xl font-extrabold text-brand-700 mt-1">{{ number_format($stats['total_chunks']) }}</div>
        </div>
    </div>

    @if($stats['processing'] > 0)
        <!-- Processing notice & auto-reload -->
        <div class="bg-indigo-50 border border-indigo-200 text-brand-900 rounded-2xl p-4 flex items-center justify-between text-xs sm:text-sm">
            <div class="flex items-center space-x-3">
                <svg class="w-5 h-5 text-brand-600 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <div>
                    <span class="font-bold">Hay {{ $stats['processing'] }} documento(s) en proceso o en cola.</span>
                    <span class="text-brand-700 ml-1">Esta vista se actualizará automáticamente cuando terminen.</span>
                </div>
            </div>
            <span class="hidden sm:inline-block font-mono text-xs bg-indigo-100 text-brand-700 px-2.5 py-1 rounded-lg">Auto-refresh 5s</span>
        </div>
        <script>
            setTimeout(function() {
                window.location.reload();
            }, 5000);
        </script>
    @endif

    <!-- Upload Card -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
        <div class="flex items-center space-x-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900">Subir nuevo documento</h2>
                <p class="text-xs text-slate-500">Soporta archivos en formato PDF, TXT y Markdown (hasta 25 MB).</p>
            </div>
        </div>

        <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="file" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Archivo *</label>
                    <input 
                        type="file" 
                        name="file" 
                        id="file" 
                        required
                        accept=".pdf,.txt,.md"
                        class="block w-full text-sm text-slate-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 border border-slate-200 rounded-xl cursor-pointer bg-slate-50/50 focus:outline-none"
                    >
                </div>
                <div>
                    <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Título opcional</label>
                    <input 
                        type="text" 
                        name="title" 
                        id="title" 
                        placeholder="Dejar vacío para usar nombre del archivo"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
                    >
                </div>
            </div>

            <div class="flex items-center justify-between pt-2">
                <div class="text-xs text-slate-400 flex items-center space-x-1.5">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>El procesamiento se ejecuta en segundo plano con Laravel Queues.</span>
                </div>
                <button 
                    type="submit" 
                    class="px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-semibold text-sm shadow-md shadow-brand-500/20 transition-all flex items-center space-x-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span>Procesar Documento</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Documents List Table -->
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-900">Documentos Registrados</h3>
            <span class="text-xs text-slate-400">Total: {{ $documents->total() }}</span>
        </div>

        @if($documents->isEmpty())
            <div class="p-12 text-center space-y-3">
                <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <p class="text-sm text-slate-500">Aún no has subido ningún documento. Sube tu primer PDF o archivo de texto arriba.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-6 font-semibold">Documento</th>
                            <th class="py-3 px-6 font-semibold">Estado</th>
                            <th class="py-3 px-6 font-semibold text-center">Páginas</th>
                            <th class="py-3 px-6 font-semibold text-center">Chunks (Vectores)</th>
                            <th class="py-3 px-6 font-semibold">Fecha</th>
                            <th class="py-3 px-6 font-semibold text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($documents as $doc)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Title & Original name -->
                                <td class="py-4 px-6">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-brand-600 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900">{{ $doc->title }}</div>
                                            <div class="text-xs text-slate-400">{{ $doc->original_name }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 px-6">
                                    @if($doc->isReady())
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                            Listo
                                        </span>
                                    @elseif($doc->isProcessing())
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-brand-700 border border-indigo-200 animate-pulse">
                                            <svg class="w-3 h-3 mr-1 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                            Procesando
                                        </span>
                                    @elseif($doc->isPending())
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                            En cola
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200" title="{{ $doc->error_message }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>
                                            Fallido
                                        </span>
                                    @endif
                                </td>

                                <!-- Pages -->
                                <td class="py-4 px-6 text-center text-slate-700 font-mono text-xs">
                                    {{ $doc->pages }}
                                </td>

                                <!-- Chunks -->
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-xs font-semibold">
                                        {{ $doc->chunks_count }}
                                    </span>
                                </td>

                                <!-- Date -->
                                <td class="py-4 px-6 text-xs text-slate-500">
                                    {{ $doc->created_at->diffForHumans() }}
                                </td>

                                <!-- Actions -->
                                <td class="py-4 px-6 text-right">
                                    <div class="flex items-center justify-end space-x-2">
                                        @if($doc->isReady())
                                            <a href="{{ route('search.index', ['document_id' => $doc->id]) }}" class="p-1.5 text-slate-400 hover:text-brand-600 rounded-lg hover:bg-slate-100 transition-colors" title="Buscar en este documento">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                                </svg>
                                            </a>
                                        @else
                                            <form action="{{ route('documents.retry', $doc) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="p-1.5 text-slate-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition-colors" title="Reintentar procesamiento">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        <form action="{{ route('documents.destroy', $doc) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este documento y todos sus vectores asociados en Qdrant?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors" title="Eliminar documento">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($documents->hasPages())
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $documents->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection
