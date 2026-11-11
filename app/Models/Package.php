<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Package extends Model
{
    protected $fillable = ['key', 'name', 'description', 'price_cents', 'parent_id', 'sort_order', 'trial_days', 'active'];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'sort_order' => 'integer',
            'trial_days' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Features added by this package only. */
    public function ownFeatureKeys(): array
    {
        return DB::table('package_features')->where('package_id', $this->id)->pluck('feature_key')->all();
    }

    /** Features in this package plus everything inherited from its parents. */
    public function allFeatureKeys(): array
    {
        $keys = [];
        $seen = [];
        $node = $this;
        for ($i = 0; $node && $i < 10; $i++) {
            if (isset($seen[$node->id])) {
                break;
            }
            $seen[$node->id] = true;
            $keys = array_merge($keys, $node->ownFeatureKeys());
            $node = $node->parent;
        }

        return array_values(array_unique($keys));
    }
}
