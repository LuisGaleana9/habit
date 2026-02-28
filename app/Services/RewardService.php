<?php

namespace App\Services;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RewardService
{
    /**
     * XP earned per difficulty level.
     */
    private const XP_MAP = [
        1 => 10,
        2 => 25,
        3 => 50,
    ];

    /**
     * Loot table with cumulative probability thresholds.
     * Format: [max_threshold, resource_name, quantity]
     */
    private const LOOT_TABLE = [
        [60,  'wood',    10],
        [90,  'iron',     5],
        [100, 'diamond',  1],
    ];

    /**
     * XP required per level (linear for MVP).
     *
     * Future (exponential curve):
     *   return (int) floor(100 * pow(1.5, $level - 1));
     *   This would give: Lv2=100, Lv3=150, Lv4=225, Lv5=337...
     */
    private const XP_PER_LEVEL = 100;

    /**
     * Process a habit completion and return the rewards.
     *
     * @return array{xp_earned: int, loot: array, leveled_up: bool, player: array}
     */
    public function processCompletion(User $user, Habit $habit, string $completedDate): array
    {
        return DB::transaction(function () use ($user, $habit, $completedDate) {
            // 1. Calculate XP based on difficulty
            $xpEarned = self::XP_MAP[$habit->difficulty] ?? 10;

            // 2. Roll random loot
            $loot = $this->rollLoot();

            // 3. Update user XP and level
            $previousLevel = $user->level;
            $user->experience += $xpEarned;
            $user->level = $this->calculateLevel($user->experience);
            $user->save();

            // 4. Update inventory (atomic upsert + increment)
            $inventory = Inventory::firstOrCreate(
                [
                    'user_id'       => $user->id,
                    'resource_name' => $loot['resource'],
                ],
                ['quantity' => 0]
            );
            $inventory->increment('quantity', $loot['quantity']);

            // 5. Log the completion
            HabitLog::create([
                'habit_id'       => $habit->id,
                'user_id'        => $user->id,
                'completed_date' => $completedDate,
                'xp_earned'      => $xpEarned,
                'loot_dropped'   => $loot,
            ]);

            return [
                'xp_earned'  => $xpEarned,
                'loot'       => $loot,
                'leveled_up' => $user->level > $previousLevel,
                'player'     => [
                    'level'      => $user->level,
                    'experience' => $user->experience,
                    'health'     => $user->health,
                ],
            ];
        });
    }

    /**
     * Calculate the player level from total accumulated experience.
     *
     * MVP: Linear progression (100 XP per level).
     * TODO v2: Switch to exponential curve for long-term retention.
     *   Example: level = floor(log(xp / 100, 1.5)) + 2
     */
    private function calculateLevel(int $experience): int
    {
        return (int) floor($experience / self::XP_PER_LEVEL) + 1;
    }

    /**
     * Roll the loot dice using weighted probabilities.
     *
     * @return array{resource: string, quantity: int}
     */
    private function rollLoot(): array
    {
        $roll = rand(1, 100);

        foreach (self::LOOT_TABLE as [$threshold, $resource, $quantity]) {
            if ($roll <= $threshold) {
                return [
                    'resource' => $resource,
                    'quantity' => $quantity,
                ];
            }
        }

        // Fallback (should never reach here)
        return ['resource' => 'wood', 'quantity' => 10];
    }
}
