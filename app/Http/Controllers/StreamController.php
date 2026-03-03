<?php

namespace App\Http\Controllers;

use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;

use App\Models\Room;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiResponse;
use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Storage\AvatarStorageService;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

use App\Services\AI\EmbeddingService;
use App\Services\AI\QdrantService;
use Exception;

class StreamController extends Controller
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly AiService            $aiService,
        private readonly AvatarStorageService $avatarStorage
    ){
    }

    public function handleExternalRequest(Request $request)
    {
        // Find out user model
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            // Validate request data
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            // Return detailed validation error response
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        $payload = $validatedData['payload'];

        // Handle standard response
        $response = $this->aiService->sendRequest($payload);

        // Record usage
        $this->usageAnalyzer->submitUsageRecord($response->usage, 'api');

        // Return response to client
        return response()->json([
            'success' => true,
            'content' => $response->content,
        ]);
    }

    /**
     * Handle AI connection requests using the new architecture
     */
    public function handleAiConnectionRequest(Request $request)
    {
    // PHP erlauben, 5 Minuten zu laufen
    set_time_limit(300);
        //validate payload
        try {
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.stream' => 'required|boolean',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'nullable|string',
                'payload.messages.*.content.attachments' => 'nullable|array',
                'payload.tools' => 'nullable|array',

                'broadcast' => 'required|boolean',
                'isUpdate' => 'nullable|boolean',
                'messageId' => ['nullable', function ($_, $value, $fail) {
                    if ($value !== null && !is_string($value) && !is_int($value)) {
                        $fail('The messageId must be a valid numeric string (e.g., "192.000" or "12").');
                    }
                }],
                'threadIndex' => 'nullable|int',
                'slug' => 'nullable|string',
                'key' => 'nullable|string',
            ]);

            // Ensure that nullable fields are set to default values if not provided
            foreach ($validatedData['payload']['messages'] as &$message) {
                if (isset($message['content']['text']) && !is_string($message['content']['text'])) {
                    $message['content']['text'] = '';
                }
                if (isset($message['content']['attachments']) && !is_array($message['content']['attachments'])) {
                    $message['content']['attachments'] = [];
                }
            }
            unset($message);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        if ($validatedData['broadcast']) {
            $this->handleGroupChatRequest($validatedData);
            return null;
        }

        $hawki = User::find(1); // HAWKI user
        $avatar_url = $this->avatarStorage->getUrl('profile_avatars',
                                            $hawki->username,
                                            $hawki->avatar_id);

        // 1. User-Query extrahieren (die letzte Nachricht)
        $messages = $validatedData['payload']['messages'];
        $lastMessage = end($messages);
        $userQuery = $lastMessage['content']['text'];

        // 1.5 Sicherheits-Kürzung für das Embedding-Modell (max. 1000 Zeichen für die Vektorsuche)
        $ragQuery = mb_substr($userQuery, 0, 1000);

        // 2. RAG Logik
        try {
            if (!empty(trim($userQuery))) {
                $qdrantService = new QdrantService();
                $embeddingService = new EmbeddingService($qdrantService); // Service muss public getEmbeddingFromOllama haben

                // a) Embedding der Frage
                $queryVector = $embeddingService->getEmbeddingFromOllama($userQuery);

                // b) Filter-Logik basierend auf Rolle
                $user = Auth::user();
                $filter = null;

                if ($user->employeetype === 'student') {
                    Log::channel('explainability')->info('Role-based filter applied', [
                        'user' => $user->username,
                        'role' => $user->employeetype,
                        'filter' => $filter
                    ]);
                    // Studenten sehen NUR Dinge, die explizit für sie getaggt sind (z.B. "student_material")
                    $filter = [
                        'must' => [
                            [
                                'key' => 'tag',
                                'match' => ['value' => 'student']
                            ]
                        ]
                    ];
                } else {
                    // Professoren sehen alles (kein Filter) oder spezifische Tags
                    $filter = null;
                    Log::channel('explainability')->info('No filter applied (full access)', [
                        'user' => $user->username,
                        'role' => $user->employeetype
                    ]);
                }

                // c) Suche in Qdrant mit Filter
                // HINWEIS: Sie müssen searchSimilar in QdrantService anpassen, damit es $filter akzeptiert!
                $contextResults = $qdrantService->searchSimilar($queryVector, 3, 0.4, $filter);

                //Logging
                \Illuminate\Support\Facades\Log::info("RAG Suche für User " . $user->username);
                \Illuminate\Support\Facades\Log::info("Suchbegriff Vektorisiert. Filter: " . json_encode($filter));
                \Illuminate\Support\Facades\Log::info("Gefundene Resultate: " . count($contextResults));

                // d) Prompt anreichern
                if (!empty($contextResults)) {
                    $contextString = implode("\n---\n", $contextResults);
                    Log::channel('explainability')->info('Context injected into prompt', [
                        'context_length' => strlen($contextString),
                        'context_chunks' => count($contextResults)
                    ]);

                    // Wir hängen den Kontext an die LETZTE Nachricht (die Frage des Users) an,
                    // statt eine neue System-Nachricht zu erzeugen. Das ist robuster bei verschiedenen Modellen.

                    // 1. Letzten Index finden
                    $lastMsgIndex = array_key_last($validatedData['payload']['messages']);

                    // 2. Originale Frage holen
                    $originalUserQuery = $validatedData['payload']['messages'][$lastMsgIndex]['content']['text'];

                    // 3. Neue Nachricht zusammenbauen
                    $enrichedQuery = "Nutze exklusiv folgenden Kontext aus der Wissensdatenbank, um die Frage zu beantworten:\n\n"
                                   . $contextString
                                   . "\n\n---\nFrage: " . $originalUserQuery;

                    // 4. Nachricht im Payload überschreiben
                    $validatedData['payload']['messages'][$lastMsgIndex]['content']['text'] = $enrichedQuery;
                    Log::channel('explainability')->info('Final enriched query prepared', [
                        'final_length' => strlen($enrichedQuery)
                    ]);

                    // Optional: Loggen, dass RAG erfolgreich war
                    \Illuminate\Support\Facades\Log::info("RAG: Kontext in User-Nachricht injiziert.");
                } else {
                    \Illuminate\Support\Facades\Log::info("RAG: Kein Kontext gefunden (Threshold/Filter).");
                }
            }
        } catch (Exception $e) {
            Log::warning("RAG Fehler: " . $e->getMessage());
        }

        if ($validatedData['payload']['stream']) {
            // Handle streaming response
            $this->handleStreamingRequest($validatedData['payload'], $hawki, $avatar_url);
        } else {
            // Handle standard response
            $response = $this->aiService->sendRequest($validatedData['payload']);

            $this->usageAnalyzer->submitUsageRecord($response->usage, 'private');

            // Return response to client
            return response()->json([
                'author' => [
                    'username' => $hawki->username,
                    'name' => $hawki->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $validatedData['payload']['model'],
                'isDone' => true,
                'content' => json_encode($response->content),
            ]);
        }

        return null;
    }

    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
    {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');

        $onData = function (AiResponse $response) use ($user, $avatar_url, $payload) {
            $flush = static function () {
                if (ob_get_length()) {
                    ob_flush();
                }
                flush();
            };

            $this->usageAnalyzer->submitUsageRecord(
                $response->usage,
                'private',
            );

            $messageData = [
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $payload['model'],
                'isDone' => $response->isDone,
                'content' => json_encode($response->content),
            ];

            echo json_encode($messageData) . "\n";
            $flush();
        };

        $this->aiService->sendStreamRequest($payload, $onData);
    }

    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data): void
    {
        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();

        // Broadcast initial generation status
        $generationStatus = [
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => true,
                'model' => $data['payload']['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));

        // Process the request
        $response = $this->aiService->sendRequest($data['payload']);

        // Record usage
        $this->usageAnalyzer->submitUsageRecord(
            $response->usage,
            'group',
            $room->id
        );

        $crypto = new SymmetricCrypto();
        $encryptedData = $crypto->encrypt($response->content['text'],
                                          base64_decode($data['key']));

        // Store message
        $messageHandler = MessageHandlerFactory::create('group');
        $member = $room->members()->where('user_id', 1)->firstOrFail();

        if ($isUpdate) {
            $message = $messageHandler->update($room, [
                'message_id' => $data['messageId'],
                'model' => $data['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ]
            ]);
        } else {
            $message = $messageHandler->create($room, [
                'threadId' => $data['threadIndex'],
                'member' => $member,
                'message_role'=> 'assistant',
                'model'=> $data['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ]
            ]);
        }


        $broadcastObject = [
            'slug' => $room->slug,
            'message_id'=> $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, $isUpdate)->onQueue('message_broadcast');

        // Update and broadcast final generation status
        $generationStatus = [
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => false,
                'model' => $data['payload']['model']
            ]
        ];

        broadcast(new RoomMessageEvent($generationStatus));
    }
}
