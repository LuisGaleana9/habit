<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LootBoxService
{
    /**
     * Available loot box tiers.
     *
     * Each tier defines:
     *   - cost: [resource_name, quantity] required to open
     *   - rewards: cumulative probability table [[threshold, resource, qty], ...]
     *   - xp_bonus: XP awarded on opening
     */
    private const BOXES = [
        'wooden' => [
            'label'   => 'Wooden Chest',
            'cost'    => ['wood', 15],
            'xp_bonus' => 5,
            'rewards' => [
                [50,  'iron',      3],
                [80,  'gold_coin', 2],
                [95,  'diamond',   1],
                [100, 'ruby',      1],
            ],
        ],
        'iron' => [
            'label'   => 'Iron Chest',
            'cost'    => ['iron', 8],
            'xp_bonus' => 15,
            'rewards' => [
                [40,  'gold_coin', 5],
                [70,  'diamond',   2],
                [90,  'ruby',      1],
                [100, 'emerald',   1],
            ],
        ],
        'diamond' => [
            'label'   => 'Diamond Chest',
            'cost'    => ['diamond', 3],
            'xp_bonus' => 30,
            'rewards' => [
                [35,  'ruby',      3],
                [65,  'emerald',   2],
                [90,  'gold_coin', 10],
                [100, 'legendary_shard', 1],
            ],
        ],
    ];

    private const XP_PER_LEVEL = 100;

    /**
     * Return metadata for all available box tiers (for the shop/UI).
     */
    public function catalog(): array
    {
        $catalog = [];

        foreach (self::BOXES as $tier => $box) {
            $catalog[] = [
                'tier'     => $tier,
                'label'    => $box['label'],
                'cost'     => [
                    'resource' => $box['cost'][0],
                    'quantity' => $box['cost'][1],
                ],
                'xp_bonus' => $box['xp_bonus'],
                'possible_rewards' => array_map(
                    fn ($r) => ['resource' => $r[1], 'max_quantity' => $r[2]],
                    $box['rewards']
                ),
            ];
        }

        return $catalog;
    }

    /**
     * Open a loot box of the given tier.
     *
     * @return array{reward: array, xp_earned: int, leveled_up: bool, player: array}
     *
     * @throws \InvalidArgumentException  If tier is invalid.
     * @throws \RuntimeException          If player lacks resources.
     */
    public function open(User $user, string $tier): array
    {
        if (!isset(self::BOXES[$tier])) {
            throw new \InvalidArgumentException("Unknown loot box tier: {$tier}");
        }

        $box = self::BOXES[$tier];
        [$costResource, $costQty] = $box['cost'];

        return DB::transaction(function () use ($user, $box, $costResource, $costQty) {
            // 1. Check player has enough resources
            $costInventory = Inventory::where('user_id', $user->id)
                ->where('resource_name', $costResource)
                ->lockForUpdate()
                ->first();

            if (!$costInventory || $costInventory->quantity < $costQty) {
                throw new \RuntimeException(
                    "Not enough {$costResource}. Need {$costQty}, have " .
                    ($costInventory->quantity ?? 0) . '.'
                );
            }

            // 2. Deduct cost
            $costInventory->decrement('quantity', $costQty);

            // 3. Roll reward
            $reward = $this->rollReward($box['rewards']);

            // 4. Add reward to inventory
            $rewardInventory = Inventory::firstOrCreate(
                [
                    'user_id'       => $user->id,
                    'resource_name' => $reward['resource'],
                ],
                ['quantity' => 0]
            );
            $rewardInventory->increment('quantity', $reward['quantity']);

            // 5. Award bonus XP + check level up
            $previousLevel = $user->level;
            $user->experience += $box['xp_bonus'];
            $user->level = (int) floor($user->experience / self::XP_PER_LEVEL) + 1;
            $user->save();

            return [
                'box'        => $box['label'],
                'cost'       => ['resource' => $costResource, 'quantity' => $costQty],
                'reward'     => $reward,
                'xp_earned'  => $box['xp_bonus'],
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
     * Roll a reward from the given probability table.
     *
     * @return array{resource: string, quantity: int}
     */
    private function rollReward(array $rewards): array
    {
        $roll = rand(1, 100);

        foreach ($rewards as [$threshold, $resource, $quantity]) {
            if ($roll <= $threshold) {
                return ['resource' => $resource, 'quantity' => $quantity];
            }
        }

        // Fallback
        $last = end($rewards);
        return ['resource' => $last[1], 'quantity' => $last[2]];
    }
}
