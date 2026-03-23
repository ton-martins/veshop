<?php

namespace App\Application\Accounting\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\AccountingClientProfile;
use App\Models\AccountingDocumentRequest;
use App\Models\AccountingDocumentVersion;
use App\Models\AccountingFeeEntry;
use App\Models\AccountingObligation;
use App\Models\AccountingReminderLog;
use App\Models\AccountingServiceTemplate;
use App\Models\AccountingTaskHistory;
use App\Models\Client;
use App\Models\Contractor;
use App\Services\Accounting\AccountingAutomationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAccountingService
{
    use ResolvesCurrentContractor;

    public function __construct(
        private readonly AccountingAutomationService $automationService,
    ) {}

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $this->ensureDefaultTemplates($contractor);
        $this->markOverdueFees($contractor);

        $enabledModules = $contractor->enabledModules();
        $nowAtTimezone = now($contractor->timezone);
        $monthStart = $nowAtTimezone->copy()->startOfMonth();
        $monthEnd = $nowAtTimezone->copy()->endOfMonth();

        $clients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'document'])
            ->map(static fn (Client $client): array => [
                'id' => (int) $client->id,
                'name' => (string) $client->name,
                'email' => (string) ($client->email ?? ''),
                'phone' => (string) ($client->phone ?? ''),
                'document' => (string) ($client->document ?? ''),
            ])
            ->values()
            ->all();

        $clientProfiles = AccountingClientProfile::query()
            ->where('contractor_id', $contractor->id)
            ->with('client:id,name,email,phone')
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get()
            ->map(static fn (AccountingClientProfile $profile): array => [
                'id' => (int) $profile->id,
                'client_id' => (int) $profile->client_id,
                'client_name' => (string) ($profile->client?->name ?? 'Não informado'),
                'client_email' => (string) ($profile->client?->email ?? ''),
                'client_phone' => (string) ($profile->client?->phone ?? ''),
                'service_regime' => (string) ($profile->service_regime ?? ''),
                'contract_number' => (string) ($profile->contract_number ?? ''),
                'contract_start_date' => optional($profile->contract_start_date)?->format('Y-m-d'),
                'contract_end_date' => optional($profile->contract_end_date)?->format('Y-m-d'),
                'monthly_fee' => $profile->monthly_fee !== null ? (float) $profile->monthly_fee : null,
                'billing_day' => $profile->billing_day,
                'sla_hours' => $profile->sla_hours,
                'responsible_name' => (string) ($profile->responsible_name ?? ''),
                'responsible_email' => (string) ($profile->responsible_email ?? ''),
                'responsible_phone' => (string) ($profile->responsible_phone ?? ''),
                'reminder_email_enabled' => (bool) $profile->reminder_email_enabled,
                'reminder_whatsapp_enabled' => (bool) $profile->reminder_whatsapp_enabled,
            ])
            ->values()
            ->all();

        $templates = AccountingServiceTemplate::query()
            ->where(static function ($query) use ($contractor): void {
                $query->whereNull('contractor_id')->orWhere('contractor_id', $contractor->id);
            })
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AccountingServiceTemplate $template): array => $this->toTemplatePayload($template))
            ->values()
            ->all();

        $fees = AccountingFeeEntry::query()
            ->where('contractor_id', $contractor->id)
            ->with('client:id,name')
            ->orderByDesc('due_date')
            ->limit(60)
            ->get()
            ->map(static fn (AccountingFeeEntry $entry): array => [
                'id' => (int) $entry->id,
                'client_id' => $entry->client_id ? (int) $entry->client_id : null,
                'client_name' => $entry->client?->name ? (string) $entry->client->name : 'Não informado',
                'reference_label' => (string) $entry->reference_label,
                'due_date' => optional($entry->due_date)?->format('Y-m-d'),
                'amount' => (float) $entry->amount,
                'paid_amount' => (float) $entry->paid_amount,
                'status' => (string) $entry->status,
                'recurrence_frequency' => (string) ($entry->recurrence_frequency ?? 'none'),
                'recurrence_interval' => (int) ($entry->recurrence_interval ?? 1),
                'next_reference_date' => optional($entry->next_reference_date)?->format('Y-m-d'),
                'adjustment_type' => (string) ($entry->adjustment_type ?? 'none'),
                'adjustment_value' => (float) ($entry->adjustment_value ?? 0),
                'reminder_email_enabled' => (bool) ($entry->reminder_email_enabled ?? true),
                'reminder_whatsapp_enabled' => (bool) ($entry->reminder_whatsapp_enabled ?? false),
                'paid_at' => optional($entry->paid_at)?->format('Y-m-d\TH:i'),
                'notes' => $entry->notes ? (string) $entry->notes : '',
            ])
            ->values()
            ->all();

        $obligations = AccountingObligation::query()
            ->where('contractor_id', $contractor->id)
            ->with('client:id,name')
            ->orderBy('due_date')
            ->limit(120)
            ->get()
            ->map(static fn (AccountingObligation $obligation): array => [
                'id' => (int) $obligation->id,
                'client_id' => $obligation->client_id ? (int) $obligation->client_id : null,
                'client_name' => $obligation->client?->name ? (string) $obligation->client->name : 'Não informado',
                'title' => (string) $obligation->title,
                'obligation_type' => $obligation->obligation_type ? (string) $obligation->obligation_type : '',
                'competence_date' => optional($obligation->competence_date)?->format('Y-m-d'),
                'due_date' => optional($obligation->due_date)?->format('Y-m-d'),
                'status' => (string) $obligation->status,
                'priority' => (string) $obligation->priority,
                'stage_code' => (string) ($obligation->stage_code ?? AccountingObligation::STAGE_BACKLOG),
                'assigned_to_name' => (string) ($obligation->assigned_to_name ?? ''),
                'started_at' => optional($obligation->started_at)?->format('Y-m-d\TH:i'),
                'reminder_at' => optional($obligation->reminder_at)?->format('Y-m-d\TH:i'),
                'completed_at' => optional($obligation->completed_at)?->format('Y-m-d\TH:i'),
                'notes' => $obligation->notes ? (string) $obligation->notes : '',
            ])
            ->values()
            ->all();

        $obligationHistories = AccountingTaskHistory::query()
            ->where('contractor_id', $contractor->id)
            ->with('createdBy:id,name')
            ->orderByDesc('created_at')
            ->limit(400)
            ->get()
            ->map(fn (AccountingTaskHistory $history): array => $this->toHistoryPayload($history))
            ->values()
            ->all();

        $documents = AccountingDocumentRequest::query()
            ->where('contractor_id', $contractor->id)
            ->with([
                'client:id,name',
                'template:id,name',
                'versions' => static fn ($query) => $query->orderByDesc('version_number')->limit(10),
            ])
            ->orderByDesc('created_at')
            ->limit(120)
            ->get()
            ->map(fn (AccountingDocumentRequest $document): array => [
                'id' => (int) $document->id,
                'client_id' => $document->client_id ? (int) $document->client_id : null,
                'client_name' => $document->client?->name ? (string) $document->client->name : 'Não informado',
                'template_id' => $document->template_id ? (int) $document->template_id : null,
                'template_name' => (string) ($document->template?->name ?? ''),
                'title' => (string) $document->title,
                'document_type' => $document->document_type ? (string) $document->document_type : '',
                'protocol_code' => (string) ($document->protocol_code ?? ''),
                'due_date' => optional($document->due_date)?->format('Y-m-d'),
                'status' => (string) $document->status,
                'pending_items_count' => (int) ($document->pending_items_count ?? 0),
                'last_version_number' => (int) ($document->last_version_number ?? 0),
                'received_at' => optional($document->received_at)?->format('Y-m-d\TH:i'),
                'notes' => $document->notes ? (string) $document->notes : '',
                'checklist_items' => $this->normalizeChecklistPayload($document->checklist_items),
                'versions' => $document->versions
                    ->map(fn (AccountingDocumentVersion $version): array => $this->toDocumentVersionPayload($contractor, $version))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $reminderLogs = AccountingReminderLog::query()
            ->where('contractor_id', $contractor->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (AccountingReminderLog $log): array => $this->toReminderLogPayload($log))
            ->values()
            ->all();

        $obligationsDueMonth = (int) AccountingObligation::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $obligationsCompletedMonth = (int) AccountingObligation::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', AccountingObligation::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->count();

        $productivityRate = $obligationsDueMonth > 0
            ? round(($obligationsCompletedMonth / $obligationsDueMonth) * 100, 2)
            : 0.0;

        $stats = [
            'fees_pending' => (float) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('status', [AccountingFeeEntry::STATUS_PENDING, AccountingFeeEntry::STATUS_OVERDUE])
                ->sum('amount'),
            'fees_received_month' => (float) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingFeeEntry::STATUS_PAID)
                ->whereBetween('paid_at', [$monthStart, $monthEnd])
                ->sum('paid_amount'),
            'obligations_due' => (int) AccountingObligation::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('status', [AccountingObligation::STATUS_PENDING, AccountingObligation::STATUS_SENT, AccountingObligation::STATUS_OVERDUE])
                ->whereDate('due_date', '<=', $nowAtTimezone->copy()->addDays(7)->toDateString())
                ->count(),
            'documents_pending' => (int) AccountingDocumentRequest::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingDocumentRequest::STATUS_PENDING)
                ->count(),
            'overdue_fees_count' => (int) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingFeeEntry::STATUS_OVERDUE)
                ->count(),
            'overdue_fees_amount' => (float) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingFeeEntry::STATUS_OVERDUE)
                ->sum('amount'),
            'contracts_expiring_30d' => (int) AccountingClientProfile::query()
                ->where('contractor_id', $contractor->id)
                ->whereNotNull('contract_end_date')
                ->whereBetween('contract_end_date', [$nowAtTimezone->toDateString(), $nowAtTimezone->copy()->addDays(30)->toDateString()])
                ->count(),
            'portfolio_active_clients' => (int) AccountingClientProfile::query()
                ->where('contractor_id', $contractor->id)
                ->count(),
            'productivity_rate' => $productivityRate,
            'reminders_today' => (int) AccountingReminderLog::query()
                ->where('contractor_id', $contractor->id)
                ->whereDate('sent_at', $nowAtTimezone->toDateString())
                ->count(),
        ];

        return Inertia::render('Admin/Services/Accounting', [
            'clients' => $clients,
            'clientProfiles' => $clientProfiles,
            'templates' => $templates,
            'fees' => $fees,
            'obligations' => $obligations,
            'obligationHistories' => $obligationHistories,
            'documents' => $documents,
            'reminderLogs' => $reminderLogs,
            'stats' => $stats,
            'moduleAccess' => [
                'finance' => in_array('finance', $enabledModules, true),
                'tasks' => in_array('tasks', $enabledModules, true),
                'documents' => in_array('documents', $enabledModules, true),
            ],
            'feeStatusOptions' => $this->feeStatusOptions(),
            'obligationStatusOptions' => $this->obligationStatusOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'documentStatusOptions' => $this->documentStatusOptions(),
            'obligationStageOptions' => $this->obligationStageOptions(),
            'feeRecurrenceOptions' => $this->feeRecurrenceOptions(),
            'feeAdjustmentOptions' => $this->feeAdjustmentOptions(),
            'permissionMatrix' => $this->permissionMatrix(),
        ]);
    }

    public function storeFee(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $payload = $this->validateFeePayload($request, $contractor);
        $payload['next_reference_date'] = $this->resolveNextReferenceDate($payload);

        AccountingFeeEntry::query()->create(
            array_merge(
                $payload,
                ['contractor_id' => $contractor->id],
            )
        );

        return back()->with('status', 'Honorário cadastrado com sucesso.');
    }

    public function updateFee(Request $request, AccountingFeeEntry $accountingFeeEntry): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $entry = $this->resolveOwnedFeeEntry($contractor, $accountingFeeEntry);
        $payload = $this->validateFeePayload($request, $contractor);
        $payload['next_reference_date'] = $this->resolveNextReferenceDate($payload, $entry);
        $entry->fill($payload)->save();

        return back()->with('status', 'Honorário atualizado com sucesso.');
    }

    public function destroyFee(Request $request, AccountingFeeEntry $accountingFeeEntry): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $entry = $this->resolveOwnedFeeEntry($contractor, $accountingFeeEntry);
        $entry->delete();

        return back()->with('status', 'Honorário removido com sucesso.');
    }

    public function storeObligation(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $payload = $this->validateObligationPayload($request, $contractor);
        $payload = $this->applyObligationTemplatePayload($payload, $contractor);

        $obligation = AccountingObligation::query()->create(
            array_merge(
                $payload,
                ['contractor_id' => $contractor->id],
            )
        );

        $this->registerTaskHistory(
            contractor: $contractor,
            obligation: $obligation,
            action: 'created',
            previousStage: null,
            currentStage: (string) $obligation->stage_code,
            previousStatus: null,
            currentStatus: (string) $obligation->status,
            assignedToName: (string) ($obligation->assigned_to_name ?? ''),
            dueDate: $obligation->due_date,
            notes: (string) ($obligation->notes ?? ''),
            request: $request,
        );

        return back()->with('status', 'Obrigação cadastrada com sucesso.');
    }

    public function updateObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $obligation = $this->resolveOwnedObligation($contractor, $accountingObligation);
        $previousStage = (string) ($obligation->stage_code ?? '');
        $previousStatus = (string) ($obligation->status ?? '');

        $payload = $this->validateObligationPayload($request, $contractor);
        $payload = $this->applyObligationTemplatePayload($payload, $contractor);

        $obligation->fill($payload)->save();

        $changedPipeline = $previousStage !== (string) $obligation->stage_code
            || $previousStatus !== (string) $obligation->status
            || $obligation->wasChanged(['assigned_to_name', 'due_date']);

        if ($changedPipeline) {
            $this->registerTaskHistory(
                contractor: $contractor,
                obligation: $obligation,
                action: 'updated',
                previousStage: $previousStage,
                currentStage: (string) $obligation->stage_code,
                previousStatus: $previousStatus,
                currentStatus: (string) $obligation->status,
                assignedToName: (string) ($obligation->assigned_to_name ?? ''),
                dueDate: $obligation->due_date,
                notes: (string) ($payload['notes'] ?? ''),
                request: $request,
            );
        }

        return back()->with('status', 'Obrigação atualizada com sucesso.');
    }

    public function destroyObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $obligation = $this->resolveOwnedObligation($contractor, $accountingObligation);

        $this->registerTaskHistory(
            contractor: $contractor,
            obligation: $obligation,
            action: 'deleted',
            previousStage: (string) ($obligation->stage_code ?? ''),
            currentStage: null,
            previousStatus: (string) ($obligation->status ?? ''),
            currentStatus: null,
            assignedToName: (string) ($obligation->assigned_to_name ?? ''),
            dueDate: $obligation->due_date,
            notes: 'Registro removido pelo usuário.',
            request: $request,
        );

        $obligation->delete();

        return back()->with('status', 'Obrigação removida com sucesso.');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $payload = $this->validateDocumentPayload($request, $contractor);
        $payload = $this->applyDocumentTemplatePayload($payload, $contractor);
        if (trim((string) ($payload['protocol_code'] ?? '')) === '') {
            $payload['protocol_code'] = $this->generateDocumentProtocolCode($contractor);
        }

        $document = AccountingDocumentRequest::query()->create(
            array_merge(
                $payload,
                ['contractor_id' => $contractor->id],
            )
        );

        if ($request->hasFile('version_file') || trim((string) ($request->input('version_notes') ?? '')) !== '') {
            $this->createDocumentVersion($request, $contractor, $document);
        }

        return back()->with('status', 'Solicitação de documento cadastrada com sucesso.');
    }

    public function updateDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $document = $this->resolveOwnedDocument($contractor, $accountingDocumentRequest);
        $payload = $this->validateDocumentPayload($request, $contractor, (int) $document->id);
        $payload = $this->applyDocumentTemplatePayload($payload, $contractor);
        if (trim((string) ($payload['protocol_code'] ?? '')) === '') {
            $payload['protocol_code'] = $document->protocol_code ?: $this->generateDocumentProtocolCode($contractor);
        }

        $document->fill($payload)->save();

        if ($request->hasFile('version_file') || trim((string) ($request->input('version_notes') ?? '')) !== '') {
            $this->createDocumentVersion($request, $contractor, $document);
        }

        return back()->with('status', 'Solicitação de documento atualizada com sucesso.');
    }

    public function destroyDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $document = $this->resolveOwnedDocument($contractor, $accountingDocumentRequest);
        foreach ($document->versions as $version) {
            $this->deleteDocumentVersionFile($contractor, $version->file_path);
        }
        $document->delete();

        return back()->with('status', 'Solicitação de documento removida com sucesso.');
    }

    public function storeClientProfile(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $payload = $this->validateClientProfilePayload($request, $contractor);

        AccountingClientProfile::query()->updateOrCreate(
            [
                'contractor_id' => $contractor->id,
                'client_id' => (int) $payload['client_id'],
            ],
            $payload,
        );

        return back()->with('status', 'Perfil contábil do cliente salvo com sucesso.');
    }

    public function updateClientProfile(Request $request, Client $client): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $client = $this->resolveOwnedClient($contractor, $client);

        $payload = $this->validateClientProfilePayload($request, $contractor, (int) $client->id);

        AccountingClientProfile::query()->updateOrCreate(
            [
                'contractor_id' => $contractor->id,
                'client_id' => $client->id,
            ],
            $payload,
        );

        return back()->with('status', 'Perfil contábil do cliente atualizado com sucesso.');
    }

    public function destroyClientProfile(Request $request, Client $client): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $client = $this->resolveOwnedClient($contractor, $client);

        AccountingClientProfile::query()
            ->where('contractor_id', $contractor->id)
            ->where('client_id', $client->id)
            ->delete();

        return back()->with('status', 'Perfil contábil removido com sucesso.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $payload = $this->validateTemplatePayload($request, $contractor);
        $payload['contractor_id'] = $contractor->id;

        AccountingServiceTemplate::query()->create($payload);

        return back()->with('status', 'Template contábil criado com sucesso.');
    }

    public function updateTemplate(Request $request, AccountingServiceTemplate $accountingServiceTemplate): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $template = $this->resolveOwnedTemplate($contractor, $accountingServiceTemplate);

        $template->fill($this->validateTemplatePayload($request, $contractor, (int) $template->id))->save();

        return back()->with('status', 'Template contábil atualizado com sucesso.');
    }

    public function destroyTemplate(Request $request, AccountingServiceTemplate $accountingServiceTemplate): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $template = $this->resolveOwnedTemplate($contractor, $accountingServiceTemplate);

        AccountingDocumentRequest::query()
            ->where('contractor_id', $contractor->id)
            ->where('template_id', $template->id)
            ->update(['template_id' => null]);

        $template->delete();

        return back()->with('status', 'Template contábil removido com sucesso.');
    }

    public function processRecurringFees(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $result = $this->automationService->processRecurringFees($contractor);

        return back()->with('status', sprintf(
            'Recorrência processada: %d lançamentos criados e %d atualizados para em atraso.',
            (int) ($result['created'] ?? 0),
            (int) ($result['updated_overdue'] ?? 0)
        ));
    }

    public function processReminders(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $result = $this->automationService->processDueReminders($contractor);

        return back()->with('status', sprintf(
            'Lembretes processados: %d notificações e %d logs gerados.',
            (int) ($result['sent'] ?? 0),
            (int) ($result['logs'] ?? 0)
        ));
    }

    public function storeDocumentVersion(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $document = $this->resolveOwnedDocument($contractor, $accountingDocumentRequest);

        $this->createDocumentVersion($request, $contractor, $document, true);

        return back()->with('status', 'Nova versão de documento registrada com sucesso.');
    }

    public function downloadDocumentVersion(Request $request, AccountingDocumentVersion $accountingDocumentVersion): StreamedResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);
        $version = $this->resolveOwnedDocumentVersion($contractor, $accountingDocumentVersion);

        [$disk, $filePath] = $this->resolveDocumentVersionStorage($contractor, $version->file_path);
        abort_unless($disk !== null && $filePath !== null, 404);

        $fileName = trim((string) ($version->file_name ?? ''));
        if ($fileName === '') {
            $fileName = basename($filePath);
        }

        if ($request->boolean('download')) {
            return Storage::disk($disk)->download($filePath, $fileName);
        }

        return Storage::disk($disk)->response($filePath, $fileName);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFeePayload(Request $request, Contractor $contractor): array
    {
        $payload = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'reference_label' => ['required', 'string', 'max:20'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'status' => ['required', Rule::in(array_column($this->feeStatusOptions(), 'value'))],
            'recurrence_frequency' => ['nullable', Rule::in(array_column($this->feeRecurrenceOptions(), 'value'))],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:48'],
            'next_reference_date' => ['nullable', 'date'],
            'adjustment_type' => ['nullable', Rule::in(array_column($this->feeAdjustmentOptions(), 'value'))],
            'adjustment_value' => ['nullable', 'numeric', 'min:-999999.9999', 'max:999999.9999'],
            'reminder_email_enabled' => ['nullable', 'boolean'],
            'reminder_whatsapp_enabled' => ['nullable', 'boolean'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payload['paid_amount'] = $payload['paid_amount'] ?? 0;
        $payload['recurrence_frequency'] = (string) ($payload['recurrence_frequency'] ?? 'none');
        $payload['recurrence_interval'] = max(1, (int) ($payload['recurrence_interval'] ?? 1));
        $payload['adjustment_type'] = (string) ($payload['adjustment_type'] ?? 'none');
        $payload['adjustment_value'] = (float) ($payload['adjustment_value'] ?? 0);
        $payload['reminder_email_enabled'] = (bool) ($payload['reminder_email_enabled'] ?? true);
        $payload['reminder_whatsapp_enabled'] = (bool) ($payload['reminder_whatsapp_enabled'] ?? false);

        if ((string) $payload['status'] === AccountingFeeEntry::STATUS_PAID && empty($payload['paid_at'])) {
            $payload['paid_at'] = now($contractor->timezone);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateObligationPayload(Request $request, Contractor $contractor): array
    {
        $payload = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_service_templates', 'id')->where(
                    static fn ($query) => $query
                        ->where('is_active', true)
                        ->where(function ($innerQuery) use ($contractor): void {
                            $innerQuery->whereNull('contractor_id')->orWhere('contractor_id', $contractor->id);
                        })
                ),
            ],
            'title' => ['required', 'string', 'max:180'],
            'obligation_type' => ['nullable', 'string', 'max:80'],
            'competence_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_column($this->obligationStatusOptions(), 'value'))],
            'priority' => ['required', Rule::in(array_column($this->priorityOptions(), 'value'))],
            'stage_code' => ['nullable', Rule::in(array_column($this->obligationStageOptions(), 'value'))],
            'assigned_to_name' => ['nullable', 'string', 'max:120'],
            'started_at' => ['nullable', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payload['obligation_type'] = trim((string) ($payload['obligation_type'] ?? '')) ?: null;
        $payload['stage_code'] = (string) ($payload['stage_code'] ?? AccountingObligation::STAGE_BACKLOG);
        $payload['assigned_to_name'] = trim((string) ($payload['assigned_to_name'] ?? '')) ?: null;
        $payload['notes'] = trim((string) ($payload['notes'] ?? '')) ?: null;

        if ((string) $payload['status'] === AccountingObligation::STATUS_COMPLETED && empty($payload['completed_at'])) {
            $payload['completed_at'] = now($contractor->timezone);
        }

        if ((string) $payload['status'] !== AccountingObligation::STATUS_COMPLETED) {
            $payload['completed_at'] = null;
        }

        if (
            in_array((string) $payload['stage_code'], [AccountingObligation::STAGE_IN_PROGRESS, AccountingObligation::STAGE_REVIEW, AccountingObligation::STAGE_DONE], true)
            && empty($payload['started_at'])
        ) {
            $payload['started_at'] = now($contractor->timezone);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocumentPayload(
        Request $request,
        Contractor $contractor,
        ?int $ignoreDocumentId = null
    ): array {
        $payload = $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_service_templates', 'id')->where(
                    static fn ($query) => $query
                        ->where('is_active', true)
                        ->where(function ($innerQuery) use ($contractor): void {
                            $innerQuery->whereNull('contractor_id')->orWhere('contractor_id', $contractor->id);
                        })
                ),
            ],
            'title' => ['required', 'string', 'max:180'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_column($this->documentStatusOptions(), 'value'))],
            'protocol_code' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('accounting_document_requests', 'protocol_code')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id))
                    ->ignore($ignoreDocumentId),
            ],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*.label' => ['nullable', 'string', 'max:180'],
            'checklist_items.*.done' => ['nullable', 'boolean'],
        ]);

        $payload['document_type'] = trim((string) ($payload['document_type'] ?? '')) ?: null;
        $payload['protocol_code'] = trim((string) ($payload['protocol_code'] ?? '')) ?: null;
        $payload['notes'] = trim((string) ($payload['notes'] ?? '')) ?: null;
        $payload['checklist_items'] = $this->normalizeChecklistPayload($payload['checklist_items'] ?? []);
        $payload['pending_items_count'] = (int) collect($payload['checklist_items'])
            ->filter(static fn (array $item): bool => ! (bool) ($item['done'] ?? false))
            ->count();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClientProfilePayload(Request $request, Contractor $contractor, ?int $forcedClientId = null): array
    {
        $clientRule = Rule::exists('clients', 'id')->where(
            static fn ($query) => $query->where('contractor_id', $contractor->id)
        );

        $payload = $request->validate([
            'client_id' => $forcedClientId === null
                ? ['required', 'integer', $clientRule]
                : ['nullable', 'integer', $clientRule],
            'service_regime' => ['nullable', 'string', 'max:80'],
            'contract_number' => ['nullable', 'string', 'max:80'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'billing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'sla_hours' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'responsible_name' => ['nullable', 'string', 'max:160'],
            'responsible_email' => ['nullable', 'email', 'max:180'],
            'responsible_phone' => ['nullable', 'string', 'max:32'],
            'reminder_email_enabled' => ['nullable', 'boolean'],
            'reminder_whatsapp_enabled' => ['nullable', 'boolean'],
        ]);

        $payload['client_id'] = $forcedClientId !== null
            ? $forcedClientId
            : (int) ($payload['client_id'] ?? 0);
        $payload['service_regime'] = trim((string) ($payload['service_regime'] ?? '')) ?: null;
        $payload['contract_number'] = trim((string) ($payload['contract_number'] ?? '')) ?: null;
        $payload['responsible_name'] = trim((string) ($payload['responsible_name'] ?? '')) ?: null;
        $payload['responsible_email'] = trim((string) ($payload['responsible_email'] ?? '')) ?: null;
        $payload['responsible_phone'] = trim((string) ($payload['responsible_phone'] ?? '')) ?: null;
        $payload['reminder_email_enabled'] = (bool) ($payload['reminder_email_enabled'] ?? true);
        $payload['reminder_whatsapp_enabled'] = (bool) ($payload['reminder_whatsapp_enabled'] ?? false);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplatePayload(Request $request, Contractor $contractor, ?int $ignoreTemplateId = null): array
    {
        $payload = $request->validate([
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9._-]+$/i',
                Rule::unique('accounting_service_templates', 'code')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id))
                    ->ignore($ignoreTemplateId),
            ],
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'default_obligation_type' => ['nullable', 'string', 'max:80'],
            'default_document_type' => ['nullable', 'string', 'max:80'],
            'default_stage_code' => ['nullable', Rule::in(array_column($this->obligationStageOptions(), 'value'))],
            'checklist_items' => ['nullable', 'array'],
            'checklist_items.*.label' => ['nullable', 'string', 'max:180'],
            'checklist_items.*.done' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        $payload['code'] = strtolower(trim((string) ($payload['code'] ?? '')));
        $payload['name'] = trim((string) ($payload['name'] ?? ''));
        $payload['category'] = trim((string) ($payload['category'] ?? '')) ?: null;
        $payload['description'] = trim((string) ($payload['description'] ?? '')) ?: null;
        $payload['default_obligation_type'] = trim((string) ($payload['default_obligation_type'] ?? '')) ?: null;
        $payload['default_document_type'] = trim((string) ($payload['default_document_type'] ?? '')) ?: null;
        $payload['default_stage_code'] = (string) ($payload['default_stage_code'] ?? AccountingObligation::STAGE_BACKLOG);
        $payload['checklist_items'] = $this->normalizeChecklistPayload($payload['checklist_items'] ?? []);
        $payload['is_default'] = (bool) ($payload['is_default'] ?? false);
        $payload['is_active'] = (bool) ($payload['is_active'] ?? true);
        $payload['sort_order'] = max(0, (int) ($payload['sort_order'] ?? 0));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyObligationTemplatePayload(array $payload, Contractor $contractor): array
    {
        $template = $this->resolveTemplateById($contractor, $payload['template_id'] ?? null);
        if (! $template) {
            unset($payload['template_id']);

            return $payload;
        }

        if (trim((string) ($payload['obligation_type'] ?? '')) === '' && $template->default_obligation_type) {
            $payload['obligation_type'] = (string) $template->default_obligation_type;
        }

        if (trim((string) ($payload['stage_code'] ?? '')) === '' && $template->default_stage_code) {
            $payload['stage_code'] = (string) $template->default_stage_code;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDocumentTemplatePayload(array $payload, Contractor $contractor): array
    {
        $template = $this->resolveTemplateById($contractor, $payload['template_id'] ?? null);
        if (! $template) {
            unset($payload['template_id']);
            $payload['checklist_items'] = $this->normalizeChecklistPayload($payload['checklist_items'] ?? []);
            $payload['pending_items_count'] = (int) collect($payload['checklist_items'])
                ->filter(static fn (array $item): bool => ! (bool) ($item['done'] ?? false))
                ->count();

            return $payload;
        }

        if (trim((string) ($payload['document_type'] ?? '')) === '' && $template->default_document_type) {
            $payload['document_type'] = (string) $template->default_document_type;
        }

        $submittedChecklist = $this->normalizeChecklistPayload($payload['checklist_items'] ?? []);
        $payload['checklist_items'] = $submittedChecklist !== []
            ? $submittedChecklist
            : $this->normalizeChecklistPayload($template->checklist_items);

        $payload['pending_items_count'] = (int) collect($payload['checklist_items'])
            ->filter(static fn (array $item): bool => ! (bool) ($item['done'] ?? false))
            ->count();

        return $payload;
    }

    private function resolveTemplateById(Contractor $contractor, mixed $templateId): ?AccountingServiceTemplate
    {
        $id = (int) $templateId;
        if ($id <= 0) {
            return null;
        }

        return AccountingServiceTemplate::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->where(static function ($query) use ($contractor): void {
                $query->whereNull('contractor_id')->orWhere('contractor_id', $contractor->id);
            })
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveNextReferenceDate(array $payload, ?AccountingFeeEntry $entry = null): ?string
    {
        $frequency = strtolower(trim((string) ($payload['recurrence_frequency'] ?? 'none')));
        if ($frequency === 'none') {
            return null;
        }

        $providedDate = trim((string) ($payload['next_reference_date'] ?? ''));
        if ($providedDate !== '') {
            try {
                return Carbon::parse($providedDate)->toDateString();
            } catch (\Throwable) {
                // fallback below
            }
        }

        $interval = max(1, (int) ($payload['recurrence_interval'] ?? 1));
        $baseDate = trim((string) ($payload['due_date'] ?? ''));
        if ($baseDate === '' && $entry?->due_date) {
            $baseDate = $entry->due_date->toDateString();
        }
        if ($baseDate === '') {
            return null;
        }

        $parsedBase = Carbon::parse($baseDate);

        return match ($frequency) {
            'monthly' => $parsedBase->copy()->addMonthsNoOverflow($interval)->toDateString(),
            'quarterly' => $parsedBase->copy()->addMonthsNoOverflow(3 * $interval)->toDateString(),
            'yearly' => $parsedBase->copy()->addYears($interval)->toDateString(),
            default => null,
        };
    }

    private function createDocumentVersion(
        Request $request,
        Contractor $contractor,
        AccountingDocumentRequest $document,
        bool $forceCreate = false,
    ): ?AccountingDocumentVersion {
        $rules = [
            'version_file' => ['nullable', 'file', 'max:10240'],
            'version_notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($forceCreate) {
            $rules['version_file'][] = 'required_without:version_notes';
            $rules['version_notes'][] = 'required_without:version_file';
        }

        $validated = $request->validate($rules);
        $notes = trim((string) ($validated['version_notes'] ?? ''));
        $uploadedFile = $request->file('version_file');

        if (! ($uploadedFile instanceof UploadedFile) && $notes === '') {
            return null;
        }

        $nextVersion = ((int) $document->versions()->max('version_number')) + 1;
        if ($nextVersion <= 0) {
            $nextVersion = 1;
        }

        $filePath = null;
        $fileName = null;
        if ($uploadedFile instanceof UploadedFile) {
            $filePath = $uploadedFile->store(
                "contractors/{$contractor->id}/services/accounting/documents",
                'local'
            );
            $fileName = $uploadedFile->getClientOriginalName();
        }

        $version = AccountingDocumentVersion::query()->create([
            'contractor_id' => $contractor->id,
            'accounting_document_request_id' => $document->id,
            'created_by_user_id' => $request->user()?->id,
            'version_number' => $nextVersion,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'uploaded_at' => now(),
            'notes' => $notes !== '' ? $notes : null,
            'metadata' => $uploadedFile instanceof UploadedFile
                ? [
                    'mime_type' => $uploadedFile->getClientMimeType(),
                    'size' => (int) $uploadedFile->getSize(),
                ]
                : null,
        ]);

        $document->last_version_number = $nextVersion;
        if ($filePath !== null) {
            $document->file_path = $filePath;
        }
        $document->save();

        return $version;
    }

    private function deleteDocumentVersionFile(Contractor $contractor, mixed $value): void
    {
        $candidate = $this->normalizeDocumentStoragePath($value);
        if ($candidate === '' || ! str_starts_with($candidate, "contractors/{$contractor->id}/")) {
            return;
        }

        if (Storage::disk('local')->exists($candidate)) {
            Storage::disk('local')->delete($candidate);
        }
        if (Storage::disk('public')->exists($candidate)) {
            Storage::disk('public')->delete($candidate);
        }
    }

    private function normalizeDocumentStoragePath(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return '';
        }

        $path = parse_url($raw, PHP_URL_PATH);
        $candidate = is_string($path) && $path !== '' ? $path : $raw;

        if (str_starts_with($candidate, '/storage/')) {
            $candidate = substr($candidate, strlen('/storage/'));
        } elseif (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }

        return ltrim($candidate, '/');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDocumentVersionStorage(Contractor $contractor, mixed $value): array
    {
        $candidate = $this->normalizeDocumentStoragePath($value);
        if ($candidate === '' || ! str_starts_with($candidate, "contractors/{$contractor->id}/")) {
            return [null, null];
        }

        if (Storage::disk('local')->exists($candidate)) {
            return ['local', $candidate];
        }

        if (Storage::disk('public')->exists($candidate)) {
            if (! Storage::disk('local')->exists($candidate)) {
                $stream = Storage::disk('public')->readStream($candidate);
                if (is_resource($stream)) {
                    try {
                        Storage::disk('local')->writeStream($candidate, $stream);
                    } finally {
                        fclose($stream);
                    }
                }
            }

            if (Storage::disk('local')->exists($candidate)) {
                Storage::disk('public')->delete($candidate);

                return ['local', $candidate];
            }

            return ['public', $candidate];
        }

        return [null, null];
    }

    private function registerTaskHistory(
        Contractor $contractor,
        AccountingObligation $obligation,
        string $action,
        ?string $previousStage,
        ?string $currentStage,
        ?string $previousStatus,
        ?string $currentStatus,
        ?string $assignedToName,
        mixed $dueDate,
        ?string $notes,
        ?Request $request = null,
    ): void {
        $dueDateValue = null;
        if ($dueDate instanceof Carbon) {
            $dueDateValue = $dueDate->toDateString();
        } elseif (is_string($dueDate) && trim($dueDate) !== '') {
            $dueDateValue = Carbon::parse($dueDate)->toDateString();
        }

        AccountingTaskHistory::query()->create([
            'contractor_id' => $contractor->id,
            'accounting_obligation_id' => $obligation->id,
            'created_by_user_id' => $request?->user()?->id,
            'action' => trim($action),
            'previous_stage' => trim((string) ($previousStage ?? '')) ?: null,
            'current_stage' => trim((string) ($currentStage ?? '')) ?: null,
            'previous_status' => trim((string) ($previousStatus ?? '')) ?: null,
            'current_status' => trim((string) ($currentStatus ?? '')) ?: null,
            'assigned_to_name' => trim((string) ($assignedToName ?? '')) ?: null,
            'due_date' => $dueDateValue,
            'notes' => trim((string) ($notes ?? '')) ?: null,
            'metadata' => [
                'ip' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ],
        ]);
    }

    private function ensureDefaultTemplates(Contractor $contractor): void
    {
        $defaults = [
            [
                'code' => 'onboarding_cliente',
                'name' => 'Onboarding de cliente',
                'category' => 'Carteira',
                'description' => 'Checklist inicial para cadastro e ativação de cliente.',
                'default_obligation_type' => 'Onboarding',
                'default_document_type' => 'Cadastro',
                'default_stage_code' => AccountingObligation::STAGE_BACKLOG,
                'checklist_items' => [
                    ['label' => 'Contrato social atualizado', 'done' => false],
                    ['label' => 'Documento do responsável', 'done' => false],
                    ['label' => 'Comprovante de endereço', 'done' => false],
                ],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'fechamento_mensal',
                'name' => 'Fechamento mensal',
                'category' => 'Rotina',
                'description' => 'Modelo para rotina mensal de fechamento.',
                'default_obligation_type' => 'Fechamento',
                'default_document_type' => 'Contábil',
                'default_stage_code' => AccountingObligation::STAGE_IN_PROGRESS,
                'checklist_items' => [
                    ['label' => 'Extratos bancários', 'done' => false],
                    ['label' => 'Notas de entrada e saída', 'done' => false],
                    ['label' => 'Comprovantes de despesas', 'done' => false],
                ],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'folha_pagamento',
                'name' => 'Folha de pagamento',
                'category' => 'Departamento pessoal',
                'description' => 'Modelo para fechamento da folha mensal.',
                'default_obligation_type' => 'DP',
                'default_document_type' => 'Trabalhista',
                'default_stage_code' => AccountingObligation::STAGE_REVIEW,
                'checklist_items' => [
                    ['label' => 'Ponto e horas extras', 'done' => false],
                    ['label' => 'Admissões e demissões', 'done' => false],
                    ['label' => 'Eventos do eSocial', 'done' => false],
                ],
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],
        ];

        foreach ($defaults as $item) {
            AccountingServiceTemplate::query()->updateOrCreate(
                [
                    'contractor_id' => null,
                    'code' => (string) $item['code'],
                ],
                [
                    'name' => (string) $item['name'],
                    'category' => $item['category'],
                    'description' => $item['description'],
                    'default_obligation_type' => $item['default_obligation_type'],
                    'default_document_type' => $item['default_document_type'],
                    'default_stage_code' => $item['default_stage_code'],
                    'checklist_items' => $item['checklist_items'],
                    'is_default' => (bool) $item['is_default'],
                    'is_active' => (bool) $item['is_active'],
                    'sort_order' => (int) $item['sort_order'],
                ]
            );
        }
    }

    private function generateDocumentProtocolCode(Contractor $contractor): string
    {
        do {
            $candidate = sprintf(
                'DOC-%d-%s-%04d',
                (int) $contractor->id,
                now()->format('Ymd'),
                random_int(0, 9999)
            );

            $query = AccountingDocumentRequest::query()
                ->where('contractor_id', $contractor->id)
                ->where('protocol_code', $candidate);

            if (method_exists(AccountingDocumentRequest::query(), 'withTrashed')) {
                $query = AccountingDocumentRequest::withTrashed()
                    ->where('contractor_id', $contractor->id)
                    ->where('protocol_code', $candidate);
            }

            $exists = $query->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * @return array<int, array{label: string, done: bool}>
     */
    private function normalizeChecklistPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static function (mixed $item): ?array {
                if (is_string($item)) {
                    $label = trim($item);
                    if ($label === '') {
                        return null;
                    }

                    return ['label' => $label, 'done' => false];
                }

                if (! is_array($item)) {
                    return null;
                }

                $label = trim((string) ($item['label'] ?? $item['name'] ?? $item['title'] ?? ''));
                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'done' => (bool) ($item['done'] ?? $item['checked'] ?? false),
                ];
            })
            ->filter(static fn (?array $item): bool => $item !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toTemplatePayload(AccountingServiceTemplate $template): array
    {
        return [
            'id' => (int) $template->id,
            'contractor_id' => $template->contractor_id ? (int) $template->contractor_id : null,
            'code' => (string) $template->code,
            'name' => (string) $template->name,
            'category' => (string) ($template->category ?? ''),
            'description' => (string) ($template->description ?? ''),
            'default_obligation_type' => (string) ($template->default_obligation_type ?? ''),
            'default_document_type' => (string) ($template->default_document_type ?? ''),
            'default_stage_code' => (string) ($template->default_stage_code ?? AccountingObligation::STAGE_BACKLOG),
            'checklist_items' => $this->normalizeChecklistPayload($template->checklist_items),
            'is_default' => (bool) $template->is_default,
            'is_active' => (bool) $template->is_active,
            'sort_order' => (int) $template->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toHistoryPayload(AccountingTaskHistory $history): array
    {
        return [
            'id' => (int) $history->id,
            'accounting_obligation_id' => (int) $history->accounting_obligation_id,
            'created_by_user_id' => $history->created_by_user_id ? (int) $history->created_by_user_id : null,
            'created_by_name' => (string) ($history->createdBy?->name ?? 'Sistema'),
            'action' => (string) $history->action,
            'previous_stage' => (string) ($history->previous_stage ?? ''),
            'current_stage' => (string) ($history->current_stage ?? ''),
            'previous_status' => (string) ($history->previous_status ?? ''),
            'current_status' => (string) ($history->current_status ?? ''),
            'assigned_to_name' => (string) ($history->assigned_to_name ?? ''),
            'due_date' => optional($history->due_date)?->format('Y-m-d'),
            'notes' => (string) ($history->notes ?? ''),
            'created_at' => optional($history->created_at)?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocumentVersionPayload(Contractor $contractor, AccountingDocumentVersion $version): array
    {
        $filePath = trim((string) ($version->file_path ?? ''));
        $resolvedDisk = null;

        if ($filePath !== '') {
            [$resolvedDisk, $resolvedPath] = $this->resolveDocumentVersionStorage($contractor, $filePath);
            if ($resolvedPath !== null) {
                $filePath = $resolvedPath;
            }
        }

        $hasFile = $filePath !== '' && $resolvedDisk !== null;
        $fileUrl = $hasFile
            ? route('admin.services.accounting.documents.versions.download', ['accountingDocumentVersion' => $version->id])
            : null;
        $fileDownloadUrl = $hasFile
            ? route('admin.services.accounting.documents.versions.download', [
                'accountingDocumentVersion' => $version->id,
                'download' => 1,
            ])
            : null;

        return [
            'id' => (int) $version->id,
            'version_number' => (int) $version->version_number,
            'file_name' => (string) ($version->file_name ?? ''),
            'file_path' => $filePath !== '' ? $filePath : null,
            'file_url' => $fileUrl,
            'file_download_url' => $fileDownloadUrl,
            'uploaded_at' => optional($version->uploaded_at)?->format('d/m/Y H:i'),
            'notes' => (string) ($version->notes ?? ''),
            'created_by_user_id' => $version->created_by_user_id ? (int) $version->created_by_user_id : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toReminderLogPayload(AccountingReminderLog $log): array
    {
        return [
            'id' => (int) $log->id,
            'client_id' => $log->client_id ? (int) $log->client_id : null,
            'channel' => (string) $log->channel,
            'target' => (string) ($log->target ?? ''),
            'context_type' => (string) $log->context_type,
            'context_id' => $log->context_id ? (int) $log->context_id : null,
            'status' => (string) ($log->status ?? ''),
            'message' => (string) ($log->message ?? ''),
            'sent_at' => optional($log->sent_at)?->format('d/m/Y H:i'),
            'created_at' => optional($log->created_at)?->format('d/m/Y H:i'),
        ];
    }

    private function markOverdueFees(Contractor $contractor): void
    {
        $timezone = trim((string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo')));
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
        }

        AccountingFeeEntry::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', AccountingFeeEntry::STATUS_PENDING)
            ->whereDate('due_date', '<', now($timezone)->toDateString())
            ->update([
                'status' => AccountingFeeEntry::STATUS_OVERDUE,
            ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function obligationStageOptions(): array
    {
        return [
            ['value' => AccountingObligation::STAGE_BACKLOG, 'label' => 'Backlog'],
            ['value' => AccountingObligation::STAGE_IN_PROGRESS, 'label' => 'Em andamento'],
            ['value' => AccountingObligation::STAGE_REVIEW, 'label' => 'Revisão'],
            ['value' => AccountingObligation::STAGE_DONE, 'label' => 'Concluída'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function feeRecurrenceOptions(): array
    {
        return [
            ['value' => 'none', 'label' => 'Sem recorrência'],
            ['value' => 'monthly', 'label' => 'Mensal'],
            ['value' => 'quarterly', 'label' => 'Trimestral'],
            ['value' => 'yearly', 'label' => 'Anual'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function feeAdjustmentOptions(): array
    {
        return [
            ['value' => 'none', 'label' => 'Sem ajuste'],
            ['value' => 'percent', 'label' => 'Percentual'],
            ['value' => 'fixed', 'label' => 'Valor fixo'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function permissionMatrix(): array
    {
        return [
            'fees' => [
                'create' => 'finance',
                'update' => 'finance',
                'delete' => 'finance',
            ],
            'obligations' => [
                'create' => 'tasks',
                'update' => 'tasks',
                'delete' => 'tasks',
            ],
            'documents' => [
                'create' => 'documents',
                'update' => 'documents',
                'delete' => 'documents',
            ],
        ];
    }

    private function ensureAccountingBusinessType(Contractor $contractor): void
    {
        abort_unless(
            $contractor->businessType() === Contractor::BUSINESS_TYPE_ACCOUNTING,
            403,
            'Módulo disponível apenas para contratantes de contabilidade.',
        );
    }

    private function resolveOwnedFeeEntry(Contractor $contractor, AccountingFeeEntry $entry): AccountingFeeEntry
    {
        abort_unless((int) $entry->contractor_id === (int) $contractor->id, 404);

        return $entry;
    }

    private function resolveOwnedObligation(Contractor $contractor, AccountingObligation $obligation): AccountingObligation
    {
        abort_unless((int) $obligation->contractor_id === (int) $contractor->id, 404);

        return $obligation;
    }

    private function resolveOwnedDocument(Contractor $contractor, AccountingDocumentRequest $document): AccountingDocumentRequest
    {
        abort_unless((int) $document->contractor_id === (int) $contractor->id, 404);
        $document->loadMissing('versions');

        return $document;
    }

    private function resolveOwnedDocumentVersion(Contractor $contractor, AccountingDocumentVersion $version): AccountingDocumentVersion
    {
        abort_unless((int) $version->contractor_id === (int) $contractor->id, 404);
        $version->loadMissing('documentRequest:id,contractor_id');
        abort_unless((int) ($version->documentRequest?->contractor_id ?? 0) === (int) $contractor->id, 404);

        return $version;
    }

    private function resolveOwnedClient(Contractor $contractor, Client $client): Client
    {
        abort_unless((int) $client->contractor_id === (int) $contractor->id, 404);

        return $client;
    }

    private function resolveOwnedTemplate(Contractor $contractor, AccountingServiceTemplate $template): AccountingServiceTemplate
    {
        abort_unless((int) $template->contractor_id === (int) $contractor->id, 404);

        return $template;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function feeStatusOptions(): array
    {
        return [
            ['value' => AccountingFeeEntry::STATUS_PENDING, 'label' => 'Pendente'],
            ['value' => AccountingFeeEntry::STATUS_PAID, 'label' => 'Pago'],
            ['value' => AccountingFeeEntry::STATUS_OVERDUE, 'label' => 'Em atraso'],
            ['value' => AccountingFeeEntry::STATUS_CANCELLED, 'label' => 'Cancelado'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function obligationStatusOptions(): array
    {
        return [
            ['value' => AccountingObligation::STATUS_PENDING, 'label' => 'Pendente'],
            ['value' => AccountingObligation::STATUS_SENT, 'label' => 'Enviada'],
            ['value' => AccountingObligation::STATUS_COMPLETED, 'label' => 'Concluída'],
            ['value' => AccountingObligation::STATUS_OVERDUE, 'label' => 'Em atraso'],
            ['value' => AccountingObligation::STATUS_CANCELLED, 'label' => 'Cancelada'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return [
            ['value' => AccountingObligation::PRIORITY_LOW, 'label' => 'Baixa'],
            ['value' => AccountingObligation::PRIORITY_NORMAL, 'label' => 'Normal'],
            ['value' => AccountingObligation::PRIORITY_HIGH, 'label' => 'Alta'],
            ['value' => AccountingObligation::PRIORITY_CRITICAL, 'label' => 'Crítica'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function documentStatusOptions(): array
    {
        return [
            ['value' => AccountingDocumentRequest::STATUS_PENDING, 'label' => 'Pendente'],
            ['value' => AccountingDocumentRequest::STATUS_RECEIVED, 'label' => 'Recebido'],
            ['value' => AccountingDocumentRequest::STATUS_VALIDATED, 'label' => 'Validado'],
            ['value' => AccountingDocumentRequest::STATUS_REJECTED, 'label' => 'Rejeitado'],
        ];
    }
}

