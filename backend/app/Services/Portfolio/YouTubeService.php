<?php

namespace App\Services\Portfolio;

use Illuminate\Support\Facades\Http;

class YouTubeService implements PortfolioServiceInterface
{
    public function fetchProjects(string $url, int $limit = 10)
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $playlistId = $params['list'] ?? null;

        $apiKey = config('services.youtube.api_key');
        $url = config('services.youtube.endpoint');
        
        $response = Http::get($url, [
            'part' => 'snippet',
            'maxResults' => 25,
            'playlistId' => $playlistId,
            'key' => $apiKey,
        ]);
        if ($response->failed()) {
            return [
                'message' => 'Failed to fetch YouTube data',
                'count' => 0,
                'projects' => []
            ];
        }
        $projects = collect($response['items'])->map(function ($item) {
            $snippet = $item['snippet'];
            return [
                'title' => $snippet['title'],
                'url' => 'https://www.youtube.com/watch?v=' . $snippet['resourceId']['videoId'],
                'image' => $snippet['thumbnails']['medium']['url'] ?? ''
            ];
        })->toArray();

        return [
            'message' => 'Portfolio fetched successfully.',
            'count' => count($projects),
            'projects' => $projects
        ];
    }
}