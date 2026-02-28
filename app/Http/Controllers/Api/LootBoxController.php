<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LootBoxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LootBoxController extends Controller
{
    public function __construct(
        private readonly LootBoxService $lootBoxService,
    ) {}

    /**
     * GET /api/lootbox
     *
     * List all available loot box tiers with costs and possible rewards.
     */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => $this->lootBoxService->catalog(),
        ]);
    }

    /**
     * POST /api/lootbox/open
     *
     * Open a loot box. Requires JSON body: { "tier": "wooden|iron|diamond" }
     */
    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'tier' => 'required|string|in:wooden,iron,diamond',
        ]);

        try {
            $result = $this->lootBoxService->open(
                $request->user(),
                $request->input('tier'),
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => "Opened {$result['box']}!",
            'data'    => $result,
        ]);
    }
}
