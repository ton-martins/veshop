<?php

namespace App\Http\Requests\Admin;

use App\Models\FinancialEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => strtolower(trim((string) $this->input('type', FinancialEntry::TYPE_PAYABLE))),
            'status' => strtolower(trim((string) $this->input('status', FinancialEntry::STATUS_PENDING))),
            'counterparty_name' => trim((string) $this->input('counterparty_name', '')),
            'reference' => trim((string) $this->input('reference', '')),
            'notes' => trim((string) $this->input('notes', '')),
            'amount' => $this->filled('amount') ? (float) $this->input('amount') : null,
            'payment_method_id' => $this->filled('payment_method_id')
                ? (int) $this->input('payment_method_id')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([FinancialEntry::TYPE_PAYABLE, FinancialEntry::TYPE_RECEIVABLE])],
            'status' => ['required', 'string', Rule::in([FinancialEntry::STATUS_PENDING, FinancialEntry::STATUS_PAID, FinancialEntry::STATUS_CANCELLED])],
            'counterparty_name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'issue_date' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'document' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counterparty_name.required' => 'O campo fornecedor, cliente ou descrição da despesa é obrigatório.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counterparty_name' => 'fornecedor, cliente ou descrição da despesa',
            'reference' => 'referência do documento',
            'amount' => 'valor',
            'issue_date' => 'data de emissão',
            'due_date' => 'data de vencimento',
            'paid_at' => 'data de baixa',
            'payment_method_id' => 'forma de pagamento',
            'document' => 'documento',
        ];
    }
}
