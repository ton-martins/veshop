<?php

namespace App\Services\Accounting;

use App\Models\AccountingDocumentRequest;
use App\Models\AccountingFeeEntry;
use App\Models\AccountingObligation;
use App\Models\AccountingReminderLog;
use App\Models\Contractor;
use App\Notifications\AccountingReminderNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;

class AccountingAutomationService
{
    /**
     * @return array{created:int,updated_overdue:int}
     */
    public function processRecurringFees(Contractor $contractor): array
    {
        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo'));
        $today = now($timezone)->startOfDay();

        $created = 0;

        $entries = AccountingFeeEntry::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [
                AccountingFeeEntry::STATUS_PENDING,
                AccountingFeeEntry::STATUS_OVERDUE,
                AccountingFeeEntry::STATUS_PAID,
            ])
            ->where('recurrence_frequency', '!=', 'none')
            ->whereNotNull('next_reference_date')
            ->orderBy('next_reference_date')
            ->limit(500)
            ->get();

        foreach ($entries as $entry) {
            $nextReference = $entry->next_reference_date?->copy()->startOfDay();
            if (! $nextReference || $nextReference->greaterThan($today)) {
                continue;
            }

            $exists = AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->where('client_id', $entry->client_id)
                ->whereDate('due_date', $nextReference->toDateString())
                ->where('reference_label', $this->referenceLabelForDate($nextReference))
                ->exists();

            if (! $exists) {
                $newAmount = $this->applyAdjustment((float) $entry->amount, (string) ($entry->adjustment_type ?? 'none'), (float) ($entry->adjustment_value ?? 0));

                AccountingFeeEntry::query()->create([
                    'contractor_id' => $entry->contractor_id,
                    'client_id' => $entry->client_id,
                    'reference_label' => $this->referenceLabelForDate($nextReference),
                    'due_date' => $nextReference->toDateString(),
                    'amount' => $newAmount,
                    'paid_amount' => 0,
                    'status' => AccountingFeeEntry::STATUS_PENDING,
                    'recurrence_frequency' => $entry->recurrence_frequency,
                    'recurrence_interval' => max(1, (int) ($entry->recurrence_interval ?? 1)),
                    'next_reference_date' => $this->nextRecurrenceDate(
                        $nextReference,
                        (string) $entry->recurrence_frequency,
                        max(1, (int) ($entry->recurrence_interval ?? 1))
                    )?->toDateString(),
                    'adjustment_type' => $entry->adjustment_type,
                    'adjustment_value' => $entry->adjustment_value,
                    'reminder_email_enabled' => (bool) $entry->reminder_email_enabled,
                    'reminder_whatsapp_enabled' => (bool) $entry->reminder_whatsapp_enabled,
                    'notes' => $entry->notes,
                    'metadata' => [
                        ...(is_array($entry->metadata) ? $entry->metadata : []),
                        'recurrence_parent_id' => (int) $entry->id,
                        'generated_at' => now()->toIso8601String(),
                    ],
                ]);

                $created++;
            }

            $entry->next_reference_date = $this->nextRecurrenceDate(
                $nextReference,
                (string) $entry->recurrence_frequency,
                max(1, (int) ($entry->recurrence_interval ?? 1))
            );
            $entry->save();
        }

        $updatedOverdue = AccountingFeeEntry::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', AccountingFeeEntry::STATUS_PENDING)
            ->whereDate('due_date', '<', $today->toDateString())
            ->update(['status' => AccountingFeeEntry::STATUS_OVERDUE]);

