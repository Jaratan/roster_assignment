<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\TalentProfileService;
use Validator;

class TalentProfileController extends Controller
{
    protected $talentProfileService;

    public function __construct(TalentProfileService $talentProfileService)
    {
        $this->talentProfileService = $talentProfileService;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'username' => 'required|string|unique:talent_profiles,username'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $profile = $this->talentProfileService->processPortfolio(
                $request->input('url'),
                $request->input('username')
            );

            return response()->json([
                'message' => 'Profile ingested successfully.',
                'data' => $profile->load(['trustedClients', 'myWorks.videos', 'testimonials', 'expertises'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to ingest profile.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id)
    {
        if (empty($id) || !is_numeric($id)) {
            return response()->json(['message' => 'Invalid or missing ID.'], 400);
        }
        
        $profile = $this->talentProfileService->getProfileById($id);
        return $profile;
    }

    public function update(Request $request, $id)
    {
        if (empty($id) || !is_numeric($id)) {
           return response()->json(['message' => 'Invalid or missing ID.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:20',
            'description' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        $updateProfile = $this->talentProfileService->updateById($id, $request->only(['name', 'description']));

        return $updateProfile;
    }

    public function destroy($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return response()->json(['message' => 'Invalid or missing ID.'], 400);
        }

        $deleted = $this->talentProfileService->deleteById($id);
        return $deleted;
        
    }
}
