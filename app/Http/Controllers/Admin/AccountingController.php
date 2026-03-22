<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\AccountingDocumentRequest;
use App\Models\AccountingFeeEntry;
use App\Models\AccountingObligation;
use App\Models\Client;
use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $enabledModules = $contractor->enabledModules();

        $clients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Client $client): array => [
                'id' => (int) $client->id,
                'name' => (string) $client->name,
            ])
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
                'paid_at' => optional($entry->paid_at)?->format('Y-m-d\TH:i'),
                'notes' => $entry->notes ? (string) $entry->notes : '',
            ])
            ->values()
            ->all();

        $obligations = AccountingObligation::query()
            ->where('contractor_id', $contractor->id)
            ->with('client:id,name')
            ->orderBy('due_date')
            ->limit(80)
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
                'completed_at' => optional($obligation->completed_at)?->format('Y-m-d\TH:i'),
                'notes' => $obligation->notes ? (string) $obligation->notes : '',
            ])
            ->values()
            ->all();

        $documents = AccountingDocumentRequest::query()
            ->where('contractor_id', $contractor->id)
            ->with('client:id,name')
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->map(static fn (AccountingDocumentRequest $document): array => [
                'id' => (int) $document->id,
                'client_id' => $document->client_id ? (int) $document->client_id : null,
                'client_name' => $document->client?->name ? (string) $document->client->name : 'Não informado',
                'title' => (string) $document->title,
                'document_type' => $document->document_type ? (string) $document->document_type : '',
                'due_date' => optional($document->due_date)?->format('Y-m-d'),
                'status' => (string) $document->status,
                'received_at' => optional($document->received_at)?->format('Y-m-d\TH:i'),
                'notes' => $document->notes ? (string) $document->notes : '',
            ])
            ->values()
            ->all();

        $stats = [
            'fees_pending' => (float) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('status', [AccountingFeeEntry::STATUS_PENDING, AccountingFeeEntry::STATUS_OVERDUE])
                ->sum('amount'),
            'fees_received_month' => (float) AccountingFeeEntry::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingFeeEntry::STATUS_PAID)
                ->whereBetween('paid_at', [now($contractor->timezone)->startOfMonth(), now($contractor->timezone)->endOfMonth()])
                ->sum('paid_amount'),
            'obligations_due' => (int) AccountingObligation::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('status', [AccountingObligation::STATUS_PENDING, AccountingObligation::STATUS_SENT])
                ->whereDate('due_date', '<=', now($contractor->timezone)->addDays(7)->toDateString())
                ->count(),
            'documents_pending' => (int) AccountingDocumentRequest::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', AccountingDocumentRequest::STATUS_PENDING)
                ->count(),
        ];

        return Inertia::render('Admin/Services/Accounting', [
            'clients' => $clients,
            'fees' => $fees,
            'obligations' => $obligations,
            'documents' => $documents,
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
        ]);
    }

    public function storeFee(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        AccountingFeeEntry::query()->create(
            array_merge(
                $this->validateFeePayload($request, $contractor),
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
        $entry->fill($this->validateFeePayload($request, $contractor))->save();

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

        AccountingObligation::query()->create(
            array_merge(
                $this->validateObligationPayload($request, $contractor),
                ['contractor_id' => $contractor->id],
            )
        );

        return back()->with('status', 'Obrigação cadastrada com sucesso.');
    }

    public function updateObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $obligation = $this->resolveOwnedObligation($contractor, $accountingObligation);
        $obligation->fill($this->validateObligationPayload($request, $contractor))->save();

        return back()->with('status', 'Obrigação atualizada com sucesso.');
    }

    public function destroyObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $obligation = $this->resolveOwnedObligation($contractor, $accountingObligation);
        $obligation->delete();

        return back()->with('status', 'Obrigação removida com sucesso.');
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        AccountingDocumentRequest::query()->create(
            array_merge(
                $this->validateDocumentPayload($request, $contractor),
                ['contractor_id' => $contractor->id],
            )
        );

        return back()->with('status', 'Solicitação de documento cadastrada com sucesso.');
    }

    public function updateDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $document = $this->resolveOwnedDocument($contractor, $accountingDocumentRequest);
        $document->fill($this->validateDocumentPayload($request, $contractor))->save();

        return back()->with('status', 'Solicitação de documento atualizada com sucesso.');
    }

    public function destroyDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $this->ensureAccountingBusinessType($contractor);

        $document = $this->resolveOwnedDocument($contractor, $accountingDocumentRequest);
        $document->delete();

        return back()->with('status', 'Solicitação de documento removida com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFeePayload(Request $request, Contractor $contractor): array
    {
        return $request->validate([
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
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateObligationPayload(Request $request, Contractor $contractor): array
    {
        return $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'title' => ['required', 'string', 'max:180'],
            'obligation_type' => ['nullable', 'string', 'max:80'],
            'competence_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_column($this->obligationStatusOptions(), 'value'))],
            'priority' => ['required', Rule::in(array_column($this->priorityOptions(), 'value'))],
            'completed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocumentPayload(Request $request, Contractor $contractor): array
    {
        return $request->validate([
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'title' => ['required', 'string', 'max:180'],
            'document_type' => ['nullable', 'string', 'max:80'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_column($this->documentStatusOptions(), 'value'))],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
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

        return $document;
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


