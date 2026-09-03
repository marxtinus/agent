<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class VoipMsSendSms implements Tool
{
    public function __construct(protected ?object $user = null) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return "Envoyer un SMS via voip.ms. Nécessite un numéro de téléphone de destination au format E.164 et un message. Limite d'un SMS par heure par utilisateur.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $userId = $this->user?->id ?? 'guest';
        $cacheKey = "voipms:last_sent_at:{$userId}";

        if (! Cache::add($cacheKey, now()->timestamp, now()->addHour())) {
            $lastSent = (int) Cache::get($cacheKey);
            $remaining = max(1, (int) ceil(($lastSent + 3600 - now()->timestamp) / 60));

            return "Limite atteinte : un SMS par heure maximum. Réessayez dans environ {$remaining} minute(s).";
        }

        try {
            $response = Http::timeout(30)->get('https://voip.ms/api/v1/rest.php', [
                'api_username' => config('ai.voipms.username'),
                'api_password' => config('ai.voipms.password'),
                'method' => 'sendSMS',
                'did' => config('ai.voipms.did'),
                'dst' => $request->string('destination'),
                'message' => $request->string('message'),
                'content_type' => 'json',
            ]);
        } catch (\Throwable) {
            Cache::forget($cacheKey);

            return "L'envoi du SMS a échoué (erreur réseau).";
        }

        if (($response->json('status') ?? '') !== 'success') {
            Cache::forget($cacheKey);

            return "L'envoi du SMS a échoué : ".($response->json('status') ?? 'réponse inconnue');
        }

        return 'SMS envoyé avec succès.';
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'destination' => $schema
                ->string()
                ->description('Le numéro de téléphone de destination au format E.164 (ex. +15141234567).')
                ->required(),
            'message' => $schema
                ->string()
                ->description('Le contenu du message SMS à envoyer.')
                ->required(),
        ];
    }
}
