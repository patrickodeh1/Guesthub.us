<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Editable wording and on/off switches for the texts the cleaning flow sends.
 * Stored in the "cleaning_messages_config" setting; code defaults fill any gap.
 */
class CleaningMessages
{
    public const EVENTS = [
        'assignment_new' => [
            'label' => 'New cleaning assigned',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'when', 'session_link'],
            'default' => 'New cleaning available at {property_name}, {when}. Accept this job? Reply Y or N',
        ],
        'assignment_updated' => [
            'label' => 'Cleaning updated',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'when', 'session_link'],
            'default' => 'Updated cleaning available at {property_name}, {when}. Accept this job? Reply Y or N',
        ],
        'assignment_moved' => [
            'label' => 'Cleaning moved to a new time',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'when', 'old_when', 'session_link'],
            'default' => "Scheduled cleaning for {property_name} on {old_when} has been moved to {when}. To accept, reply Y or if you're not available reply N.",
        ],
        'start_reminder' => [
            'label' => 'Cleaning starts soon (reminder)',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'lead', 'time', 'session_link'],
            'default' => 'Reminder: your cleaning at {property_name} starts in {lead} ({time}). View job: {session_link}',
        ],
        'cleaner_confirmed' => [
            'label' => 'Cleaner replied Y (confirmation)',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'when', 'session_link'],
            'default' => 'Confirmed: {property_name} on {when}. Thank you! View job: {session_link}',
        ],
        'cleaner_declined' => [
            'label' => 'Cleaner replied N (thank you)',
            'audience' => 'To cleaner',
            'tokens' => ['property_name', 'when'],
            'default' => 'Thank you, We will schedule another cleaner.',
        ],
        'cleaner_reply_help' => [
            'label' => 'Cleaner replied something else',
            'audience' => 'To cleaner',
            'tokens' => [],
            'default' => 'Please reply Y to confirm or N to decline your cleaning assignment.',
        ],
        'almost_done' => [
            'label' => 'Cleaning almost finished',
            'audience' => 'To property contacts',
            'tokens' => ['property_name', 'cleaner', 'guest', 'time', 'job_id'],
            'default' => "Cleaning is almost finished at {property_name}.\nCleaner: {cleaner}\nGuest: {guest}\nTime: {time}\nJob ID: #{job_id}",
        ],
        'admin_accepted' => [
            'label' => 'Cleaner accepted a job',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name', 'when'],
            'default' => '{cleaner} accepted the cleaning at {property_name} on {when}.',
        ],
        'admin_declined' => [
            'label' => 'Cleaner declined a job',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name', 'when', 'session_link'],
            'default' => '{cleaner} declined the cleaning at {property_name} on {when}. View job: {session_link}',
        ],
        'admin_needs_cleaner' => [
            'label' => 'Cleaning needs a cleaner',
            'audience' => 'To contact desk',
            'tokens' => ['property_name', 'when', 'assign_link'],
            'default' => 'New cleaning needs a cleaner: {property_name}, {when}. Assign: {assign_link}',
        ],
        'admin_cleaner_cancelled' => [
            'label' => 'Cleaner cancelled a job',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name', 'when', 'assign_link'],
            'default' => '{cleaner} cancelled the cleaning at {property_name} on {when}. It needs a new cleaner. Assign: {assign_link}',
        ],
        'admin_unconfirmed' => [
            'label' => 'Cleaner has not confirmed',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name', 'when'],
            'default' => "{cleaner} hasn't confirmed {property_name} cleaning for {when}",
        ],
        'admin_no_phone' => [
            'label' => 'Cleaner has no phone number',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name'],
            'default' => '{cleaner} has no phone number on file, so they could not be texted the cleaning at {property_name}. Add a phone number, or confirm the job manually.',
        ],
        'admin_send_failed' => [
            'label' => 'Assignment text failed to send',
            'audience' => 'To contact desk',
            'tokens' => ['cleaner', 'property_name', 'when'],
            'default' => 'The assignment text to {cleaner} for {property_name} on {when} failed to send. Job will need to be confirmed manually.',
        ],
    ];

    public static function config(): array
    {
        $stored = json_decode(Setting::getValue('cleaning_messages_config', '') ?: '', true) ?: [];
        $out = [];
        foreach (self::EVENTS as $key => $meta) {
            $row = is_array($stored[$key] ?? null) ? $stored[$key] : [];
            $message = trim((string) ($row['message'] ?? ''));
            $out[$key] = [
                'message' => $message !== '' ? $message : $meta['default'],
                'enabled' => array_key_exists('enabled', $row) ? (bool) $row['enabled'] : true,
            ];
        }
        return $out;
    }

    public static function enabled(string $key): bool
    {
        return (bool) (self::config()[$key]['enabled'] ?? false);
    }

    public static function render(string $key, array $vars = []): string
    {
        $message = self::config()[$key]['message'] ?? (self::EVENTS[$key]['default'] ?? '');
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{'.$k.'}'] = (string) $v;
        }
        $text = strtr($message, $map);

        // Drop an empty "()" left by a blank token (e.g. no scheduled time).
        return trim(preg_replace('/\s*\(\s*\)/', '', $text));
    }

    /** Save from the settings form. Rows that did not arrive complete keep their saved values. */
    public static function put(array $input): void
    {
        $existing = self::config();
        $clean = [];
        foreach (self::EVENTS as $key => $meta) {
            $row = $input[$key] ?? null;
            if (! is_array($row) || empty($row['_complete'])) {
                $clean[$key] = $existing[$key];
                continue;
            }
            $msg = trim(str_replace("\r\n", "\n", (string) ($row['message'] ?? '')));
            $clean[$key] = [
                'message' => $msg !== '' ? $msg : $meta['default'],
                'enabled' => ! empty($row['enabled']),
            ];
        }
        Setting::putValue('cleaning_messages_config', json_encode($clean));
    }

    /** Back to code wording for every message, keeping the Send switches. */
    public static function resetWording(): void
    {
        $cfg = self::config();
        foreach ($cfg as $k => $row) {
            $cfg[$k]['message'] = self::EVENTS[$k]['default'];
        }
        Setting::putValue('cleaning_messages_config', json_encode($cfg));
    }
}
