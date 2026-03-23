<?php

namespace App\Http\Controllers\Admin;

use App\Application\Finance\Services\AdminFinancialEntryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFinancialEntryRequest;
use App\Http\Requests\Admin\UpdateFinancialEntryRequest;
use App\Models\FinancialEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinancialEntryController extends Controller
{
    public function __construct(
        private readonly AdminFinancialEntryService $service,
    ) {}

    public function store(StoreFinancialEntryRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdateFinancialEntryRequest $request, FinancialEntry $financialEntry): RedirectResponse
    {
        return $this->service->update($request, $financialEntry);
    }

    public function destroy(Request $request, FinancialEntry $financialEntry): RedirectResponse
    {
        return $this->service->destroy($request, $financialEntry);
    }
}
