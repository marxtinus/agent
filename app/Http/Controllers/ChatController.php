<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ChatBot;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        $activeConversationId = $this->resolveActiveConversationId($request);

        $messages = $activeConversationId
            ? ConversationMessage::query()
                ->where('conversation_id', $activeConversationId)
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'created_at'])
            : [];

        return Inertia::render('Chat', [
            'conversations' => $conversations,
            'activeConversationId' => $activeConversationId,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string', 'uuid'],
        ]);

        $user = $request->user();

        $conversationId = $data['conversation_id'] ?? null;

        if ($conversationId && Conversation::query()->whereKey($conversationId)->exists()) {
            $conversation = Conversation::query()
                ->whereKey($conversationId)
                ->where('user_id', $user->id)
                ->firstOrFail();
        } else {
            $conversation = Conversation::create([
                'id' => $conversationId ?? (string) Str::uuid7(),
                'user_id' => $user->id,
                'title' => Str::limit($data['message'], 50),
            ]);
        }

        (new ChatBot)
            ->continue($conversation->id, $user)
            ->broadcastOnQueue($data['message'], [new PrivateChannel('user.'.$user->id)]);

        return back();
    }

    private function resolveActiveConversationId(Request $request): ?string
    {
        $user = $request->user();

        if ($request->filled('conversation')) {
            $conversation = Conversation::query()
                ->whereKey($request->string('conversation'))
                ->where('user_id', $user->id)
                ->first();

            if ($conversation) {
                return $conversation->id;
            }

            abort(404);
        }

        $latestConversationId = Conversation::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->value('id');

        return is_string($latestConversationId) ? $latestConversationId : null;
    }
}
