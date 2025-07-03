<?php

namespace App\Services\Portfolio;

use Illuminate\Support\Facades\Http;

class VimeoService implements PortfolioServiceInterface
{
    public function fetchProjects(string $url, int $limit = 10): array
    {
        $username = $this->extractUsername($url);

        if (!$username) {
            return [
                'message' => 'Invalid Vimeo URL.',
                'count' => 0,
                'projects' => []
            ];
        }

        $token = config('services.vimeo.token');

        // Step 1: Resolve vanity username → user ID
        $userRes = Http::withToken($token)->get("https://api.vimeo.com/users/{$username}");

        if ($userRes->failed()) {
            return [
                'message' => 'Failed to resolve Vimeo user.',
                'count' => 0,
                'projects' => []
            ];
        }

        $userUri = $userRes['uri']; // e.g. "/users/12345678"

        // Step 2: Get videos
        $videoRes = Http::withToken($token)->get("https://api.vimeo.com{$userUri}/videos", [
            'per_page' => $limit,
            'sort' => 'date',
            'direction' => 'desc',
        ]);

        if ($videoRes->failed()) {
            return [
                'message' => 'Failed to fetch Vimeo videos.',
                'count' => 0,
                'projects' => []
            ];
        }

        $projects = collect($videoRes['data'])->map(function ($video) {
            return [
                'title' => $video['name'],
                'url' => $video['link'],
                'image' => $video['pictures']['sizes'][2]['link'] ?? '',
            ];
        })->toArray();

        return [
            'message' => 'Portfolio fetched successfully.',
            'count' => count($projects),
            'projects' => $projects,
        ];
    }

    protected function extractUsername(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH); // /whenpigsfly
        return trim($path, '/');
    }
}
