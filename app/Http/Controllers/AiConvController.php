<?php

namespace App\Http\Controllers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Attachment\AttachmentService;
use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Storage\FileStorageService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiConvController extends Controller
{
    protected $aiConvService;
    protected $messageHandler;
    protected $contentValidator;
    protected $attachmentService;

    public function __construct(AttachmentService $attachmentService, AiConvService $aiConvService)
    {
        $this->aiConvService = $aiConvService;
        $this->messageHandler = app(MessageHandlerFactory::class)->create('private');
        $this->contentValidator = new MessageContentValidator();
        $this->attachmentService = $attachmentService;
    }

    public function create(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'conv_name' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string'
        ]);
        $conv = $this->aiConvService->create($validatedData);
        return response()->json(['success' => true, 'conv' => $conv], 201);
    }

    public function load($slug): JsonResponse
    {
        $convData = $this->aiConvService->load($slug);
        return response()->json(['success' => true, 'data' => $convData]);
    }

    public function update(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate(['system_prompt' => 'string']);
        $this->aiConvService->update($validatedData, $slug);
        return response()->json(['success' => true, 'response' => "Info updated successfully"]);
    }

    public function delete($slug): JsonResponse
    {
        $this->aiConvService->delete($slug);
        return response()->json(['success' => true, 'message' => 'Conv deleted successfully']);
    }

    public function sendMessage(Request $request, $slug, MessageContentValidator $contentValidator): JsonResponse
    {
        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'threadId' => 'required|integer|min:0',
            'content' => 'required|array',
            'model' => 'string',
            'completion' => 'required|boolean',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);
        $conv = AiConv::where('slug', $slug)->firstOrFail();
        $message = $this->messageHandler->create($conv, $validatedData);
        $messageData = $message->createMessageObject();
        return response()->json(['success' => true, 'messageData'=> $messageData]);
    }

    public function updateMessage(Request $request, $slug, MessageContentValidator $contentValidator): JsonResponse
    {
        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'content' => 'required|array',
            'model' => 'nullable|string',
            'completion' => 'required|boolean',
            'message_id' => 'required|string',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);
        $conv = AiConv::where('slug', $slug)->firstOrFail();
        $message = $this->messageHandler->update($conv, $validatedData);
        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');
        return response()->json(['success' => true, 'messageData' => $messageData]);
    }

    public function deleteMessage(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate(["message_id" => 'required|string|size:5']);
        $conv = AiConv::where('slug', $slug)->first();
        $this->messageHandler->delete($conv, $validatedData);
        return response()->json(['success'=> true]);
    }

    // --- HIER BEGINNT IHRE ANPASSUNG ---

    public function storeAttachment(Request $request): JsonResponse
    {
        // 1. Sicherheits-Check (Professor Only)
        if ($request->user()->employeetype !== 'professor') {
            return response()->json([
                'message' => 'Uploads sind nur für Dozenten erlaubt.'
            ], 403);
        }

        // 2. Standard-Logik
        $validateData = $request->validate([
            'file' => 'required|file|max:20480'
        ]);
        $result = $this->attachmentService->store($validateData['file'], 'private');
        return response()->json($result);
    }

    public function getAttachmentUrl(Request $request, string $uuid): JsonResponse
    {
        $attachment = Attachment::where('uuid', $uuid)->firstOrFail();
        if($attachment->user->isNot(Auth::user())){
            throw new AuthorizationException();
        }
        $url = $this->attachmentService->getFileUrl($attachment, null);
        return response()->json(['success' => true, 'url' => $url]);
    }

    public function downloadAttachment(string $uuid, string $path)
    {
        try {
            $attachment = Attachment::where('uuid', $uuid)->firstOrFail();
            if($attachment->user->isNot(Auth::user())){
                throw new AuthorizationException();
            }
            $storageService = app(FileStorageService::class);
            $stream = $storageService->streamFromSignedPath($path);
            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);
            }, $attachment->filename, ['Content-Type' => $attachment->mime]);
        } catch (FileNotFoundException $e) {
            abort(404, 'File not found');
        }
    }

    public function deleteAttachment(Request $request): JsonResponse
    {
        $validateData = $request->validate(['fileId' => 'required|string']);
        try{
            $attachment = Attachment::where('uuid', $validateData['fileId'])->firstOrFail();
            if ($attachment->user && !$attachment->user->is(Auth::user())) {
                throw new AuthorizationException();
            }
            if (!$attachment->attachable instanceof AiConvMsg) {
                return response()->json(['success'=> false, 'err'=> 'File Id mismatch'], 500);
            }
            $result = $this->attachmentService->delete($attachment);
            return response()->json(["success" => $result]);
        } catch(Exception $e) {
            Log::error($e);
            throw $e;
        }
    }
}
