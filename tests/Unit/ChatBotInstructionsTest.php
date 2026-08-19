<?php

use App\Ai\Agents\ChatBot;
use Tests\TestCase;

uses(TestCase::class);

test('the chatbot instructions require markdown structured responses', function () {
    $instructions = (string) (new ChatBot)->instructions();

    expect($instructions)->toContain('Structure toujours tes réponses en Markdown');
});

test('the chatbot instructions forbid raw html output', function () {
    $instructions = (string) (new ChatBot)->instructions();

    expect($instructions)->toContain('N\'écris jamais de HTML brut');
});
