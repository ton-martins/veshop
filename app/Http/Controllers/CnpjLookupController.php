<?php

namespace App\Http\Controllers;

use App\Services\Brazil\CnpjLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CnpjLookupController extends Controller
{
    public function __construct(
        private readonly CnpjLookupService $service,
    ) {}

    public function __invoke(Request $request, string $cnpj): JsonResponse
    {
        try {
            $company = $this->service->lookup($cnpj);

            return response()->json([
                'ok' => true,
                'data' => $company,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'ok' => false,
                'message' => (string) ($exception->validator?->errors()?->first('cnpj') ?? 'CNPJ inválido.'),
            ], 422);
        } catch (Throwable $exception) {
            Log::warning('cnpj_lookup.unexpected_error', [
                'cnpj' => preg_replace('/\D+/', '', $cnpj) ?? '',
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.',
            ], 503);
        }
    }
}

