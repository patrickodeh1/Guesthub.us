<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Pms\ChannexOutboxSender;
use App\Services\Pms\ChannexProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Read-only: compares what Channex holds with the Guesthub ledger.
 * Never writes to Channex or to the ledger.
 */
class ChannexCompare extends Command
{
    protected $signature = 'channex:compare {--property= : Only this Guesthub property ID} {--days=500} {--dump : Print the raw Channex responses for the first window and stop}';
    protected $description = 'Read-only comparison of Channex ARI against the Guesthub ledger';

    private const WINDOW_DAYS = 90;

    public function handle(ChannexOutboxSender $sender): int
    {
        $provider = new ChannexProvider();
        $days = max(1, (int) $this->option('days'));
        $start = now()->startOfDay();

        $properties = Property::query()
            ->whereNotNull('channex_property_id')
            ->whereNotNull('channex_room_type_id')
            ->when($this->option('property'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($properties->isEmpty()) {
            $this->info('No connected properties.');
            return self::SUCCESS;
        }

        $restrictionPayload = new \ReflectionMethod($sender, 'restrictionPayload');
        $restrictionPayload->setAccessible(true);

        $exit = self::SUCCESS;

        foreach ($properties as $property) {
            $end = $start->copy()->addDays($days - 1);
            $ledger = $property->availabilities()
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString())
                ->orderBy('date')->get()
                ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

            $checkAvail = (bool) $property->channex_availability_seeded_at;
            $checkRates = $property->rate_source === 'guesthub' && $property->channex_rate_plan_id;

            $expectedRates = [];
            $fields = [];
            if ($checkRates) {
                foreach ($ledger as $d => $row) {
                    $p = $restrictionPayload->invoke($sender, $row);
                    if ($p !== []) {
                        $expectedRates[$d] = $p;
                        $fields = array_unique(array_merge($fields, array_keys($p)));
                    }
                }
            }

            $diffs = [];
            $failed = false;

            for ($offset = 0; $offset < $days; $offset += self::WINDOW_DAYS) {
                $from = $start->copy()->addDays($offset);
                $to = $start->copy()->addDays(min($offset + self::WINDOW_DAYS, $days) - 1);
                $f = $from->toDateString();
                $t = $to->toDateString();

                if ($checkAvail) {
                    $data = $provider->getAvailabilityRange($property->channex_property_id, $f, $t);
                    if ($data === null) { $failed = true; continue; }
                    if ($this->option('dump')) { $this->line('availability: ' . mb_substr(json_encode($data), 0, 1500)); }
                    $remote = $data[$property->channex_room_type_id] ?? [];

                    foreach ($ledger as $d => $row) {
                        if ($d < $f || $d > $t) continue;
                        $want = $row->is_available ? 1 : 0;
                        $got = $remote[$d] ?? null;
                        if ($got === null || (int) $got !== $want) {
                            $diffs[] = "{$d} availability: ledger {$want}, Channex " . ($got ?? 'missing');
                        }
                    }
                    usleep(300000);
                }

                if ($checkRates && $fields) {
                    $data = $provider->getRestrictionsRange($property->channex_property_id, $f, $t, $fields);
                    if ($data === null) { $failed = true; continue; }
                    if ($this->option('dump')) { $this->line('restrictions: ' . mb_substr(json_encode($data), 0, 1500)); }
                    $remote = $data[$property->channex_rate_plan_id] ?? [];

                    foreach ($expectedRates as $d => $want) {
                        if ($d < $f || $d > $t) continue;
                        foreach ($want as $field => $value) {
                            $got = $remote[$d][$field] ?? null;
                            if ($this->norm($got) !== $this->norm($value)) {
                                $diffs[] = "{$d} {$field}: ledger " . json_encode($value) . ', Channex ' . json_encode($got);
                            }
                        }
                    }
                    usleep(300000);
                }

                if ($this->option('dump')) {
                    $this->warn('Dump only: stopped after the first window. Nothing was compared.');
                    return self::SUCCESS;
                }
            }

            $label = "Property {$property->id} ({$property->name})";
            if ($failed) {
                $exit = self::FAILURE;
                $this->error("{$label}: a read from Channex failed, see laravel.log. Result may be incomplete.");
            }
            if ($diffs === []) {
                $this->info("{$label}: no differences" . ($failed ? ' in the windows that loaded' : '') . '.');
                Log::info("channex:compare {$label}: no differences");
            } else {
                $this->warn("{$label}: " . count($diffs) . ' difference(s). First 15:');
                foreach (array_slice($diffs, 0, 15) as $line) {
                    $this->line('  ' . $line);
                }
                Log::warning("channex:compare {$label}: " . count($diffs) . ' difference(s)', ['first' => array_slice($diffs, 0, 15)]);
            }
        }

        return $exit;
    }

    private function norm($v)
    {
        if ($v === null) return null;
        if (is_bool($v)) return (int) $v;
        if (is_numeric($v)) return round((float) $v, 2);
        return (string) $v;
    }
}
