@extends('layouts.home')

@section('content')
<div class="scroll-container">
    <div class="scroll-panel">
        <div class="inputs-list profile-container" id="rag-library" style="max-width: 1000px; margin: 0 auto;">

            <h1 class="zero-b-margin">Wissensdatenbank (Qdrant)</h1>
            <p class="sub-descript">Verwalte hier alle RAG-Dokumente der Dozierenden.</p>

            <div style="display: flex; gap: 1rem; margin-top: 2rem; margin-bottom: 1rem;">
                <input type="text" id="rag-search-input" placeholder="Nach Titel suchen..." onkeyup="filterRagDocs()" class="text-input" style="flex: 1;">
                <button class="btn-md-fill delete-btn" onclick="deleteSelectedRagDocs()">Ausgewählte löschen</button>
            </div>

            <table style="width: 100%; text-align: left; border-collapse: collapse; margin-top: 1rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-stroke-thin); font-size: var(--font-sm);">
                        <th style="padding: 1rem 0.5rem;"><input type="checkbox" id="selectAllRag" onclick="toggleSelectAllRag(this)"></th>
                        <th style="padding: 1rem 0.5rem;">Titel</th>
                        <th style="padding: 1rem 0.5rem;">Tag</th>
                        <th style="padding: 1rem 0.5rem;">Datum</th>
                        <th style="padding: 1rem 0.5rem;">Aktion</th>
                    </tr>
                </thead>
                <tbody id="rag-docs-list">
                    <!-- Wird per JS befüllt -->
                </tbody>
            </table>

        </div>
    </div>
</div>
<script src="{{ asset('js/rag_library.js') }}"></script>
@endsection
