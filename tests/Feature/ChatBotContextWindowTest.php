<?php

use App\Ai\Agents\ChatBot;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * @param  array<string, int>  $overrides
 */
function configureChatBotContext(array $overrides = []): void
{
    config()->set('ai.providers.ollama.context', array_merge([
        'window' => 4000,
        'safety_margin' => 0,
        'max_output_tokens' => 500,
        'max_steps' => 2,
        'max_prompt_characters' => 600,
        'tool_schema_tokens' => 0,
        'max_tool_result_tokens' => 100,
    ], $overrides));
}

function createChatBotContextConversation(User $user): Conversation
{
    return Conversation::create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Contexte',
    ]);
}

/**
 * @param  array<int, array<string, mixed>>  $toolCalls
 * @param  array<int, array<string, mixed>>  $toolResults
 */
function storeChatBotContextMessage(
    string $conversationId,
    int $userId,
    int $sequence,
    string $role,
    string $content = '',
    array $toolCalls = [],
    array $toolResults = [],
): void {
    ConversationMessage::create([
        'id' => sprintf('00000000-0000-7000-8000-%012d', $sequence),
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'agent' => ChatBot::class,
        'role' => $role,
        'content' => $content,
        'attachments' => [],
        'tool_calls' => $toolCalls,
        'tool_results' => $toolResults,
        'usage' => [],
        'meta' => [],
    ]);
}

test('it sends the configured context window to ollama', function () {
    config()->set('ai.providers.ollama.context.window', 16384);

    $bot = new ChatBot;

    expect($bot->providerOptions('ollama'))->toBe(['num_ctx' => 16384])
        ->and($bot->providerOptions(Lab::Ollama))->toBe(['num_ctx' => 16384]);
});

test('it does not send ollama options to other providers', function () {
    expect((new ChatBot)->providerOptions('openai'))->toBe([]);
});

test('it reads the step and output limits from the configuration', function () {
    config()->set('ai.providers.ollama.context.max_steps', 4);
    config()->set('ai.providers.ollama.context.max_output_tokens', 900);

    $bot = new ChatBot;

    expect($bot->maxSteps())->toBe(4)
        ->and($bot->maxTokens())->toBe(900);
});

test('it drops the oldest turns to stay within the context budget', function () {
    configureChatBotContext();

    $user = User::factory()->create();
    $conversation = createChatBotContextConversation($user);
    $sequence = 1;

    foreach (['Premier', 'Deuxieme', 'Troisieme'] as $label) {
        $content = $label.' '.str_repeat('x', 1180);

        storeChatBotContextMessage($conversation->id, $user->id, $sequence++, 'user', $content);
        storeChatBotContextMessage($conversation->id, $user->id, $sequence++, 'assistant', $content);
    }

    $bot = (new ChatBot)->continue($conversation->id, $user);
    $messages = array_values($bot->messages());

    $budget = (new ReflectionMethod(ChatBot::class, 'historyTokenBudget'))->invoke($bot);
    $estimatedTokens = (new ReflectionMethod(ChatBot::class, 'estimateMessagesTokens'))->invoke($bot, $messages);

    expect($messages)->toHaveCount(4)
        ->and($messages[0]->content)->toContain('Deuxieme')
        ->and($messages[1]->content)->toContain('Deuxieme')
        ->and($messages[2]->content)->toContain('Troisieme')
        ->and($messages[3]->content)->toContain('Troisieme')
        ->and($estimatedTokens)->toBeLessThanOrEqual($budget);
});

test('it truncates an oversized tool result to fit the context budget', function () {
    configureChatBotContext();

    $user = User::factory()->create();
    $conversation = createChatBotContextConversation($user);

    storeChatBotContextMessage($conversation->id, $user->id, 1, 'user', 'Fais une recherche');
    storeChatBotContextMessage(
        $conversation->id,
        $user->id,
        2,
        'assistant',
        '',
        toolCalls: [
            ['id' => 'call-1', 'name' => 'SearxngSearch', 'arguments' => ['query' => 'Laravel'], 'result_id' => 'call-1'],
        ],
        toolResults: [
            [
                'id' => 'call-1',
                'name' => 'SearxngSearch',
                'arguments' => ['query' => 'Laravel'],
                'result' => str_repeat('resultat volumineux ', 10000),
                'result_id' => 'call-1',
            ],
        ],
    );

    $bot = (new ChatBot)->continue($conversation->id, $user);
    $messages = array_values($bot->messages());

    $budget = (new ReflectionMethod(ChatBot::class, 'historyTokenBudget'))->invoke($bot);
    $estimatedTokens = (new ReflectionMethod(ChatBot::class, 'estimateMessagesTokens'))->invoke($bot, $messages);

    $toolResultMessage = null;

    foreach ($messages as $message) {
        if ($message instanceof ToolResultMessage) {
            $toolResultMessage = $message;

            break;
        }
    }

    expect($toolResultMessage)->not->toBeNull()
        ->and($toolResultMessage->role)->toBe(MessageRole::ToolResult)
        ->and(mb_strlen($toolResultMessage->toolResults->first()->result))->toBeLessThan(10000)
        ->and($estimatedTokens)->toBeLessThanOrEqual($budget);
});

test('it never returns an orphan tool result after trimming', function () {
    configureChatBotContext(['window' => 2000]);

    $user = User::factory()->create();
    $conversation = createChatBotContextConversation($user);

    storeChatBotContextMessage($conversation->id, $user->id, 1, 'user', 'Ancien message '.str_repeat('x', 1200));
    storeChatBotContextMessage(
        $conversation->id,
        $user->id,
        2,
        'assistant',
        '',
        toolCalls: [
            ['id' => 'call-ancien', 'name' => 'SearxngSearch', 'arguments' => ['query' => 'Laravel'], 'result_id' => 'call-ancien'],
        ],
        toolResults: [
            ['id' => 'call-ancien', 'name' => 'SearxngSearch', 'arguments' => ['query' => 'Laravel'], 'result' => 'Ancien resultat', 'result_id' => 'call-ancien'],
        ],
    );
    storeChatBotContextMessage($conversation->id, $user->id, 3, 'user', 'Nouveau message '.str_repeat('x', 1180));
    storeChatBotContextMessage($conversation->id, $user->id, 4, 'assistant', 'Nouveau resultat '.str_repeat('x', 1180));

    $messages = array_values((new ChatBot)->continue($conversation->id, $user)->messages());

    $roles = array_map(fn ($message) => $message->role->value, $messages);

    expect($roles[0])->toBe('user')
        ->and($roles)->not->toContain('tool_result')
        ->and($messages[0]->content)->toContain('Nouveau message');
});
