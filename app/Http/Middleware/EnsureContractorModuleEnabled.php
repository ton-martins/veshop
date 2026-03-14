<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContractorModuleEnabled
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $requestedModule = strtolower(trim($module));
        abort_if(! in_array($requestedModule, Contractor::availableModules(), true), 403, 'Módulo inválido.');

        $user->loadMissing('contractors');
        $availableContractors = $user->contractors->values();
        abort_if($availableContractors->isEmpty(), 403, 'Nenhum contratante disponível para este usuário.');

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

        abort_unless($currentContractor, 403, 'Contratante ativo não encontrado.');
        abort_unless(
            $currentContractor->hasModule($requestedModule),
            403,
            'Módulo não habilitado para o contratante ativo.'
        );

        return $next($request);
    }
}

