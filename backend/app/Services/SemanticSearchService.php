<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\TalentProfile;

class SemanticSearchService
{
    protected $cohereApiKey;

    public function __construct()
    {
        $this->cohereApiKey = env('COHERE_API_KEY');
    }

    public function search(string $query, int $limit = 5)
    {
        // 1. Generate embedding for the query using Cohere
        $embedding = $this->generateEmbedding($query);

        if (!$embedding) {
            throw new \Exception("Failed to generate embedding.");
        }

        // 2. Fetch all profiles with embeddings
        $profiles = TalentProfile::whereNotNull('embedding')->get();

        // 3. Calculate cosine similarity between the query and each profile
        $results = [];
        foreach ($profiles as $profile) {
            $profileEmbedding = json_decode($profile->embedding, true);

            if (!$profileEmbedding || !is_array($profileEmbedding)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($embedding, $profileEmbedding);

            $results[] = [
                'profile' => $profile,
                'similarity' => $similarity,
            ];
        }

        // 4. Sort by similarity
        usort($results, function ($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        // 5. Return top results
        return array_slice($results, 0, $limit);
    }

    private function generateEmbedding(string $text)
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->cohereApiKey}",
            'Content-Type' => 'application/json'
        ])->post('https://api.cohere.ai/v1/embed', [
            'model' => 'embed-english-v2.0',
            'texts' => [$text]
        ]);

        if ($response->successful()) {
            return $response->json()['embeddings'][0] ?? null;
        }

        return null;
    }

    private function cosineSimilarity(array $vectorA, array $vectorB)
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] ** 2;
            $normB += $vectorB[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}