<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EmbeddingService
{
    protected $ollamaBaseUrl;
    protected $qdrantService;

    public function __construct(QdrantService $qdrantService)
    {
        // Wir nutzen den Docker-internen Hostnamen "ollama"
        $this->ollamaBaseUrl = 'http://ollama:11434';
        $this->qdrantService = $qdrantService;
    }

    /**
     * Hauptfunktion: Text -> Vektor -> Qdrant
     */
    public function processFileAndUpload(string $text, string $fileId, string $tag): void
    {
        // 1. Sicherstellen, dass Qdrant bereit ist
        $this->qdrantService->ensureCollectionExists();

        // 2. Text zerteilen (Chunking)
        $chunks = $this->chunkText($text);
        $points = [];

        foreach ($chunks as $index => $chunk) {
            try {
                // 3. Vektor von Ollama holen
                $vector = $this->getEmbeddingFromOllama($chunk);

                // 4. Datenpaket für Qdrant schnüren
                $points[] = [
                    'id' => $this->qdrantService->generateUuid($fileId . $index),
                    'vector' => $vector,
                    'payload' => [
                        'text' => $chunk, // Der eigentliche Inhalt
                        'source_file' => $fileId, // Referenz zur Datei
                        'tag' => $tag, // Ihr Tag (z.B. "Modul 1")
                        'chunk_index' => $index
                    ]
                ];
            } catch (Exception $e) {
                Log::error("Fehler beim Embedden von Chunk $index: " . $e->getMessage());
            }
        }

        // 5. Alles auf einmal hochladen (Batch Upload ist schneller)
        if (!empty($points)) {
            $this->qdrantService->upsertPoints($points);
            Log::info("Erfolgreich " . count($points) . " Chunks in Qdrant gespeichert.");
        }
    }

    /**
     * Macht einen Text zu Vektoren (Public für den StreamController)
     */
    public function getEmbeddingFromOllama(string $text): array
    {
        // Modellname aus der Config oder Fallback
        $model = env('EMBEDDING_MODEL', 'nomic-embed-text');

        $response = Http::timeout(300)->post("{$this->ollamaBaseUrl}/api/embeddings", [
            'model' => $model,
            'prompt' => $text,
        ]);

        if ($response->failed()) {
            throw new Exception("Ollama Embedding Error: " . $response->body());
        }

        return $response->json()['embedding'];
    }

    /**
     * Hilfsfunktion: Text in kleinere Stücke teilen
     */
    protected function chunkText(string $text, int $chunkSize = 800): array
    {
        return str_split($text, $chunkSize);
    }
}
