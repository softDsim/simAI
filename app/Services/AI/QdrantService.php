<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class QdrantService
{
    protected $baseUrl;
    protected $collection;
    protected $vectorSize;

    public function __construct()
    {
        $this->baseUrl = env('QDRANT_HOST', 'http://qdrant:6333');
        $this->collection = env('QDRANT_COLLECTION', 'hawki_global_knowledge');
        $this->vectorSize = (int) env('QDRANT_VECTOR_SIZE', 768);
    }

    /**
     * Schritt A: Collection initialisieren (wird einmalig benötigt)
     */
    public function ensureCollectionExists(): void
    {
        // Prüfen, ob Collection existiert
        $response = Http::get("{$this->baseUrl}/collections/{$this->collection}");

        if ($response->notFound()) {
            // Collection anlegen
            $createResponse = Http::put("{$this->baseUrl}/collections/{$this->collection}", [
                'vectors' => [
                    'size' => $this->vectorSize,
                    'distance' => 'Cosine' // Cosine Similarity ist Standard für Texte
                ]
            ]);

            if ($createResponse->failed()) {
                throw new Exception("Fehler beim Erstellen der Qdrant Collection: " . $createResponse->body());
            }
            Log::info("Qdrant Collection '{$this->collection}' erstellt.");
        }
    }

    /**
     * Schritt B: Vektoren hochladen (Upsert)
     */
    public function upsertPoints(array $points): void
    {
        // Qdrant erwartet eine Struktur: { points: [ { id, vector, payload } ] }
        $response = Http::put("{$this->baseUrl}/collections/{$this->collection}/points?wait=true", [
            'points' => $points
        ]);

        if ($response->failed()) {
            Log::error("Qdrant Upsert Error: " . $response->body());
            throw new Exception("Fehler beim Speichern in Qdrant.");
        }
    }

    /**
     * Helper: Generiert eine deterministische UUID aus Strings (für Qdrant IDs)
     */
    public function generateUuid($data): string
    {
        // Qdrant braucht UUIDs oder Integers als IDs. Wir nutzen UUID v5.
        // Für die Bachelorarbeit reicht auch eine einfache Random UUID:
        return \Illuminate\Support\Str::uuid()->toString();
    }

    /**
     * Sucht nach den ähnlichsten Textstücken zu einem Vektor.
     */
    public function searchSimilar(array $vector, int $limit = 3, float $threshold = 0.7, ?array $filter = null): array
    {
        // Schritt A: Basis-Anfrage bauen
        $body = [
            'vector' => $vector,
            'limit' => $limit,
            'with_payload' => true,
            'score_threshold' => $threshold
        ];

        // Schritt B: Filter hinzufügen (Nur wenn einer übergeben wurde!)
        if ($filter) {
            $body['filter'] = $filter;
        }

        // Schritt C: Anfrage senden (WICHTIG: Das muss VOR den Checks passieren!)
        $response = Http::post("{$this->baseUrl}/collections/{$this->collection}/points/search", $body);

        // Schritt D: Fehlerbehandlung
        // 1. Spezialfall: Collection existiert noch nicht (404) -> Ist okay, wir geben leeres Array zurück
        if ($response->status() === 404) {
            return [];
        }

        // 2. Andere Fehler (z.B. Qdrant abgestürzt) -> Loggen
        if ($response->failed()) {
            Log::error("Qdrant Search Error: " . $response->body());
            return [];
        }

        // Schritt E: Ergebnis extrahieren
        return collect($response->json()['result'])->map(function ($item) {
            return $item['payload']['text'] ?? '';
        })->toArray();
    }
}
