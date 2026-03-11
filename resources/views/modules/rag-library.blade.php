@extends('layouts.home')

@section('content')
<div class="scroll-container">
    <div class="scroll-panel">
        <div class="inputs-list profile-container" id="rag-library" style="max-width: 1000px; margin: 0 auto;">

            <h1 class="zero-b-margin">Wissensdatenbank (Qdrant)</h1>
            <p class="sub-descript">Verwaltung aller RAG-Dokumente.</p>

            <!-- Suchfeld & Roter Button -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; margin-bottom: 1rem; align-items: center;">
                <input type="text" id="rag-search-input" placeholder="Nach Titel suchen..." onkeyup="filterRagDocs()" class="text-input" style="flex: 1; margin: 0;">
                <button onclick="deleteSelectedRagDocs()" style="background-color: #e53e3e; color: white; border: none; border-radius: 8px; padding: 0 1.5rem; height: 3rem; font-weight: 500; cursor: pointer; width: max-content; white-space: nowrap; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#c53030'" onmouseout="this.style.backgroundColor='#e53e3e'">Ausgewählte löschen</button>
            </div>

            <!-- Verkleinerte Tabelle -->
            <table style="width: 100%; text-align: left; border-collapse: collapse; margin-top: 1rem; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-stroke-thin);">
                        <th style="padding: 0.5rem;"><input type="checkbox" id="selectAllRag" onclick="toggleSelectAllRag(this)"></th>
                        <th style="padding: 0.5rem;">Titel</th>
                        <th style="padding: 0.5rem;">Sichtbarkeit (Tag)</th>
                        <th style="padding: 0.5rem;">Datum</th>
                        <th style="padding: 0.5rem;">Aktion</th>
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
