<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Booking;

class CategoryPage extends Model
{
    protected $fillable = [
        'property_id', 'category_id', 'linked_page_id', 'title', 'content',
        'image_1', 'image_2', 'image_3', 'sort_order', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The page this one inherits its content from, if any.
     */
    public function linkedPage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_page_id');
    }

    /**
     * Pages that inherit their content from this one (this page is a shared
     * source for those properties).
     */
    public function linkedPages(): HasMany
    {
        return $this->hasMany(self::class, 'linked_page_id');
    }

    public function isLinked(): bool
    {
        return filled($this->linked_page_id);
    }

    /**
     * Follow the linked_page_id chain to the page that actually holds the
     * content. Guards against a cycle so a bad link can never loop forever.
     */
    public function resolvedPage(): self
    {
        $page = $this;
        $seen = [];

        while ($page->linked_page_id && ! isset($seen[$page->id])) {
            $seen[$page->id] = true;
            $next = $page->linkedPage;

            if (! $next) {
                break;
            }

            $page = $next;
        }

        return $page;
    }

    /**
     * The page whose content should be shown for a property + category,
     * resolving any link to its source. Null when no page row exists yet.
     */
    public static function effectiveFor(?int $propertyId, int $categoryId): ?self
    {
        $row = static::where('property_id', $propertyId)
            ->where('category_id', $categoryId)
            ->first();

        return $row?->resolvedPage();
    }

    public function renderContent(Booking $booking): string
    {
        if (!$this->content) return '';
        $content = preg_replace_callback('#internal://category/(\d+)#', function ($matches) use ($booking) {
            $linkedCategory = \App\Models\Category::find((int) $matches[1]);
            if (!$linkedCategory) return '#';
            return route('guest.category', [$booking->booking_id, $booking->token, $linkedCategory->slug]);
        }, $this->content);

        return str_replace(
            [
                '[[guest_name]]',
                '[[guest_first_name]]',
                '[[guest_last_name]]',
                '[[guest_phone]]',
                '[[booking_id]]',
                '[[check_in_date]]',
                '[[check_out_date]]',
                '[[property_name]]',
                '[[property_address]]',
            ],
            [
                $booking->guest_name,
                str($booking->guest_name)->before(' ')->toString(),
                str($booking->guest_name)->after(' ')->toString(),
                $booking->phone,
                $booking->booking_id,
                $booking->check_in_date->format('M d, Y'),
                $booking->check_out_date->format('M d, Y'),
                $booking->property->name,
                $booking->property->address,
            ],
            $content
        );
    }

    public function images(): array
    {
        return collect([$this->image_1, $this->image_2, $this->image_3])->filter()->values()->all();
    }
}
