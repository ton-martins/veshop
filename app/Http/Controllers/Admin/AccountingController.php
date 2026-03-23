<?php

namespace App\Http\Controllers\Admin;

use App\Application\Accounting\Services\AdminAccountingService;
use App\Http\Controllers\Controller;
use App\Models\AccountingDocumentRequest;
use App\Models\AccountingDocumentVersion;
use App\Models\AccountingFeeEntry;
use App\Models\AccountingObligation;
use App\Models\AccountingServiceTemplate;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingController extends Controller
{
    public function __construct(
        private readonly AdminAccountingService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function storeFee(Request $request): RedirectResponse
    {
        return $this->service->storeFee($request);
    }

    public function updateFee(Request $request, AccountingFeeEntry $accountingFeeEntry): RedirectResponse
    {
        return $this->service->updateFee($request, $accountingFeeEntry);
    }

    public function destroyFee(Request $request, AccountingFeeEntry $accountingFeeEntry): RedirectResponse
    {
        return $this->service->destroyFee($request, $accountingFeeEntry);
    }

    public function storeObligation(Request $request): RedirectResponse
    {
        return $this->service->storeObligation($request);
    }

    public function updateObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        return $this->service->updateObligation($request, $accountingObligation);
    }

    public function destroyObligation(Request $request, AccountingObligation $accountingObligation): RedirectResponse
    {
        return $this->service->destroyObligation($request, $accountingObligation);
    }

    public function storeDocument(Request $request): RedirectResponse
    {
        return $this->service->storeDocument($request);
    }

    public function updateDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        return $this->service->updateDocument($request, $accountingDocumentRequest);
    }

    public function destroyDocument(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        return $this->service->destroyDocument($request, $accountingDocumentRequest);
    }

    public function storeClientProfile(Request $request): RedirectResponse
    {
        return $this->service->storeClientProfile($request);
    }

    public function updateClientProfile(Request $request, Client $client): RedirectResponse
    {
        return $this->service->updateClientProfile($request, $client);
    }

    public function destroyClientProfile(Request $request, Client $client): RedirectResponse
    {
        return $this->service->destroyClientProfile($request, $client);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        return $this->service->storeTemplate($request);
    }

    public function updateTemplate(Request $request, AccountingServiceTemplate $accountingServiceTemplate): RedirectResponse
    {
        return $this->service->updateTemplate($request, $accountingServiceTemplate);
    }

    public function destroyTemplate(Request $request, AccountingServiceTemplate $accountingServiceTemplate): RedirectResponse
    {
        return $this->service->destroyTemplate($request, $accountingServiceTemplate);
    }

    public function processRecurringFees(Request $request): RedirectResponse
    {
        return $this->service->processRecurringFees($request);
    }

    public function processReminders(Request $request): RedirectResponse
    {
        return $this->service->processReminders($request);
    }

    public function storeDocumentVersion(Request $request, AccountingDocumentRequest $accountingDocumentRequest): RedirectResponse
    {
        return $this->service->storeDocumentVersion($request, $accountingDocumentRequest);
    }

    public function downloadDocumentVersion(Request $request, AccountingDocumentVersion $accountingDocumentVersion): StreamedResponse
    {
        return $this->service->downloadDocumentVersion($request, $accountingDocumentVersion);
    }
}
