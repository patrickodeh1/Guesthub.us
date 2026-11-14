// Finds cleans tagged to a guest who had not arrived yet on the clean's day, and moves them to the guest who left last.
// Dry run by default.  APPLY=1 to save.  DAYS=14 looks back/forward from today.
//   docker compose exec -T app php artisan tinker --execute="$(cat retag_sessions.php)"
//   docker compose exec -T -e APPLY=1 app php artisan tinker --execute="$(cat retag_sessions.php)"
use App\Models\{Booking, CleaningSession};

$apply = getenv('APPLY') === '1';
$from = now(config('app.display_timezone'))->subDays((int) (getenv('DAYS') ?: 14))->toDateString();
$dead = ['pending_payment', 'cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];
$n = 0;

CleaningSession::whereNotNull('booking_id')->where('no_guest', false)->where('status', '<>', 'cancelled')
    ->whereDate('scheduled_date', '>=', $from)->orderBy('scheduled_date')->get()
    ->each(function ($s) use ($apply, $dead, &$n) {
        $b = Booking::find($s->booking_id);
        $d = $s->scheduled_date?->toDateString();
        if (! $b || ! $d || $d > $b->check_in_date->toDateString() || $d >= $b->check_out_date->toDateString()) {
            return; // tagged to a guest who was in house / checking out: leave alone
        }
        $prev = Booking::where('property_id', $s->property_id)->where('id', '<>', $b->id)->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', $dead))
            ->whereDate('check_out_date', '<', $d)->orderByDesc('check_out_date')->orderByDesc('id')->first();
        if (! $prev) { return; }
        $n++;
        echo "session #{$s->id} {$d} ({$s->housekeeper?->name}, {$s->status}): {$b->guest_name} -> {$prev->guest_name}\n";
        if ($apply) { $s->forceFill(['booking_id' => $prev->id])->saveQuietly(); }
    });
echo $n === 0 ? "Nothing to retag.\n" : ($apply ? "Retagged {$n}.\n" : "{$n} found. Re-run with APPLY=1 to save.\n");
