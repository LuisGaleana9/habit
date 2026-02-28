<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'habit_id',
        'user_id',
        'completed_date',
        'xp_earned',
        'loot_dropped',
    ];

    protected function casts(): array
    {
        return [
            'completed_date' => 'date',
            'xp_earned'      => 'integer',
            'loot_dropped'   => 'array',
        ];
    }

    // ─── Relationships ──────────────────────────────────

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
