<?php

namespace App\Services\Portfolio;
class BehanceService implements PortfolioServiceInterface
{
    public function fetchProjects(string $url, int $limit = 10)
    {
        $escapedInput = escapeshellarg($url);
        $scriptPath = base_path('node-scraper/scrape-behance.js');

        try {
            $output = shell_exec("node {$scriptPath} {$escapedInput}");
            $projects = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid JSON from scraper'], 500);
            }
            $projects = collect($projects)  // ⬅️ Convert to collection
                ->groupBy('url')
                ->map(function ($group) {
                    return $group->first(fn ($item) => !empty($item['title']) || !empty($item['image'])) ?? $group->first();
                })
                ->values(); // Reset indexes

            return response()->json([
                'message' => 'Portfolio fetched successfully.',
                'count' => $projects->count(),
                'projects' => $projects,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Scraper failed: ' . $e->getMessage()], 500);
        }
    }
}