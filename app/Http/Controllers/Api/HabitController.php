<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteHabitRequest;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;

class HabitController extends Controller
{
    public function __construct(
        private readonly RewardService $rewardService,
    ) {}

    /**
     * POST /api/habits/{habit}/complete
     *
     * Complete a habit, calculate rewards (XP + loot) and update the player state.
     */
    public function completeHabit(CompleteHabitRequest $request, int $habitId): JsonResponse
    {
        $user = $request->user();

        // 1. Verify the habit exists and belongs to the authenticated user
        $habit = Habit::where('id', $habitId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->firstOrFail();

        // 2. Resolve the user's local date
        $completedDate = $request->resolvedDate();

        // 3. Check for duplicate completion today
        $alreadyCompleted = HabitLog::where('habit_id', $habit->id)
            ->where('user_id', $user->id)
            ->where('completed_date', $completedDate)
            ->exists();

        if ($alreadyCompleted) {
            return response()->json([
                'message'        => 'You have already completed this habit today.',
                'completed_date' => $completedDate,
            ], 409);
        }

        // 4. Process rewards inside a DB transaction
        $result = $this->rewardService->processCompletion($user, $habit, $completedDate);

        // 5. Return structured response for the frontend to animate
        return response()->json([
            'message'    => 'Habit completed successfully!',
            'player'     => $result['player'],
            'reward'     => [
                'xp_earned' => $result['xp_earned'],
                'loot'      => $result['loot'],
            ],
            'leveled_up' => $result['leveled_up'],
        ], 200);
    }
}
