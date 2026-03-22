<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\StoreFinancialEntryRequest;
use App\Http\Requests\Admin\UpdateFinancialEntryRequest;
use App\Models\Contractor;
use App\Models\FinancialEntry;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FinancialEntryController extends Controller
{
    use ResolvesCurrentContractor;

    public function store(StoreFinancialEntryRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        $this->assertPaymentMethodOwnership($contractor, $data['payment_method_id'] ?? null);

        $documentPath = null;
        $documentOriginalName = null;
        if ($request->hasFile('document')) {
            $document = $request->file('document');
            $documentPath = $document->store("contractors/{$contractor->id}/finance/documents", 'public');
            $documentOriginalName = trim((string) $document->getClientOriginalName());
        }

        if (($data['status'] ?? FinancialEntry::STATUS_PENDING) !== FinancialEntry::STATUS_PAID) {
            $data['paid_at'] = null;
            $data['payment_method_id'] = null;
        } elseif (empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        FinancialEntry::query()->create([
            'contractor_id' => $contractor->id,
            'type' => $data['type'],
            'status' => $data['status'],
            'counterparty_name' => $data['counterparty_name'],
            'reference' => $this->blankToNull($data['reference'] ?? null),
            'amount' => (float) $data['amount'],
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'notes' => $this->blankToNull($data['notes'] ?? null),
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'document_path' => $documentPath,
            'document_original_name' => $documentOriginalName !== '' ? $documentOriginalName : null,
            'created_by_id' => $request->user()?->id,
            'updated_by_id' => $request->user()?->id,
        ]);

        return back()->with('status', 'Lançamento financeiro criado com sucesso.');
    }

    public function update(UpdateFinancialEntryRequest $request, FinancialEntry $financialEntry): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $entry = $this->resolveOwnedEntry($contractor, $financialEntry);
        $data = $request->validated();

        $this->assertPaymentMethodOwnership($contractor, $data['payment_method_id'] ?? null);

        $removeDocument = (bool) ($data['remove_document'] ?? false);

        if ($request->hasFile('document')) {
            if ($entry->document_path && Storage::disk('public')->exists($entry->document_path)) {
                Storage::disk('public')->delete($entry->document_path);
            }

            $document = $request->file('document');
            $entry->document_path = $document->store("contractors/{$contractor->id}/finance/documents", 'public');
            $entry->document_original_name = trim((string) $document->getClientOriginalName()) ?: null;
        } elseif ($removeDocument) {
            if ($entry->document_path && Storage::disk('public')->exists($entry->document_path)) {
                Storage::disk('public')->delete($entry->document_path);
            }

            $entry->document_path = null;
            $entry->document_original_name = null;
        }

        if (($data['status'] ?? FinancialEntry::STATUS_PENDING) !== FinancialEntry::STATUS_PAID) {
            $data['paid_at'] = null;
            $data['payment_method_id'] = null;
        } elseif (empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $entry->fill([
            'type' => $data['type'],
            'status' => $data['status'],
            'counterparty_name' => $data['counterparty_name'],
            'reference' => $this->blankToNull($data['reference'] ?? null),
            'amount' => (float) $data['amount'],
            'issue_date' => $data['issue_date'] ?? null,
            'due_date' => $data['due_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'notes' => $this->blankToNull($data['notes'] ?? null),
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'updated_by_id' => $request->user()?->id,
        ])->save();

        return back()->with('status', 'Lançamento financeiro atualizado com sucesso.');
    }

    public function destroy(Request $request, FinancialEntry $financialEntry): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $entry = $this->resolveOwnedEntry($contractor, $financialEntry);

        if ($entry->document_path && Storage::disk('public')->exists($entry->document_path)) {
            Storage::disk('public')->delete($entry->document_path);
        }

        $entry->delete();

        return back()->with('status', 'Lançamento financeiro removido com sucesso.');
    }

    private function resolveOwnedEntry(Contractor $contractor, FinancialEntry $entry): FinancialEntry
    {
        abort_unless((int) $entry->contractor_id === (int) $contractor->id, 404);

        return $entry;
    }

    private function assertPaymentMethodOwnership(Contractor $contractor, ?int $paymentMethodId): void
    {
        if ($paymentMethodId === null || $paymentMethodId <= 0) {
            return;
        }

        $belongsToContractor = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $paymentMethodId)
            ->exists();

        if (! $belongsToContractor) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Forma de pagamento inválida para o contratante ativo.',
            ]);
        }
    }


    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}


