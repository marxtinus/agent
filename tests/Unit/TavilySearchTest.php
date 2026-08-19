<?php

use App\Ai\Tools\TavilySearch;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

uses(TestCase::class);

test('it returns the top 3 formatted results from tavily', function () {
    config()->set('ai.tavilyKey', 'test-key');

    Http::fake([
        'api.tavily.com/*' => Http::response([
            'results' => [
                ['title' => 'Laravel', 'url' => 'https://laravel.com', 'content' => 'The PHP framework for web artisans.'],
                ['title' => 'Second', 'url' => 'https://example.com/2', 'content' => 'Second result content.'],
                ['title' => 'Third', 'url' => 'https://example.com/3', 'content' => 'Third result content.'],
            ],
        ]),
    ]);

    $tool = new TavilySearch;

    $output = $tool->handle(new Request(['query' => 'Laravel']));

    Http::assertSent(fn ($request) => $request->url() === 'https://api.tavily.com/search'
        && $request['query'] === 'Laravel'
        && $request['max_results'] === 3
        && $request['api_key'] === 'test-key');

    expect($output)->toContain('Here are the top 3 web search results:')
        ->toContain('Laravel')
        ->toContain('https://laravel.com')
        ->toContain('The PHP framework for web artisans.');
});

test('it returns a message when no results are found', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response(['results' => []]),
    ]);

    $tool = new TavilySearch;

    $output = $tool->handle(new Request(['query' => 'nothing']));

    expect($output)->toBe('No results found for the query.');
});

test('it returns a message when the tavily request fails', function () {
    Http::fake([
        'api.tavily.com/*' => Http::response([], 500),
    ]);

    $tool = new TavilySearch;

    $output = $tool->handle(new Request(['query' => 'boom']));

    expect($output)->toBe('The web search failed.');
});

test('it exposes a required query parameter in its schema', function () {
    $tool = new TavilySearch;

    $schema = $tool->schema(new \Illuminate\JsonSchema\JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('query')
        ->and($schema['query']->toArray()['type'])->toBe('string');
});
