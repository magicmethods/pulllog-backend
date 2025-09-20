<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFilter extends Model
{
    use HasFactory;

    protected $table = 'user_filters';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'context',
        'version',
        'layout',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'filters' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, UserFilter>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}