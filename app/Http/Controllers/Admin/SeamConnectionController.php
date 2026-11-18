<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\SeamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SeamConnectionController extends Controller
{
    public function connect(Request $request, Property $property, SeamService $seam)
    {
        $host = $property->owner;
        abort_unless($host, 422, 'Assign an owner to this property before connecting a lock account.');

        try {
            $webview = $seam->createConnectWebview(
                $host,
                route('admin.locks.connect.return', $property),
                route('admin.locks.connect.failed', $property),
            );
        } catch (Throwable $exception) {
            Log::warning('Could not create Seam Connect Webview.', [
                'property_id' => $property->id,
                'host_id' => $host->id,
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'Seam could not start the connection. Please try again later.');
        }

        if (empty($webview['connect_webview_id']) || empty($webview['url'])) {
            return back()->with('error', 'Seam did not return a valid connection link. Please try again.');
        }

        $request->session()->put('seam.pending.'.$property->id, [
            'host_id' => $host->id,
            'connect_webview_id' => $webview['connect_webview_id'],
        ]);

        return redirect()->away($webview['url']);
    }

    public function returned(Request $request, Property $property, SeamService $seam)
    {
        $pending = $request->session()->pull('seam.pending.'.$property->id);
        $host = $property->owner;

        abort_unless(
            is_array($pending)
            && $host
            && (int) ($pending['host_id'] ?? 0) === (int) $host->id
            && ! empty($pending['connect_webview_id']),
            403,
            'This lock connection has expired. Start again from the property settings.',
        );

        try {
            $webview = $seam->getConnectWebview($pending['connect_webview_id']);
            if (($webview['status'] ?? null) !== 'authorized'
                || ! ($webview['login_successful'] ?? false)
                || ($webview['customer_key'] ?? null) !== 'gh-'.$host->id
                || ! in_array($webview['selected_provider'] ?? null, ['yale', 'august'], true)
                || empty($webview['connected_account_id'])) {
                return redirect()->route('admin.properties.edit', $property)
                    ->with('error', 'The lock account was not authorized. Please try connecting again.');
            }

            $existingAccount = \\App\\Models\\SeamConnectedAccount::where('seam_connected_account_id', $webview['connected_account_id'])->first();
            abort_if($existingAccount && (int) $existingAccount->user_id !== (int) $host->id, 403, 'This lock account is already linked to another host.');

            $account = $host->seamConnectedAccounts()->updateOrCreate(
                ['seam_connected_account_id' => $webview['connected_account_id']],
                [
                    'provider' => $webview['selected_provider'] ?? null,
                    'status' => 'connected',
                    'last_synced_at' => now(),
                ],
            );

            // A successful connection is not complete until its devices can be listed.
            $seam->listDevices($account->seam_connected_account_id);
        } catch (Throwable $exception) {
            Log::warning('Seam Connect Webview verification failed.', [
                'property_id' => $property->id,
                'host_id' => $host->id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('admin.properties.edit', $property)
                ->with('error', 'The lock account could not be verified. Please reconnect and try again.');
        }

        return redirect()->route('admin.properties.edit', $property)
            ->with('success', 'Lock account connected. You can now assign one of its devices to this property.');
    }

    public function failed(Request $request, Property $property)
    {
        $request->session()->forget('seam.pending.'.$property->id);

        return redirect()->route('admin.properties.edit', $property)
            ->with('error', 'The lock account connection was not completed. No account was linked.');
    }
}
