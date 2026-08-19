<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TavilySearch implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Search the web using Tavily and return the top 3 relevant results with their titles, URLs and content.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $response = Http::timeout(30)
            ->post('https://api.tavily.com/search', [
                'api_key' => config('ai.tavilyKey'),
                'query' => $request->string('query'),
                'max_results' => 3,
            ]);

        if ($response->failed()) {
            return 'The web search failed.';
        }

        $results = collect($response->json('results'));

        if ($results->isEmpty()) {
            return 'No results found for the query.';
        }

        $formatted = $results->map(fn (array $result): string => sprintf(
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
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
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
