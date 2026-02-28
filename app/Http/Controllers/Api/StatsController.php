<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    /**
     * GET /api/habits/{habit}/logs
     *
     * Paginated completion history for a specific habit.
     */
    public function habitLogs(Request $request, int $habitId): JsonResponse
    {
        $habit = Habit::where('id', $habitId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $logs = $habit->habitLogs()
            ->latest('completed_date')
            ->paginate(15);

        return response()->json($logs);
    }

    /**
     * GET /api/stats
     *
     * Aggregated stats for the authenticated player:
     * - total_completions, total_xp_earned
     * - completions last 7 and 30 days
     * - current streak (consecutive days with at least one completion)
     * - best streak
     * - favourite habit (most completed)
     */
    public function overview(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now    = Carbon::today();

        // Base query scoped to user
        $logsQuery = HabitLog::where('user_id', $userId);

        // Totals
        $totalCompletions = (clone $logsQuery)->count();
        $totalXp          = (clone $logsQuery)->sum('xp_earned');

        // Recent activity
        $last7  = (clone $logsQuery)->where('completed_date', '>=', $now->copy()->subDays(7))->count();
        $last30 = (clone $logsQuery)->where('completed_date', '>=', $now->copy()->subDays(30))->count();

        // Streaks: get distinct completion dates ordered descending
        $dates = HabitLog::where('user_id', $userId)
            ->selectRaw('DISTINCT completed_date')
            ->orderByDesc('completed_date')
            ->pluck('completed_date')
            ->map(fn ($d) => Carbon::parse($d)->startOfDay());

        [$currentStreak, $bestStreak] = $this->calculateStreaks($dates, $now);

        // Favourite habit
        $favourite = HabitLog::where('user_id', $userId)
            ->selectRaw('habit_id, COUNT(*) as completions')
            ->groupBy('habit_id')
            ->orderByDesc('completions')
            ->first();

        $favouriteHabit = null;
        if ($favourite) {
            $habit = Habit::find($favourite->habit_id);
            $favouriteHabit = [
                'id'          => $habit->id,
                'title'       => $habit->title,
                'completions' => $favourite->completions,
            ];
        }

        return response()->json([
            'data' => [
                'total_completions' => $totalCompletions,
                'total_xp_earned'   => (int) $totalXp,
                'last_7_days'       => $last7,
                'last_30_days'      => $last30,
                'current_streak'    => $currentStreak,
                'best_streak'       => $bestStreak,
                'favourite_habit'   => $favouriteHabit,
            ],
        ]);
    }

    /**
     * Calculate the current and best streaks from a descending collection of dates.
     *
     * @param  \Illuminate\Support\Collection<Carbon>  $dates
     * @return array{int, int} [currentStreak, bestStreak]
     */
    private function calculateStreaks($dates, Carbon $today): array
    {
        if ($dates->isEmpty()) {
            return [0, 0];
        }

        $currentStreak = 0;
        $bestStreak    = 0;
        $streak        = 0;
        $expectedDate  = $today->copy();

        foreach ($dates as $date) {
            // Allow today or yesterday as the starting point
            if ($streak === 0 && $date->diffInDays($today) > 1) {
                // No activity today or yesterday → current streak is 0
                $streak = 1;
                $bestStreak = 1;
                $expectedDate = $date->copy()->subDay();
                continue;
            }

            if ($streak === 0) {
                // First date matches today or yesterday
                $streak = 1;
                $currentStreak = 1;
                $expectedDate = $date->copy()->subDay();
                continue;
            }

            if ($date->equalTo($expectedDate)) {
                $streak++;
                if ($currentStreak > 0) {
                    $currentStreak = $streak;
                }
                $expectedDate = $date->copy()->subDay();
            } else {
                // Streak broken
                $bestStreak = max($bestStreak, $streak);
                $streak = 1;
                if ($currentStreak > 0) {
                    // Current streak is already set, don't extend it anymore
                    $currentStreak = $currentStreak; // keep current value
                }
                $currentStreak = max($currentStreak, 0); // lock it
                // Start checking a new streak from this date
                $expectedDate = $date->copy()->subDay();
                // After current streak is broken, stop updating it
                $currentStreak = $bestStreak > $currentStreak ? $currentStreak : $currentStreak;
            }
        }

        $bestStreak = max($bestStreak, $streak);

        return [$currentStreak, $bestStreak];
    }
}
