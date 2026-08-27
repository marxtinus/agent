<?php

use App\Ai\Tools\SearxngSearch;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

uses(TestCase::class);

test('it returns the top 3 formatted results from searxng', function () {
    config()->set('ai.searxngUrl', 'http://localhost:30053');

    Http::fake([
        'http://localhost:30053/*' => Http::response([
            'results' => [
                ['title' => 'Laravel', 'url' => 'https://laravel.com', 'content' => 'The PHP framework for web artisans.'],
                ['title' => 'Second', 'url' => 'https://example.com/2', 'content' => 'Second result content.'],
                ['title' => 'Third', 'url' => 'https://example.com/3', 'content' => 'Third result content.'],
                ['title' => 'Fourth', 'url' => 'https://example.com/4', 'content' => 'Should be truncated.'],
            ],
        ]),
    ]);

    $tool = new SearxngSearch;

    $output = $tool->handle(new Request(['query' => 'Laravel']));

    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'http://localhost:30053/search')
        && $request['q'] === 'Laravel'
        && $request['format'] === 'json');

    expect($output)->toContain('Here are the top 3 web search results:')
        ->toContain('Laravel')
        ->toContain('https://laravel.com')
        ->toContain('The PHP framework for web artisans.')
        ->not->toContain('Should be truncated');
});

test('it returns a message when no results are found', function () {
    config()->set('ai.searxngUrl', 'http://localhost:30053');

    Http::fake([
        'http://localhost:30053/*' => Http::response(['results' => []]),
    ]);

    $tool = new SearxngSearch;

    $output = $tool->handle(new Request(['query' => 'nothing']));

    expect($output)->toBe('No results found for the query.');
});

test('it returns a message when the searxng request fails', function () {
    config()->set('ai.searxngUrl', 'http://localhost:30053');

    Http::fake([
        'http://localhost:30053/*' => Http::response([], 500),
    ]);

    $tool = new SearxngSearch;

    $output = $tool->handle(new Request(['query' => 'boom']));

    expect($output)->toBe('The web search failed.');
});

test('it exposes a required query parameter in its schema', function () {
    $tool = new SearxngSearch;

    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)
        ->toHaveKey('query')
        ->and($schema['query']->toArray()['type'])->toBe('string');
});