        return [
            'created' => (int) $created,
            'updated_overdue' => (int) $updatedOverdue,
        ];
    }

    /**
     * @return array{sent:int,logs:int}
     */
    public function processDueReminders(Contractor $contractor): array
    {
        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo'));
        $today = now($timezone)->startOfDay();
        $windowEnd = $today->copy()->addDays(3)->endOfDay();

        $sent = 0;
        $logs = 0;

        $fees = AccountingFeeEntry::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [AccountingFeeEntry::STATUS_PENDING, AccountingFeeEntry::STATUS_OVERDUE])
            ->whereDate('due_date', '<=', $windowEnd->toDateString())
            ->where(static function ($query) use ($today): void {
                $query
                    ->whereNull('overdue_notified_at')
                    ->orWhereDate('overdue_notified_at', '<', $today->toDateString());
            })
            ->with('client:id,name,email,phone')
            ->limit(200)
            ->get();

        foreach ($fees as $fee) {
            $message = sprintf(
                'Honorário %s do cliente %s vence em %s (valor %s).',
                (string) $fee->reference_label,
                (string) ($fee->client?->name ?: 'Não informado'),
                $fee->due_date?->format('d/m/Y') ?: '-',
                number_format((float) $fee->amount, 2, ',', '.')
            );

            $dispatch = $this->dispatchReminder(
                contractor: $contractor,
                contextType: 'fee',
                contextId: (int) $fee->id,
                clientId: $fee->client_id ? (int) $fee->client_id : null,
                title: 'Lembrete de honorário',
                message: $message,
                targetUrl: '/app/services/accounting',
                emailEnabled: (bool) $fee->reminder_email_enabled,
                whatsappEnabled: (bool) $fee->reminder_whatsapp_enabled,
                emailTarget: (string) ($fee->client?->email ?? ''),
                whatsappTarget: (string) ($fee->client?->phone ?? $contractor->phone ?? '')
            );

            $sent += $dispatch['sent'];
            $logs += $dispatch['logs'];
            $fee->overdue_notified_at = now();
            $fee->save();
        }

        $obligations = AccountingObligation::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [AccountingObligation::STATUS_PENDING, AccountingObligation::STATUS_SENT, AccountingObligation::STATUS_OVERDUE])
            ->whereDate('due_date', '<=', $windowEnd->toDateString())
            ->where(static function ($query) use ($today): void {
                $query
                    ->whereNull('last_reminder_at')
                    ->orWhereDate('last_reminder_at', '<', $today->toDateString());
            })
            ->with('client:id,name,email,phone')
            ->limit(200)
            ->get();

        foreach ($obligations as $obligation) {
            $message = sprintf(
                'Obrigação "%s" do cliente %s vence em %s.',
                (string) $obligation->title,
                (string) ($obligation->client?->name ?: 'Não informado'),
                $obligation->due_date?->format('d/m/Y') ?: '-'
            );

            $dispatch = $this->dispatchReminder(
                contractor: $contractor,
                contextType: 'obligation',
                contextId: (int) $obligation->id,
                clientId: $obligation->client_id ? (int) $obligation->client_id : null,
                title: 'Lembrete de obrigação',
                message: $message,
                targetUrl: '/app/services/accounting',
                emailEnabled: true,
                whatsappEnabled: true,
                emailTarget: (string) ($obligation->client?->email ?? ''),
                whatsappTarget: (string) ($obligation->client?->phone ?? $contractor->phone ?? '')
            );

            $sent += $dispatch['sent'];
            $logs += $dispatch['logs'];
            $obligation->last_reminder_at = now();
            $obligation->save();
        }

        $documents = AccountingDocumentRequest::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [AccountingDocumentRequest::STATUS_PENDING, AccountingDocumentRequest::STATUS_REJECTED])
            ->whereDate('due_date', '<=', $windowEnd->toDateString())
            ->where(static function ($query) use ($today): void {
                $query
                    ->whereNull('last_reminder_at')
                    ->orWhereDate('last_reminder_at', '<', $today->toDateString());
            })
            ->with('client:id,name,email,phone')
            ->limit(200)
            ->get();

        foreach ($documents as $document) {
            $message = sprintf(
                'Documento "%s" do cliente %s tem prazo em %s.',
                (string) $document->title,
                (string) ($document->client?->name ?: 'Não informado'),
                $document->due_date?->format('d/m/Y') ?: '-'
            );

            $dispatch = $this->dispatchReminder(
                contractor: $contractor,
                contextType: 'document',
                contextId: (int) $document->id,
                clientId: $document->client_id ? (int) $document->client_id : null,
                title: 'Lembrete de documento',
                message: $message,
                targetUrl: '/app/services/accounting',
                emailEnabled: true,
                whatsappEnabled: true,
                emailTarget: (string) ($document->client?->email ?? ''),
                whatsappTarget: (string) ($document->client?->phone ?? $contractor->phone ?? '')
            );

            $sent += $dispatch['sent'];
            $logs += $dispatch['logs'];
            $document->last_reminder_at = now();
            $document->save();
        }

        return [
            'sent' => $sent,
            'logs' => $logs,
        ];
    }

    /**
     * @return array{sent:int,logs:int}
     */
    private function dispatchReminder(
        Contractor $contractor,
        string $contextType,
        int $contextId,
        ?int $clientId,
        string $title,
        string $message,
        string $targetUrl,
        bool $emailEnabled,
        bool $whatsappEnabled,
        string $emailTarget,
        string $whatsappTarget,
    ): array {
        $sent = 0;
        $logs = 0;

        $recipients = $contractor->users()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AccountingReminderNotification([
                'title' => $title,
                'message' => $message,
                'target_url' => $targetUrl,
                'contractor_id' => (int) $contractor->id,
                'context_type' => $contextType,
                'context_id' => $contextId,
            ]));
            $sent += $recipients->count();
        }

        $logs += $this->logReminder(
            contractorId: (int) $contractor->id,
            clientId: $clientId,
            channel: 'app',
            target: null,
            contextType: $contextType,
            contextId: $contextId,
            status: 'sent',
            message: $message
        );

        if ($emailEnabled && trim($emailTarget) !== '') {
            $logs += $this->logReminder(
                contractorId: (int) $contractor->id,
                clientId: $clientId,
                channel: 'email',
                target: trim($emailTarget),
                contextType: $contextType,
                contextId: $contextId,
                status: 'queued',
                message: $message
            );
        }

        if ($whatsappEnabled && trim($whatsappTarget) !== '') {
            $logs += $this->logReminder(
                contractorId: (int) $contractor->id,
                clientId: $clientId,
                channel: 'whatsapp',
                target: trim($whatsappTarget),
                contextType: $contextType,
                contextId: $contextId,
                status: 'queued',
                message: $message
            );
        }

        return [
            'sent' => $sent,
            'logs' => $logs,
        ];
    }

    private function logReminder(
        int $contractorId,
        ?int $clientId,
        string $channel,
        ?string $target,
        string $contextType,
        int $contextId,
        string $status,
        string $message,
    ): int {
        AccountingReminderLog::query()->create([
            'contractor_id' => $contractorId,
            'client_id' => $clientId,
            'channel' => $channel,
            'target' => $target,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
            'metadata' => null,
        ]);

        return 1;
    }

    private function referenceLabelForDate(Carbon $date): string
    {
        return $date->format('m/Y');
    }

    private function applyAdjustment(float $baseAmount, string $type, float $value): float
    {
        $normalizedType = strtolower(trim($type));

        if ($normalizedType === 'percent') {
            $next = $baseAmount + ($baseAmount * ($value / 100));
            return max(0, round($next, 2));
        }

        if ($normalizedType === 'fixed') {
            return max(0, round($baseAmount + $value, 2));
        }

        return max(0, round($baseAmount, 2));
    }

    private function nextRecurrenceDate(Carbon $baseDate, string $frequency, int $interval): ?Carbon
    {
        $safeInterval = max(1, $interval);
        $normalized = strtolower(trim($frequency));

        return match ($normalized) {
            'monthly' => $baseDate->copy()->addMonthsNoOverflow($safeInterval),
            'quarterly' => $baseDate->copy()->addMonthsNoOverflow(3 * $safeInterval),
            'yearly' => $baseDate->copy()->addYears($safeInterval),
            default => null,
        };
    }
}
