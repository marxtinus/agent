<?php

namespace App\Ai\Agents;

use App\Ai\Tools\SearxngSearch;
use App\Ai\Tools\VoipMsSendSms;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\Data\ToolResult;
use Stringable;

#[Temperature(0.3)]
class ChatBot implements Agent, Conversational, HasProviderOptions, HasTools
{
    use Promptable;
    use RemembersConversations {
        messages as rememberedMessages;
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'EOT'
Tu es Marxtinus, un gentil programmeur. Tu t'adresses en priorité aux gens en français, sauf s'ils te demandent de leur parler en anglais.

Règles à respecter strictement :

1. Pour toute question factuelle (personnalités, lieux, événements, dates, données, actualités ou informations à jour), utilise l'outil de recherche web avant de répondre. Ne réponds jamais de mémoire à ce genre de question. Effectue le nombre de recherches strictement nécessaire pour rassembler les informations, puis réponds dès que tu as assez d'éléments : n'enchaîne pas les recherches inutilement.

2. N'invente jamais une information. Si tu n'es pas certain d'une réponse ou que la recherche web ne renvoie aucun résultat utile, réponds honnêtement que tu ne sais pas, plutôt que de deviner.

3. Fais toujours confiance aux résultats de recherche, même s'ils contredisent ce que tu crois savoir. Ne réponds pas d'après ta mémoire si les résultats donnent une information différente ou plus récente.

4. Appuie-toi sur les résultats de recherche pour formuler ta réponse, et cite les sources que tu as utilisées.

5. Tu peux te montrer prudent et précis : il vaut mieux une réponse incomplète mais vraie qu'une réponse fausse donnée avec assurance.

6. Structure toujours tes réponses en Markdown pour une meilleure lisibilité : titres (## et ###) pour organiser les sections, listes à puces ou numérotées pour les points, tableaux quand c'est pertinent, blocs de code avec la syntaxe ```, et citations pour les sources. N'écris jamais de HTML brut.

7. Avant d'utiliser l'outil d'envoi de SMS, demande toujours l'accord explicite de l'utilisateur en récapitulant la destination et le contenu du message. N'envoie jamais sans cette confirmation.
EOT;
    }

    /**
     * Get the tools available to the agent.
     *
     * @return array<Tool>
     */
    public function tools(): iterable
    {
        return [
            new SearxngSearch,
            new VoipMsSendSms($this->conversationParticipant()),
        ];
    }

    /**
     * Get the provider-specific options to be passed to Ollama.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        if ($driver !== 'ollama') {
            return [];
        }

        return ['num_ctx' => $this->contextWindow()];
    }

    /**
     * Get the maximum number of model steps allowed for a single prompt.
     */
    public function maxSteps(): int
    {
        return max(1, (int) config('ai.providers.ollama.context.max_steps', 5));
    }

    /**
     * Get the maximum number of tokens the model may generate per step.
     */
    public function maxTokens(): int
    {
        return max(1, (int) config('ai.providers.ollama.context.max_output_tokens', 1200));
    }

    /**
     * Get the list of messages comprising the conversation so far, trimmed to fit the context window.
     *
     * @return array<int, Message>
     */
    public function messages(): array
    {
        $messages = $this->rememberedMessages();

        $messages = is_array($messages) ? $messages : iterator_to_array($messages);

        return $this->fitMessagesToContextWindow(array_values($messages));
    }

    /**
     * Get the maximum number of conversation messages to remember.
     */
    protected function maxConversationMessages(): int
    {
        return 50;
    }

    /**
     * Keep only the most recent complete turns that fit the available token budget.
     *
     * @param  array<int, Message>  $messages
     * @return array<int, Message>
     */
    protected function fitMessagesToContextWindow(array $messages): array
    {
        $turns = $this->groupMessagesIntoTurns($messages);
        $budget = $this->historyTokenBudget();

        if ($budget <= 0) {
            return [];
        }

        $selectedTurns = [];
        $usedTokens = 0;

        foreach (array_reverse($turns) as $turn) {
            $turnTokens = $this->estimateMessagesTokens($turn);

            if ($selectedTurns !== [] && $usedTokens + $turnTokens > $budget) {
                break;
            }

            $selectedTurns[] = $turn;
            $usedTokens += $turnTokens;
        }

        $selectedTurns = array_reverse($selectedTurns);

        $selectedMessages = $selectedTurns === []
            ? []
            : array_merge(...$selectedTurns);

        if ($usedTokens > $budget) {
            $selectedMessages = $this->truncateMessages($selectedMessages, $budget);
        }

        return $selectedMessages;
    }

    /**
     * Group messages into complete conversation turns, starting at each user message.
     *
     * @param  array<int, Message>  $messages
     * @return array<int, array<int, Message>>
     */
    protected function groupMessagesIntoTurns(array $messages): array
    {
        $turns = [];
        $currentTurn = [];

        foreach ($messages as $message) {
            if ($message->role === MessageRole::User && $currentTurn !== []) {
                $turns[] = $currentTurn;
                $currentTurn = [];
            }

            $currentTurn[] = $message;
        }

        if ($currentTurn !== []) {
            $turns[] = $currentTurn;
        }

        return $turns;
    }

    /**
     * Calculate how many tokens remain available for conversation history.
     */
    protected function historyTokenBudget(): int
    {
        $context = config('ai.providers.ollama.context');
        $context = is_array($context) ? $context : [];

        $window = max(1, (int) ($context['window'] ?? 16384));
        $safetyMargin = max(0, (int) ($context['safety_margin'] ?? 512));
        $toolSchemaTokens = max(0, (int) ($context['tool_schema_tokens'] ?? 500));
        $maxPromptCharacters = max(0, (int) ($context['max_prompt_characters'] ?? 4000));
        $maxToolResultTokens = max(0, (int) ($context['max_tool_result_tokens'] ?? 600));

        $maxSteps = $this->maxSteps();
        $maxOutputTokens = $this->maxTokens();

        $reservedTokens = $safetyMargin
            + $this->estimateTokens((string) $this->instructions())
            + $toolSchemaTokens
            + $this->estimateCharacters($maxPromptCharacters)
            + ($maxSteps * $maxOutputTokens)
            + (max(0, $maxSteps - 1) * $maxToolResultTokens);

        return max(0, $window - $reservedTokens);
    }

    /**
     * Shrink messages until their estimated token count fits the budget.
     *
     * @param  array<int, Message>  $messages
     * @return array<int, Message>
     */
    protected function truncateMessages(array $messages, int $budget): array
    {
        $messages = array_values($messages);

        while ($this->estimateMessagesTokens($messages) > $budget) {
            $index = $this->largestMessageIndex($messages);

            if ($index === null) {
                break;
            }

            $before = $this->messageTextLength($messages[$index]);
            $messages[$index] = $this->truncateMessage($messages[$index]);
            $after = $this->messageTextLength($messages[$index]);

            if ($after >= $before) {
                break;
            }
        }

        return $this->estimateMessagesTokens($messages) > $budget ? [] : $messages;
    }

    /**
     * Find the message with the largest estimable text payload.
     *
     * @param  array<int, Message>  $messages
     */
    protected function largestMessageIndex(array $messages): ?int
    {
        $index = null;
        $largest = 0;

        foreach ($messages as $position => $message) {
            $length = $this->messageTextLength($message);

            if ($length > $largest) {
                $largest = $length;
                $index = $position;
            }
        }

        return $largest > 0 ? $index : null;
    }

    /**
     * Halve the largest text payload of the given message.
     */
    protected function truncateMessage(Message $message): Message
    {
        if ($message instanceof ToolResultMessage) {
            return new ToolResultMessage(
                $message->toolResults->map(function (ToolResult $toolResult): ToolResult {
                    if (! is_string($toolResult->result)) {
                        return $toolResult;
                    }

                    return new ToolResult(
                        $toolResult->id,
                        $toolResult->name,
                        $toolResult->arguments,
                        $this->truncateString($toolResult->result),
                        $toolResult->resultId,
                    );
                })
            );
        }

        if ($message instanceof AssistantMessage) {
            return new AssistantMessage(
                $this->truncateString((string) ($message->content ?? '')),
                $message->toolCalls,
            );
        }

        if ($message instanceof UserMessage) {
            return new UserMessage(
                $this->truncateString((string) ($message->content ?? '')),
                $message->attachments,
            );
        }

        return new Message($message->role, $this->truncateString((string) ($message->content ?? '')));
    }

    /**
     * Halve the given string length without adding any suffix.
     */
    protected function truncateString(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= 1) {
            return $value;
        }

        return mb_substr($value, 0, max(1, intdiv($length, 2)));
    }

