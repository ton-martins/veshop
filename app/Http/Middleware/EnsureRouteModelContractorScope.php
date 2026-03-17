<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRouteModelContractorScope
{
    public function __construct(
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            return $next($request);
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId <= 0) {
            return $next($request);
        }

        $route = $request->route();
        if (! $route) {
            return $next($request);
        }

        foreach ($route->parameters() as $parameterName => $parameterValue) {
            if (! $parameterValue instanceof Model || $parameterValue instanceof Contractor) {
                continue;
            }

            $attributes = $parameterValue->getAttributes();
            if (! array_key_exists('contractor_id', $attributes)) {
                continue;
            }

            $resourceContractorId = (int) ($parameterValue->getAttribute('contractor_id') ?? 0);
            if ($resourceContractorId <= 0 || $resourceContractorId === $sessionContractorId) {
                continue;
            }

            $this->securityAuditLogger->log(
                $request,
                'tenant.resource_scope_violation',
                SecurityAuditLog::SEVERITY_CRITICAL,
                $sessionContractorId,
                [
                    'route_parameter' => $parameterName,
                    'resource_type' => $parameterValue::class,
                    'resource_id' => (string) $parameterValue->getKey(),
                    'resource_contractor_id' => $resourceContractorId,
                    'session_contractor_id' => $sessionContractorId,
                ],
            );

            abort(404);
        }

        return $next($request);
    }
}

