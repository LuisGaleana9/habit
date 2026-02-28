<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteHabitRequest;
use App\Http\Requests\StoreHabitRequest;
use App\Http\Requests\UpdateHabitRequest;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    public function __construct(
        private readonly RewardService $rewardService,
    ) {}

    /**
     * GET /api/habits
     *
     * List all habits for the authenticated user.
     * Supports optional ?active=true|false filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->habits();

        if ($request->has('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        $habits = $query->latest()->get();

        return response()->json([
            'data' => $habits,
        ]);
    }

    /**
     * POST /api/habits
     *
     * Create a new habit for the authenticated user.
     */
    public function store(StoreHabitRequest $request): JsonResponse
    {
        $habit = $request->user()->habits()->create($request->validated());

        return response()->json([
            'message' => 'Habit created successfully!',
            'data'    => $habit,
        ], 201);
    }

    /**
     * GET /api/habits/{habit}
     *
     * Show a specific habit with its recent logs.
     */
    public function show(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('id', $habitId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $habit->load(['habitLogs' => function ($q) {
            $q->latest('completed_date')->limit(10);
        }]);

        return response()->json([
            'data' => $habit,
        ]);
    }

    /**
     * PUT /api/habits/{habit}
     *
     * Update a habit's title, difficulty, type or active status.
     */
    public function update(UpdateHabitRequest $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('id', $habitId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $habit->update($request->validated());

        return response()->json([
            'message' => 'Habit updated successfully!',
            'data'    => $habit->fresh(),
        ]);
    }

    /**
     * DELETE /api/habits/{habit}
     *
     * Soft-deactivate a habit (set is_active = false).
     * Pass ?force=true to permanently delete it.
     */
    public function destroy(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('id', $habitId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($request->boolean('force')) {
            $habit->delete();

            return response()->json([
                'message' => 'Habit permanently deleted.',
            ]);
        }

        $habit->update(['is_active' => false]);

        return response()->json([
            'message' => 'Habit deactivated.',
            'data'    => $habit->fresh(),
        ]);
    }

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
