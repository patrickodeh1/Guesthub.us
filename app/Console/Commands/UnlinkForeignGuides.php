<?php

namespace App\Console\Commands;

use App\Models\CategoryPage;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnlinkForeignGuides extends Command
{
    protected $signature = 'guides:unlink-foreign {--dry-run : Only report what would change}';

    protected $description = 'Turn photo guides and category pages that are linked to a property of a different owner into independent copies.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? 'DRY RUN: nothing will be changed.' : 'Converting links between different owners into copies.');

        $stats = ['photo_links' => 0, 'photo_copied' => 0, 'photo_unmatched' => 0, 'photo_dangling' => 0, 'pages' => 0];

        DB::transaction(function () use ($dry, &$stats) {
            // ---- Photo guides ----
            foreach (Property::whereNotNull('photo_guide_source_id')->get() as $property) {
                $source = Property::find($property->photo_guide_source_id);

                if (! $source) {
                    $stats['photo_dangling']++;
                    $this->line("  photo guide: {$property->name} points at a property that no longer exists, clearing the link.");
                    if (! $dry) {
                        $property->forceFill(['photo_guide_source_id' => null])->save();
                    }
                    continue;
                }

                if ($source->hasSameOwnerAs($property)) {
                    continue;
                }

                $stats['photo_links']++;
                $this->line("  photo guide: {$property->name} (owner {$property->owner_id}) uses {$source->name} (owner {$source->owner_id}).");

                $map = $property->guideRoomMap();

                foreach ($source->photoReferences as $ref) {
                    $stats['photo_copied']++;
                    if ($dry) {
                        continue;
                    }

                    $new = $ref->replicate();
                    $new->property_id = $property->id;

                    $roomId = (int) ($ref->getAttribute('room_id') ?? 0);
                    $mapped = $roomId === 0 ? 0 : (int) ($map[$roomId] ?? 0);
                    if ($roomId !== 0 && $mapped === 0) {
                        $stats['photo_unmatched']++;
                    }
                    $new->setAttribute('room_id', $mapped);

                    // Copy the file too: removing a photo deletes its file, so two properties must not share one.
                    $disk = Storage::disk('public');
                    if ($ref->image_path && $disk->exists($ref->image_path)) {
                        $dir = dirname($ref->image_path);
                        $ext = pathinfo($ref->image_path, PATHINFO_EXTENSION);
                        $newPath = ($dir === '.' ? '' : $dir . '/') . Str::uuid() . ($ext ? '.' . $ext : '');
                        $disk->copy($ref->image_path, $newPath);
                        $new->image_path = $newPath;
                    }

                    $new->save();
                }

                if (! $dry) {
                    $property->forceFill(['photo_guide_source_id' => null])->save();
                }
            }

            // ---- Category pages ----
            foreach (CategoryPage::whereNotNull('linked_page_id')->with(['property', 'linkedPage.property'])->get() as $page) {
                $linked = $page->linkedPage;

                if (! $linked) {
                    $stats['pages']++;
                    $this->line("  page: #{$page->id} points at a page that no longer exists, clearing the link.");
                    if (! $dry) {
                        $page->linked_page_id = null;
                        $page->save();
                    }
                    continue;
                }

                if (! $page->property || ! $linked->property || $page->property->hasSameOwnerAs($linked->property)) {
                    continue;
                }

                $stats['pages']++;
                $this->line("  page: {$page->property->name} uses the page of {$linked->property->name} (different owner).");

                if (! $dry) {
                    $source = $page->resolvedPage();
                    $page->fill([
                        'title' => $source->title,
                        'content' => $source->content,
                        'image_1' => $source->image_1,
                        'image_2' => $source->image_2,
                        'image_3' => $source->image_3,
                    ]);
                    $page->linked_page_id = null;
                    $page->save();
                }
            }
        });

        $this->newLine();
        $this->table(['Result', 'Count'], [
            ['Photo guide links between different owners', $stats['photo_links']],
            ['Photo references ' . ($dry ? 'that would be copied' : 'copied'), $stats['photo_copied']],
            ['Photos whose room had no match by name (set to no room)', $stats['photo_unmatched']],
            ['Photo links to deleted properties cleared', $stats['photo_dangling']],
            ['Category pages ' . ($dry ? 'that would be converted' : 'converted') . ' (or dangling links cleared)', $stats['pages']],
        ]);

        return self::SUCCESS;
    }
}
