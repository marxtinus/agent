<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearxngSearch implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Search the web and return the top 3 relevant results with their titles, URLs and content.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $baseUrl = config('ai.searxngUrl');

        $response = Http::timeout(30)->get("{$baseUrl}/search", [
            'q' => $request->string('query'),
            'format' => 'json',
        ]);

        if ($response->failed()) {
            return 'The web search failed.';
        }

        $results = array_slice($response->json('results', []), 0, 3);

        if ($results === []) {
            return 'No results found for the query.';
        }

        $formatted = collect($results)->map(fn (array $result): string => sprintf(
            "Title: %s\nURL: %s\nContent: %s",
            $result['title'] ?? 'Untitled',
            $result['url'] ?? '',
            trim(strip_tags($result['content'] ?? '')),
        ));

        return "Here are the top 3 web search results:\n\n".$formatted->implode("\n\n");
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema
                ->string()
                ->description('The search query.')
                ->required(),
        ];
    }
}
