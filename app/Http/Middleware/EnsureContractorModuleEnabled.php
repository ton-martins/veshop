<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use App\Models\Module;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditLogger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureContractorModuleEnabled
{
    public function __construct(
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $user = $request->user();
        abort_unless($user, 403);
        if ($user->isMaster()) {
            return $next($request);
        }

        $requestedModules = collect($modules)
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($requestedModules === []) {
            $this->securityAuditLogger->log(
                $request,
                'module.access_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $request->session()->get('current_contractor_id', 0),
                [
                    'reason' => 'invalid_module_constraint',
                    'requested_modules' => $modules,
                ],
            );
            abort(403, 'Modulo invalido.');
        }

        $user->loadMissing('contractors.modules');
        $availableContractors = $user->contractors->values();
        abort_if($availableContractors->isEmpty(), 403, 'Nenhum contratante disponivel para este usuario.');

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        $currentContractor = $sessionContractorId > 0
            ? $availableContractors->firstWhere('id', $sessionContractorId)
            : null;

        if (! $currentContractor) {
            $currentContractor = $availableContractors->first();

            if ($currentContractor) {
                $request->session()->put('current_contractor_id', $currentContractor->id);
            }
        }

        if (! $currentContractor) {
            return $this->redirectToAdminHome('Contratante ativo nao encontrado.');
        }

        $acceptedModulesByRequest = $this->resolveAcceptedModulesByRequest($requestedModules, $currentContractor);
        foreach ($acceptedModulesByRequest as $acceptedModules) {
            abort_if($acceptedModules === [], 403, 'Modulo invalido.');
        }

        $hasAnyRequestedModule = collect($acceptedModulesByRequest)
            ->contains(
                fn (array $acceptedModules): bool => collect($acceptedModules)
                    ->contains(fn (string $moduleCode): bool => $currentContractor->hasModule($moduleCode))
            );

        if (! $hasAnyRequestedModule) {
            $this->securityAuditLogger->log(
                $request,
                'module.access_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $currentContractor->id,
                [
                    'reason' => 'module_not_enabled_for_contractor',
                    'requested_modules' => $requestedModules,
                    'accepted_modules' => $acceptedModulesByRequest,
                ],
            );

            return $this->redirectToAdminHome('Modulo nao habilitado para o contratante ativo.');
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function legacyFallbackModuleCodes(string $moduleCode): array
    {
        if (in_array($moduleCode, [Contractor::MODULE_COMMERCIAL, Contractor::MODULE_SERVICES], true)) {
            return [$moduleCode];
        }

        return match ($moduleCode) {
            'services',
            'services_catalog',
            'service_orders',
            'schedule',
            'workshop',
            'tasks',
            'documents' => [Contractor::MODULE_SERVICES],
            default => [Contractor::MODULE_COMMERCIAL],
        };
    }

    /**
     * @param array<int, string> $requestedModules
     * @return array<string, array<int, string>>
     */
    private function resolveAcceptedModulesByRequest(array $requestedModules, Contractor $contractor): array
    {
        $hasModuleCatalog = Schema::hasTable('modules');
        $acceptedModulesByRequest = [];

        foreach ($requestedModules as $requestedModule) {
            if (! $hasModuleCatalog) {
                $acceptedModulesByRequest[$requestedModule] = $this->legacyFallbackModuleCodes($requestedModule);
                continue;
            }

            $isValid = Module::query()
                ->where('code', $requestedModule)
                ->where('is_active', true)
                ->exists();

            if (! $isValid) {
                $acceptedModulesByRequest[$requestedModule] = [];
                continue;
            }

            $acceptedModulesByRequest[$requestedModule] = [$requestedModule];
        }

        return $acceptedModulesByRequest;
    }

    private function redirectToAdminHome(string $statusMessage): RedirectResponse
    {
        return redirect()
            ->route('admin.home')
            ->with('status', $statusMessage);
    }
}
