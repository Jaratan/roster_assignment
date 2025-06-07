<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SemanticSearchService;


class SemanticSearchController extends Controller
{
    protected $semanticSearchService;

    public function __construct(SemanticSearchService $semanticSearchService)
    {
        $this->semanticSearchService = $semanticSearchService;
    }

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string',
            'limit' => 'sometimes|integer|min:1|max:20'
        ]);

        $query = $request->input('query');
        $limit = $request->input('limit', 5);

        $results = $this->semanticSearchService->search($query, $limit);

        return response()->json([
            'data' => array_map(function ($item) {
                return [
                    'profile' => [
                        'id' => $item['profile']->id,
                        'name' => $item['profile']->name,
                        'description' => $item['profile']->description,
                    ],
                    'similarity' => $item['similarity']
                ];
            }, $results)
        ]);
    }
}
