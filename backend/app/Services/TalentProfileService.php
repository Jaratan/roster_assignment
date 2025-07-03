<?php

namespace App\Services;

use App\Models\TalentProfile;
use App\Models\TrustedClient;
use App\Models\MyWork;
use App\Models\Video;
use App\Models\Testimonial;
use App\Models\Expertise;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Helpers\HttpHelper;
use Symfony\Component\Panther\Client;

class TalentProfileService
{
    private $cohereApiKey;

    public function __construct()
    {
        $this->cohereApiKey = env('COHERE_API_KEY');
    }
    /**
     * Process portfolio by scraping, parsing, and saving it.
     */
    public function getYTPlaylist(string $url)
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
        dd($response['items']);
        return collect($response['items'])->map(function ($item) {
            $snippet = $item['snippet'];
            return [
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'video_url' => 'https://www.youtube.com/watch?v=' . $snippet['resourceId']['videoId'],
                'thumbnail_url' => $snippet['thumbnails']['medium']['url'] ?? null
            ];
        });
    }

    public function processPortfolio(string $url, string $username)
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
                'message' => 'Profile ingested successfully.',
                'count' => $projects->count(),
                'projects' => $projects,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Scraper failed: ' . $e->getMessage()], 500);
        }
        //old script
        $html = HttpHelper::fetchHtml($url);
        
        if (!$html) {
            throw new \Exception("Failed to fetch HTML from the URL.");
        }

        preg_match('/window\\[\'bootstrap\'\\] = JSON\\.parse\\(\'(.*?)\'\\);/', $html, $matches);
        if (!isset($matches[1])) {
            throw new \Exception('No JSON found in HTML.');
        }

        $jsonString = stripcslashes($matches[1]);
        $jsonData = json_decode($jsonString, true);

        $page = $jsonData['page']['A'] ?? [];

        $profileData = [
            'name' => $page['D'] ?? '',
            'description' => $page['E'] ?? '',
            'data' => $this->extractTextBlock($page),
        ];

        DB::beginTransaction();
        try {
            // Save TalentProfile
            $profile = TalentProfile::updateOrCreate(
                ['username' => $username],
                ['name' => $profileData['name'], 'description' => $profileData['description']]
            );

            // $embedding = $this->generateEmbedding($profileData['description']);
            // $profile->embedding = json_encode($embedding);
            // $profile->save();

            // Clear existing related data
            $profile->trustedClients()->delete();
            $profile->myWorks()->delete();
            $profile->testimonials()->delete();
            $profile->expertises()->delete();

            $parsedData = $profileData['data'];

            // Save Trusted Clients
            foreach ($parsedData['trusted_by'] as $clientName) {
                TrustedClient::create([
                    'talent_profile_id' => $profile->id,
                    'name' => $clientName
                ]);
            }

            // Save MyWork and Videos
            foreach ($parsedData['my_work'] as $workTitle => $videos) {
                $work = MyWork::create([
                    'talent_profile_id' => $profile->id,
                    'title' => $workTitle
                ]);
                foreach ($videos as $videoUrl) {
                    Video::create([
                        'my_work_id' => $work->id,
                        'url' => $videoUrl
                    ]);
                }
            }

            // Save Testimonials
            foreach ($parsedData['testimonials'] as $testimonial) {
                Testimonial::create([
                    'talent_profile_id' => $profile->id,
                    'name' => $testimonial['name'],
                    'text' => $testimonial['text']
                ]);
            }

            // Save Expertise
            foreach ($parsedData['expertise'] as $expertise) {
                Expertise::create([
                    'talent_profile_id' => $profile->id,
                    'name' => $expertise
                ]);
            }

            $combinedText = $profileData['description'] 
                        . ' ' . ($profileData['about'] ?? '')
                        . ' ' . implode(' ', $profileData['data']['expertise']);

            // 🆕 Generate the embedding (call CohereService or similar)
            $embedding = $this->generateEmbedding($combinedText);

            // 🆕 Store the embedding in the profile
            $profile->embedding = json_encode($embedding);
            $profile->save();


            DB::commit();

            return $profile;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Failed to process portfolio: " . $e->getMessage());
            throw $e;
        }
    }

    private function extractTextBlock(array $page): array
    {
        $result = [];

        if (!isset($page['A'])) {
            return $result;
        }

        foreach ($page['A'] as $block) {
            $elements = $block['E'] ?? [];
            foreach ($elements as $element) {
                // Text inside 'a'
                if (isset($element['a'])) {
                    $aData = $element['a'];
                    if (is_array($aData)) {
                        foreach ($aData as $subArray) {
                            $result[] = $subArray[0]['A'] ?? '';
                        }
                    } else {
                        $result[] = $aData;
                    }
                }

                // Text inside 'c'
                if (!empty($element['c']) && is_array($element['c'])) {
                    foreach ($element['c'] as $cElement) {
                        $text = $cElement['a']['A'][0]['A'] ?? null;
                        if ($text) {
                            $result[] = $text;
                        }
                    }
                }
            }
        }

        return $this->extractTextRecursively($result);
    }

    private function extractTextRecursively(array $data): array
    {
        $result = [
            'name' => '',
            'description' => '',
            'trusted_by' => [],
            'SHORTS & REELS VIDEO' => [],
            'my_work' => [],
            'testimonials' => [],
            'expertise' => [],
            'about' => '',
        ];

        $currentSection = '';
        $currentWorkTitle = '';
        $pendingTestimonialName = '';

        $expertiseKeywords = ['INDUSTRY', 'YEARS', 'UNDERSTANDING', 'CONTENTS', 'BRANDING', 'GRAPHICS'];
        $whatIDoKeywords = ['specialize in', 'high-quality', 'top creators', 'organic views'];

        foreach ($data as $item) {
            if (!is_string($item) || trim($item) === '' || is_numeric($item)) {
                continue;
            }

            $text = trim($item);
            $upperText = strtoupper($text);
            $lowerText = strtolower($text);

            // Classify expertise
            if ($this->containsKeyword($upperText, $expertiseKeywords)) {
                $result['expertise'][] = $text;
                continue;
            }

            // Classify "about"
            if ($this->containsKeyword($lowerText, $whatIDoKeywords) && empty($result['about'])) {
                $result['about'] = $text;
                continue;
            }

            // Section switching
            switch (true) {
                case str_contains($lowerText, 'trusted by'):
                    $currentSection = 'trusted_by';
                    continue 2;

                case str_contains($lowerText, 'shorts & reels video'):
                    $currentSection = 'SHORTS & REELS VIDEO';
                    continue 2;

                case str_contains($lowerText, 'my work'):
                    $currentSection = 'my_work';
                    continue 2;

                case str_contains($lowerText, 'testimonial'):
                    $currentSection = 'testimonials';
                    continue 2;
            }

            // Basic metadata
            if (empty($result['name'])) {
                $result['name'] = $text;
                continue;
            }

            if (empty($result['description'])) {
                $result['description'] = $text;
                continue;
            }

            // Section content
            switch ($currentSection) {
                case 'trusted_by':
                    $result['trusted_by'][] = $text;
                    break;

                case 'SHORTS & REELS VIDEO':
                    if (filter_var($text, FILTER_VALIDATE_URL)) {
                        $result['SHORTS & REELS VIDEO'][] = $text;
                    }
                    break;

                case 'my_work':
                    if (filter_var($text, FILTER_VALIDATE_URL)) {
                        if (!empty($currentWorkTitle)) {
                            $result['my_work'][$currentWorkTitle][] = $text;
                        }
                    } else {
                        $currentWorkTitle = $text;
                        $result['my_work'][$currentWorkTitle] = [];
                    }
                    break;

                case 'testimonials':
                    if (preg_match('/—|---/', $text)) {
                        $pendingTestimonialName = trim(preg_replace('/—|---/', '', $text));
                    } elseif (!empty($pendingTestimonialName)) {
                        $result['testimonials'][] = [
                            'name' => $pendingTestimonialName,
                            'text' => $text,
                        ];
                        $pendingTestimonialName = '';
                    }
                    break;
            }
        }

        return $result;
    }

    private function containsKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, strtolower($keyword)) || str_contains($text, strtoupper($keyword))) {
                return true;
            }
        }
        return false;
    }

    public function getProfileById(string $id)
    {
        try {
            $data = TalentProfile::where('id', $id)
            ->with(['trustedClients', 'myWorks.videos', 'testimonials', 'expertises'])
            ->first();

            if (!$data) {
                return response()->json(['message' => 'Profile not found.'], 404);
            }
            return response()->json(['data' => $data, 'message' => 'Profile retrieved successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An error occurred while retrieving the profile.', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateById(string $id, array $data)
    {
        $profile = TalentProfile::where('id', $id)->first();
        if (!$profile) {
            return null;
        }
        try {
            if($profile->update($data)){
                return response()->json([
                    'message' => 'Profile updated successfully.',
                    'data' => $profile->load(['trustedClients', 'myWorks.videos', 'testimonials', 'expertises'])
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update profile.',
                'error' => $e->getMessage()
            ], 500);
        }
        
    }

    public function deleteById(int $id)
    {
        try {
            DB::beginTransaction();

            $profile = TalentProfile::where('id', $id)->first();
            if (!$profile) {
                DB::rollBack();
                return response()->json(['message' => 'Profile not found.'], 404);
            }

            $profile->trustedClients()->delete();

            $profile->myWorks()->each(function ($work) {
                $work->videos()->delete();
                $work->delete();
            });

            $profile->testimonials()->delete();
            $profile->expertises()->delete();
            $profile->delete();

            DB::commit();

            return response()->json(['message' => 'Profile deleted successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateEmbedding(string $text): array
    {
        $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('COHERE_API_KEY'),
        'Content-Type' => 'application/json',
        ])->post('https://api.cohere.ai/v1/embed', [
            'texts' => [$text],  // Must be a non-empty array of strings
            'model' => 'embed-english-v2.0', // Make sure this is spelled exactly
            'input_type' => 'search_document' // Optional, but valid values: 'search_document', 'search_query', etc.
        ]);

        if ($response->failed()) {
            \Log::error('Cohere API error', [
                'status' => $response->status(),
                'reason' => $response->reason(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Failed to generate embedding from Cohere API.');
        }

        return $response->json()['embeddings'][0] ?? null;
    }
}
