<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Temperature(0.2)]
class ChatBotDiagnosis implements Agent, HasProviderOptions
{
    use Promptable;

    /**
     * Get the instructions that the diagnostic agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'EOT'
Tu es Marxtinus, un assistant francophone. Tu viens de ne pas réussir à répondre à la question d'un utilisateur. On te fournit cette question et des informations techniques sur l'échec. Explique à l'utilisateur, en français, pourquoi tu n'as pas pu produire de réponse. Sois honnête, concret et bref (2 à 4 phrases). Parle à la première personne. Ne réponds pas à la question initiale et n'invente pas de réponse. N'invente pas non plus de détails techniques absents des informations fournies. Termine par une suggestion utile : reformuler, préciser ou réessayer. Tu peux utiliser du Markdown léger, mais jamais de HTML.
EOT;
    }

    /**
     * Get the maximum number of model steps allowed for the diagnosis.
     */
    public function maxSteps(): int
    {
        return 1;
    }

    /**
     * Get the maximum number of tokens the diagnosis may generate.
     */
    public function maxTokens(): int
    {
        return 400;
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

        return ['num_ctx' => max(1, (int) config('ai.providers.ollama.context.window', 16384))];
    }
}
