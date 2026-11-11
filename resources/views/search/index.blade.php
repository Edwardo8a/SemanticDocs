@extends('layouts.app')

@section('title', 'Búsqueda Semántica')

@section('content')
<div class="space-y-8">

    <!-- Hero / Header -->
    <div class="text-center max-w-3xl mx-auto space-y-3">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
            Busca en tus documentos <span class="bg-gradient-to-r from-brand-600 to-indigo-500 bg-clip-text text-transparent">por significado</span>
        </h1>
        <p class="text-base text-slate-600">
            Haz preguntas en lenguaje natural. Los vectores y la distancia coseno encontrarán el fragmento relevante aunque no coincidan las palabras exactas.
        </p>
    </div>

    <!-- Search Box -->
    <div class="max-w-3xl mx-auto">
        <form action="{{ route('search.index') }}" method="GET" class="space-y-3">
            <div class="relative flex items-center shadow-lg rounded-2xl bg-white border border-slate-200 focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/10 transition-all p-1.5">
                <div class="pl-4 text-slate-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <input 
                    type="text" 
                    name="q" 
                    id="search-input"
                    value="{{ $query }}" 
                    placeholder="Ej. ¿Cuándo puedo solicitar la baja de una materia?" 
                    required
                    autocomplete="off"
                    class="w-full px-4 py-3 text-slate-800 placeholder-slate-400 text-base bg-transparent focus:outline-none"
                >

                @if(!empty($query))
                    <a href="{{ route('search.index') }}" class="p-2 text-slate-400 hover:text-slate-600 transition-colors mr-1" title="Limpiar búsqueda">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                @endif

                <button 
                    type="submit" 
                    class="px-6 py-3 rounded-xl bg-gradient-to-r from-brand-600 to-indigo-600 hover:from-brand-700 hover:to-indigo-700 text-white font-semibold text-sm shadow-md shadow-brand-500/25 transition-all flex items-center space-x-2"
                >
                    <span>Buscar</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>

            <!-- Optional Document Filter & RAG toggle -->
            <div class="flex flex-wrap items-center justify-between text-xs text-slate-500 px-2 pt-1 gap-2">
                <div class="flex flex-wrap items-center space-x-3">
                    <div class="flex items-center space-x-2">
                        <label for="document_id" class="font-medium text-slate-600">Filtrar por documento:</label>
                        <select name="document_id" id="document_id" onchange="this.form.submit()" class="bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-slate-700 focus:outline-none focus:border-brand-500 text-xs">
                            <option value="">Todos los documentos</option>
                            @foreach($userDocuments as $doc)
                                <option value="{{ $doc->id }}" {{ $selectedDocumentId == $doc->id ? 'selected' : '' }}>
                                    {{ Str::limit($doc->title, 40) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <label class="inline-flex items-center space-x-1.5 cursor-pointer select-none bg-slate-50 border border-slate-200 px-2.5 py-1 rounded-lg hover:bg-slate-100 transition-colors">
                        <input type="checkbox" name="rag" value="1" {{ ($useRag ?? true) ? 'checked' : '' }} class="rounded border-slate-300 text-brand-600 shadow-xs focus:ring-brand-500">
                        <span class="font-semibold text-slate-700 flex items-center space-x-1">
                            <span>✨ Síntesis IA (RAG)</span>
                        </span>
                    </label>
                </div>

                @if($latencyMs > 0)
                    <div class="flex items-center space-x-1.5 font-mono text-slate-400">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Latencia: {{ $latencyMs }} ms</span>
                    </div>
                @endif
            </div>

            <!-- Quick Suggestion Chips -->
            <div class="flex flex-wrap items-center gap-2 pt-1 px-1">
                <span class="text-xs text-slate-400">Sugerencias:</span>
                <button type="button" onclick="setQuery('¿cuándo puedo dar de baja una materia?')" class="text-xs bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-600 px-2.5 py-1 rounded-full transition-colors border border-slate-200">
                    Baja de materias
                </button>
                <button type="button" onclick="setQuery('¿qué requisitos necesito para graduarme y titularme?')" class="text-xs bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-600 px-2.5 py-1 rounded-full transition-colors border border-slate-200">
                    Requisitos titulación
                </button>
                <button type="button" onclick="setQuery('¿cuánto tiempo prestan un libro en la biblioteca?')" class="text-xs bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-600 px-2.5 py-1 rounded-full transition-colors border border-slate-200">
                    Préstamo de libros
                </button>
                <button type="button" onclick="setQuery('Who created Cassandra and when was it open sourced?')" class="text-xs bg-slate-100 hover:bg-brand-50 hover:text-brand-700 text-slate-600 px-2.5 py-1 rounded-full transition-colors border border-slate-200">
                    Cassandra history
                </button>
            </div>
        </form>
    </div>

    <!-- Results Section -->
    <div class="max-w-4xl mx-auto space-y-4">
        @if(!empty($error))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 rounded-2xl p-4 text-sm">
                <strong>Error:</strong> {{ $error }}
            </div>
        @endif

        @if(!empty($query))
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h2 class="text-lg font-bold text-slate-900 flex items-center space-x-2">
                    <span>Resultados para:</span>
                    <span class="text-brand-600 font-semibold italic">"{{ $query }}"</span>
                </h2>
                <span class="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full border border-slate-200">
                    {{ count($results) }} {{ count($results) === 1 ? 'coincidencia' : 'coincidencias' }}
                </span>
            </div>

            @if(empty($results))
                <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center space-y-4">
                    <div class="w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 mx-auto flex items-center justify-center">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-base font-bold text-slate-800">No se encontraron fragmentos relevantes</h3>
                        <p class="text-sm text-slate-500 max-w-md mx-auto">
                            No hubo fragmentos con similitud suficiente a tu consulta. Intenta redactar la pregunta con otras palabras o sube más documentos.
                        </p>
                    </div>
                    <a href="{{ route('documents.index') }}" class="inline-flex items-center space-x-2 text-sm font-semibold text-brand-600 hover:text-brand-700">
                        <span>Ir a subir documentos</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            @else
                @if(!empty($ragAnswer))
                    <!-- RAG AI Answer Card -->
                    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-900 via-brand-900 to-slate-900 text-white p-6 sm:p-7 shadow-xl border border-indigo-700/50 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-4">
                            <div class="flex items-center space-x-2.5">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-400 to-brand-400 text-slate-950 flex items-center justify-center font-black shadow-md shadow-amber-400/20 text-sm">
                                    ✨
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-white flex items-center space-x-2">
                                        <span>Respuesta Sintetizada con IA</span>
                                        <span class="text-xs font-mono font-medium bg-white/15 text-indigo-200 px-2 py-0.5 rounded-full">RAG</span>
                                    </h3>
                                    <p class="text-xs text-indigo-200/80">Generada exclusivamente a partir de tus documentos recuperados</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-2 text-xs font-mono text-indigo-200">
                                <span class="bg-indigo-950/60 border border-indigo-500/30 px-2.5 py-1 rounded-lg">
                                    Modelo: {{ $ragAnswer['model'] }}
                                </span>
                                @if(!empty($ragAnswer['duration_ms']))
                                    <span class="bg-indigo-950/60 border border-indigo-500/30 px-2 py-1 rounded-lg">
                                        {{ round($ragAnswer['duration_ms'] / 1000, 1) }}s
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Answer content -->
                        <div class="text-slate-100 text-sm sm:text-base leading-relaxed space-y-2 whitespace-pre-line font-normal">
                            {{ $ragAnswer['answer'] }}
                        </div>

                        <!-- Sources citations -->
                        @if(!empty($ragAnswer['sources']))
                            <div class="pt-3 border-t border-white/10 flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold text-indigo-300">Fuentes citadas:</span>
                                @foreach($ragAnswer['sources'] as $srcIdx => $source)
                                    <a 
                                        href="#chunk-{{ $srcIdx + 1 }}" 
                                        class="inline-flex items-center space-x-1.5 text-xs bg-white/10 hover:bg-white/20 text-indigo-100 px-2.5 py-1 rounded-lg transition-colors border border-white/10"
                                        title="Ver fragmento #{{ $srcIdx + 1 }}"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                        <span class="font-medium">{{ Str::limit($source['title'], 25) }}</span>
                                        <span class="text-indigo-300">· Pág. {{ $source['page'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                <div class="space-y-4">
                    @foreach($results as $index => $hit)
                        <div id="chunk-{{ $index + 1 }}" class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow space-y-3 scroll-mt-20">
                            <!-- Card Header: Title, Page, Score Badge -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                                <div class="flex items-center space-x-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-brand-600 flex items-center justify-center flex-shrink-0 font-bold text-xs">
                                        #{{ $index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900 hover:text-brand-600 transition-colors">
                                            {{ $hit['title'] }}
                                        </h3>
                                        <div class="flex items-center space-x-2 text-xs text-slate-500">
                                            <span class="inline-flex items-center space-x-1">
                                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <span>Página {{ $hit['page'] }}</span>
                                            </span>
                                            <span>&bull;</span>
                                            <span>Chunk #{{ $hit['chunk_index'] }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Score Badge -->
                                <div class="flex items-center space-x-2">
                                    @php
                                        $score = $hit['score'];
                                        $badgeClass = $score >= 0.70 
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                            : ($score >= 0.55 
                                                ? 'bg-indigo-50 text-brand-700 border-indigo-200' 
                                                : 'bg-amber-50 text-amber-700 border-amber-200');
                                    @endphp
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $badgeClass }} shadow-xs">
                                        <span class="w-2 h-2 rounded-full mr-1.5 {{ $score >= 0.70 ? 'bg-emerald-500' : ($score >= 0.55 ? 'bg-brand-500' : 'bg-amber-500') }}"></span>
                                        Similitud: {{ $hit['score_percent'] }}% ({{ $hit['score'] }})
                                    </span>
                                </div>
                            </div>

                            <!-- Card Body: Snippet Text -->
                            <div class="text-sm text-slate-700 leading-relaxed font-normal bg-slate-50/80 rounded-xl p-4 border border-slate-100">
                                {{ $hit['text'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <!-- Initial State when no query is active -->
            <div class="bg-white rounded-3xl border border-dashed border-slate-300 p-12 text-center space-y-4 my-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-brand-100 to-indigo-100 text-brand-600 mx-auto flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-slate-900">Comienza una búsqueda</h3>
                    <p class="text-sm text-slate-500 max-w-md mx-auto">
                        Escribe cualquier duda o concepto en la barra de arriba, o prueba una de las sugerencias para ver cómo Qdrant recupera la información por significado.
                    </p>
                </div>
            </div>
        @endif
    </div>

</div>

@push('scripts')
<script>
    function setQuery(text) {
        const input = document.getElementById('search-input');
        input.value = text;
        input.form.submit();
    }
</script>
@endpush
@endsection
