<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RagDocument;
use App\Services\AI\QdrantService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RagLibraryController extends Controller
{
    /**
     * Gibt eine Liste aller Dokumente zurück.
     */
    public function index()
    {
        if (Auth::user()->employeetype !== 'professor') {
            abort(403, 'Nur Dozenten haben Zugriff auf die Wissensdatenbank.');
        }

        // Holt alle Dokumente, die neuesten zuerst
        return response()->json(RagDocument::latest()->get());
    }

    /**
     * Benennt den Titel eines Dokuments um (nur in der MySQL-Datenbank).
     */
    public function update(Request $request, $uuid)
    {
        if (Auth::user()->employeetype !== 'professor') abort(403);

        $request->validate(['title' => 'required|string|max:255']);

        $doc = RagDocument::where('uuid', $uuid)->firstOrFail();
        $doc->update(['title' => $request->title]);

        return response()->json(['success' => true]);
    }

    /**
     * Löscht Dokumente sowohl aus Qdrant (Vektoren) als auch aus MySQL (Metadaten).
     */
    public function destroy(Request $request)
    {
        if (Auth::user()->employeetype !== 'professor') abort(403);

        $uuids = $request->input('uuids', []);
        $qdrant = new QdrantService();

        foreach ($uuids as $uuid) {
            try {
                // 1. Physisch aus Qdrant löschen
                $qdrant->deleteBySourceFile($uuid);

                // 2. Metadaten aus der MySQL-Tabelle löschen
                RagDocument::where('uuid', $uuid)->delete();
            } catch (\Exception $e) {
                Log::error("Fehler beim Löschen des RAG Dokuments {$uuid}: " . $e->getMessage());
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Ändert den Berechtigungs-Tag (Sichtbarkeit)
     */
    public function updateTag(Request $request, $uuid)
    {
        if (\Illuminate\Support\Facades\Auth::user()->employeetype !== 'professor') abort(403);

        $request->validate(['tag' => 'required|string|in:professor,student']);

        // 1. In MySQL aktualisieren
        $doc = RagDocument::where('uuid', $uuid)->firstOrFail();
        $doc->update(['tag' => $request->tag]);

        // 2. In Qdrant aktualisieren
        $qdrant = new QdrantService();
        $qdrant->updateTagBySourceFile($uuid, $request->tag);

        return response()->json(['success' => true]);
    }
}
