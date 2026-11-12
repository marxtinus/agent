<?php

use App\Ai\Agents\ChatBot;
use App\Ai\Agents\ChatBotDiagnosis;
use App\Jobs\BroadcastChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

function createChatFailureConversation(User $user): Conversation
{
    return Conversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Diagnostic',
    ]);
}

/**
 * @param  array<int, Channel>  $channels
 */
function broadcastChatFailureMessage(
    User $user,
    Conversation $conversation,
    string $prompt,
    array $channels = [],
): void {
    $channels = $channels === [] ? [new PrivateChannel('user.'.$user->id)] : $channels;

    (new BroadcastChatMessage(
        (new ChatBot)->continue($conversation->id, $user),
        $prompt,
        $channels,
        $conversation->id,
        $user->id,
    ))->handle();
}

test('it asks ollama to explain an empty response and stores the explanation', function () {
    Ai::fakeAgent(ChatBot::class, ['']);
    Ai::fakeAgent(ChatBotDiagnosis::class, ["Je n'ai pas pu répondre car ma recherche n'a rien donné."]);

    $user = User::factory()->create();
    $conversation = createChatFailureConversation($user);

    broadcastChatFailureMessage($user, $conversation, 'Quelle est la capitale du Zorblax ?');

    $assistant = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->orderByDesc('id')
        ->first();

    expect($assistant)->not->toBeNull();

    expect($assistant?->getAttribute('content'))
        ->toBe("Je n'ai pas pu répondre car ma recherche n'a rien donné.");

    Ai::assertAgentWasPrompted(
        ChatBotDiagnosis::class,
        fn ($prompt) => str_contains($prompt->prompt, 'capitale du Zorblax'),
    );
});

test('it does not ask for an explanation when the response is not empty', function () {
    Ai::fakeAgent(ChatBot::class, ['Bonjour, je suis Marxtinus.']);
    Ai::fakeAgent(ChatBotDiagnosis::class, ['Cette explication ne doit pas être utilisée.']);

    $user = User::factory()->create();
    $conversation = createChatFailureConversation($user);

    broadcastChatFailureMessage($user, $conversation, 'Bonjour !');

    $assistant = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->orderByDesc('id')
        ->first();

    expect($assistant)->not->toBeNull();

    expect($assistant?->getAttribute('content'))->toBe('Bonjour, je suis Marxtinus.');

    Ai::assertAgentNeverPrompted(ChatBotDiagnosis::class);
});

test('it falls back to a deterministic explanation when the diagnosis is empty', function () {
    Ai::fakeAgent(ChatBot::class, ['']);
    Ai::fakeAgent(ChatBotDiagnosis::class, ['']);

    $user = User::factory()->create();
    $conversation = createChatFailureConversation($user);

    broadcastChatFailureMessage($user, $conversation, 'Une question sans réponse ?');

    $assistant = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', 'assistant')
        ->orderByDesc('id')
        ->first();

    expect($assistant)->not->toBeNull();

    expect($assistant?->getAttribute('content'))
        ->toContain("Je n'ai pas pu produire de réponse à ta question.")
        ->toContain("Le modèle n'a renvoyé aucun texte.");
});

test('it explains a failed stream and stores both messages', function () {
    Ai::fakeAgent(ChatBotDiagnosis::class, ["Je n'ai pas pu joindre le service. Réessaie dans un instant."]);

    $user = User::factory()->create();
    $conversation = createChatFailureConversation($user);

    $job = new BroadcastChatMessage(
        (new ChatBot)->continue($conversation->id, $user),
        'Ma question importante',
        [new PrivateChannel('user.'.$user->id)],
        $conversation->id,
        $user->id,
    );

    $job->failed(new RuntimeException('Connection refused'));

    $messages = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->orderBy('id')
        ->get();

    expect($messages)->toHaveCount(2);

    $userMessage = $messages->first();
    $assistantMessage = $messages->last();

    expect($userMessage?->getAttribute('role'))->toBe('user');
    expect($userMessage?->getAttribute('content'))->toBe('Ma question importante');
    expect($assistantMessage?->getAttribute('role'))->toBe('assistant');
    expect($assistantMessage?->getAttribute('content'))
        ->toBe("Je n'ai pas pu joindre le service. Réessaie dans un instant.");
});
