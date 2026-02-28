<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     *
     * Return the authenticated player's profile with gamification stats.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Total completions and current XP towards next level
        $totalCompletions = $user->habitLogs()->count();
        $xpForNextLevel   = 100; // XP_PER_LEVEL (linear MVP)
        $xpProgress       = $user->experience % $xpForNextLevel;

        return response()->json([
            'data' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'level'             => $user->level,
                'experience'        => $user->experience,
                'health'            => $user->health,
                'xp_to_next_level'  => $xpForNextLevel - $xpProgress,
                'xp_progress_pct'   => $xpForNextLevel > 0 ? round(($xpProgress / $xpForNextLevel) * 100) : 100,
                'total_completions' => $totalCompletions,
                'active_habits'     => $user->habits()->where('is_active', true)->count(),
                'member_since'      => $user->created_at->toDateString(),
            ],
        ]);
    }
}