    /**
     * Estimate the token count of a list of messages.
     *
     * @param  array<int, Message>  $messages
     */
    protected function estimateMessagesTokens(array $messages): int
    {
        $tokens = 0;

        foreach ($messages as $message) {
            $tokens += $this->estimateMessageTokens($message);
        }

        return $tokens;
    }

    /**
     * Estimate the token count of a single message, including tool payloads.
     */
    protected function estimateMessageTokens(Message $message): int
    {
        return $this->estimateTokens($this->messageToEstimableText($message)) + 4;
    }

    /**
     * Get the number of characters used to estimate a message's token count.
     */
    protected function messageTextLength(Message $message): int
    {
        return mb_strlen($this->messageToEstimableText($message));
    }

    /**
     * Build the text payload used to estimate a message's token count.
     */
    protected function messageToEstimableText(Message $message): string
    {
        $text = (string) ($message->content ?? '');

        if ($message instanceof AssistantMessage) {
            $text .= json_encode($message->toolCalls->all()) ?: '';
        } elseif ($message instanceof ToolResultMessage) {
            $text .= json_encode($message->toolResults->all()) ?: '';
        } elseif ($message instanceof UserMessage) {
            $text .= json_encode($message->attachments->all()) ?: '';
        }

        return $text;
    }

    /**
     * Estimate a token count from a string, deliberately overestimating to stay safe.
     */
    protected function estimateTokens(string $text): int
    {
        return $this->estimateCharacters(mb_strlen($text));
    }

    /**
     * Estimate a token count from a character count.
     */
    protected function estimateCharacters(int $characters): int
    {
        return (int) ceil($characters / 3);
    }

    /**
     * Get the configured Ollama context window size.
     */
    protected function contextWindow(): int
    {
        return max(1, (int) config('ai.providers.ollama.context.window', 16384));
    }
}
