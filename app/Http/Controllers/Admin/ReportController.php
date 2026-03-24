<?php

namespace App\Http\Controllers\Admin;

use App\Application\Reports\Services\AdminReportService;
use App\Http\Controllers\Controller;
use App\Models\ReportExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function exportSales(Request $request): RedirectResponse
    {
        return $this->service->exportSales($request);
    }

    public function export(Request $request): RedirectResponse
    {
        return $this->service->export($request);
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        return $this->service->download($request, $reportExport);
    }
}
