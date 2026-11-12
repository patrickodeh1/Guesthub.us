<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LeaseApplication;
use App\Models\PaymentReceipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Receipts for manual (off-platform) payments. One table serves every kind of
 * payable: direct bookings now, lease installments and lease application fees
 * next. Files live on the private local disk, never under /storage.
 */
class PaymentReceiptService
{
    private const DISK = 'local';

    public function rules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:8192'];
    }

    public function store(UploadedFile $file, Model $payable, string $role, ?int $userId = null): PaymentReceipt
    {
        $dir = 'payment-receipts/'.strtolower(class_basename($payable)).'/'.$payable->getKey();
        $path = $file->store($dir, self::DISK);

        return PaymentReceipt::create([
            'payable_type' => $payable->getMorphClass(),
            'payable_id' => $payable->getKey(),
            'role' => $role,
            'path' => $path,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    public function forPayable(Model $payable)
    {
        return PaymentReceipt::query()
            ->where('payable_type', $payable->getMorphClass())
            ->where('payable_id', $payable->getKey())
            ->orderBy('created_at')
            ->get();
    }

    public function stream(PaymentReceipt $receipt): BinaryFileResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($receipt->path), 404);

        return response()->file(Storage::disk(self::DISK)->path($receipt->path), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Guest says they paid and attaches proof. Does NOT confirm anything: the host does that. */
    public function guestReport(Booking $booking, UploadedFile $file): void
    {
        if ($booking->source !== 'direct' || $booking->payment_method !== 'manual'
            || $booking->status !== Booking::STATUS_PENDING_PAYMENT || $booking->payment_confirmed_at
            || ($booking->direct_booking_request && ! $booking->direct_booking_approved_at)
            || ($booking->hold_expires_at && $booking->hold_expires_at->isPast())) {
            throw ValidationException::withMessages(['receipt' => 'This booking is not waiting for a payment right now.']);
        }
        if ($this->forPayable($booking)->where('role', 'guest')->count() >= 5) {
            throw ValidationException::withMessages(['receipt' => 'You have already uploaded the maximum number of receipts.']);
        }

        $this->store($file, $booking, 'guest');
        Booking::query()->whereKey($booking->id)->update(['guest_payment_reported_at' => now()]);

        $this->notifyHost($booking->fresh(['property.owner']));
    }

    public function guestReportLeaseApplicationFee(
        LeaseApplication $application,
        UploadedFile $file,
        LeaseApplicationNotificationService $notifications,
    ): void {
        if (! in_array($application->status, ['submitted', 'in_review'], true)
            || $application->payment_method !== 'manual'
            || $application->fee_status !== 'pending') {
            throw ValidationException::withMessages(['receipt' => 'This application is not waiting for a manual payment.']);
        }
        if ($this->forPayable($application)->where('role', 'guest')->count() >= 5) {
            throw ValidationException::withMessages(['receipt' => 'You have already uploaded the maximum number of receipts.']);
        }

        $this->store($file, $application, 'guest');
        $notifications->feePaymentReported($application->fresh(['property.owner', 'listing']));
    }

    private function notifyHost(Booking $booking): void
    {
        try {
            $email = $booking->property?->owner?->email;
            if (! $email) {
                return;
            }
            $amount = '$'.number_format(((int) $booking->total_cents) / 100, 2);
            $dates = $booking->check_in_date->format('M j').' to '.$booking->check_out_date->format('M j, Y');
            EmailNotificationService::sendDirectBooking(
                $booking,
                $email,
                'Guest reported a payment',
                "{$booking->guest_name} says they paid {$amount} for {$booking->property->name} ({$dates}) and uploaded a receipt. Check your account, then open the booking, upload your own received receipt and mark the payment received. Reference {$booking->booking_id}.",
                route('admin.guests.show', $booking),
                'Review payment'
            );
        } catch (\Throwable $e) {
            Log::warning('Guest payment report notification failed', ['booking' => $booking->booking_id, 'message' => $e->getMessage()]);
        }
    }
}
