<?php

use App\Ai\Tools\VoipMsSendSms;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

uses(TestCase::class);

$user = fn (int $id) => new class($id)
{
    public function __construct(public int $id) {}
};

test('it sends an sms via voip.ms and records the send', function () use ($user) {
    config()->set('ai.voipms', [
        'username' => 'account@example.com',
        'password' => 'secret',
        'did' => '14501234567',
    ]);

    Http::fake([
        'voip.ms/api/v1/rest.php*' => Http::response(['status' => 'success', 'sms' => ['id' => '123']]),
    ]);

    $tool = new VoipMsSendSms($user(1));
    $output = $tool->handle(new Request(['destination' => '+15145551234', 'message' => 'Bonjour !']));

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://voip.ms/api/v1/rest.php')
        && $request['method'] === 'sendSMS'
        && $request['api_username'] === 'account@example.com'
        && $request['api_password'] === 'secret'
        && $request['did'] === '14501234567'
        && $request['dst'] === '+15145551234'
        && $request['message'] === 'Bonjour !'
        && $request['content_type'] === 'json');

    expect($output)->toBe('SMS envoyé avec succès.')
        ->and(Cache::has('voipms:last_sent_at:1'))->toBeTrue();
});

test('it limits to one sms per hour per user', function () use ($user) {
    config()->set('ai.voipms', [
        'username' => 'account@example.com',
        'password' => 'secret',
        'did' => '14501234567',
    ]);

    Http::fake([
        'voip.ms/api/v1/rest.php*' => Http::response(['status' => 'success']),
    ]);

    $tool = new VoipMsSendSms($user(1));

    expect($tool->handle(new Request(['destination' => '+15145551234', 'message' => 'Premier'])))
        ->toBe('SMS envoyé avec succès.');

    $output = $tool->handle(new Request(['destination' => '+15145559999', 'message' => 'Deuxième']));

    expect($output)->toContain('Limite atteinte')->toContain('Réessayez');

    Http::assertSentCount(1);
});

test('it applies the rate limit per user', function () use ($user) {
    config()->set('ai.voipms', [
        'username' => 'account@example.com',
        'password' => 'secret',
        'did' => '14501234567',
    ]);

    Http::fake([
        'voip.ms/api/v1/rest.php*' => Http::response(['status' => 'success']),
    ]);

    $userA = new VoipMsSendSms($user(1));

    expect($userA->handle(new Request(['destination' => '+15145551234', 'message' => 'A'])))
        ->toBe('SMS envoyé avec succès.');

    $userB = new VoipMsSendSms($user(2));

    expect($userB->handle(new Request(['destination' => '+15145559999', 'message' => 'B'])))
        ->toBe('SMS envoyé avec succès.');

    Http::assertSentCount(2);
});

test('it releases the rate limit when the api fails', function () use ($user) {
    config()->set('ai.voipms', [
        'username' => 'account@example.com',
        'password' => 'secret',
        'did' => '14501234567',
    ]);

    Http::fake([
        'voip.ms/api/v1/rest.php*' => Http::sequence()
            ->push(['status' => 'invalid_did'])
            ->push(['status' => 'success']),
    ]);

    $tool = new VoipMsSendSms($user(1));

    expect($tool->handle(new Request(['destination' => '+15145551234', 'message' => 'Essai'])))
        ->toBe("L'envoi du SMS a échoué : invalid_did")
        ->and(Cache::has('voipms:last_sent_at:1'))->toBeFalse();

    expect($tool->handle(new Request(['destination' => '+15145551234', 'message' => 'Réessai'])))
        ->toBe('SMS envoyé avec succès.');

    Http::assertSentCount(2);
});

test('it exposes destination and message parameters in its schema', function () {
    $tool = new VoipMsSendSms;

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('destination')
        ->toHaveKey('message')
        ->and($schema['destination']->toArray()['type'])->toBe('string')
        ->and($schema['message']->toArray()['type'])->toBe('string');
});
