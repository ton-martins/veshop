<?php

namespace App\Services\Brazil;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CnpjLookupService
{
    /**
     * @return array<string, string>
     */
    public function lookup(string $cnpj): array
    {
        $digits = preg_replace('/\D+/', '', $cnpj);
        if (! is_string($digits) || strlen($digits) !== 14) {
            throw ValidationException::withMessages([
                'cnpj' => 'Informe um CNPJ válido com 14 dígitos.',
            ]);
        }

        try {
            $response = Http::baseUrl($this->baseUrl())
                ->acceptJson()
                ->timeout($this->timeoutSeconds())
                ->get('/cnpj/v1/'.$digits);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'cnpj' => 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.',
            ]);
        }

        if ($response->status() === 404) {
            throw ValidationException::withMessages([
                'cnpj' => 'CNPJ não encontrado na Brasil API.',
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'cnpj' => 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.',
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'cnpj' => 'Resposta inválida da consulta de CNPJ.',
            ]);
        }

        return [
            'cnpj' => $digits,
            'razao_social' => trim((string) ($payload['razao_social'] ?? '')),
            'nome_fantasia' => trim((string) ($payload['nome_fantasia'] ?? '')),
            'email' => $this->resolveEmail($payload),
            'phone' => $this->resolvePhone($payload),
            'cep' => $this->formatCep($this->digitsOnly((string) ($payload['cep'] ?? ''))),
            'street' => trim((string) ($payload['logradouro'] ?? '')),
            'number' => trim((string) ($payload['numero'] ?? '')),
            'complement' => trim((string) ($payload['complemento'] ?? '')),
            'neighborhood' => trim((string) ($payload['bairro'] ?? '')),
            'city' => trim((string) ($payload['municipio'] ?? '')),
            'uf' => Str::upper(trim((string) ($payload['uf'] ?? ''))),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.brasilapi.base_url', 'https://brasilapi.com.br/api'), '/');
    }

    private function timeoutSeconds(): int
    {
        return max(2, (int) config('services.brasilapi.timeout', 10));
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEmail(array $payload): string
    {
        $candidates = [
            $payload['email'] ?? null,
            $payload['email_empresa'] ?? null,
            $payload['correio_eletronico'] ?? null,
            $payload['endereco_eletronico'] ?? null,
            data_get($payload, 'contato.email'),
        ];

        foreach ($candidates as $candidate) {
            $email = trim((string) ($candidate ?? ''));
            if ($email !== '') {
                return $email;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePhone(array $payload): string
    {
        $dddOne = $this->digitsOnly((string) ($payload['ddd'] ?? ''));
        $phoneOne = $this->digitsOnly((string) ($payload['telefone_1'] ?? $payload['telefone1'] ?? ''));
        $phoneTwo = $this->digitsOnly((string) ($payload['telefone_2'] ?? $payload['telefone2'] ?? ''));

        $candidates = [
            $payload['ddd_telefone_1'] ?? null,
            $payload['ddd_telefone_2'] ?? null,
            $payload['telefone'] ?? null,
            $payload['telefone_1'] ?? null,
            $payload['telefone1'] ?? null,
            ($dddOne !== '' && $phoneOne !== '') ? $dddOne.$phoneOne : null,
            ($dddOne !== '' && $phoneTwo !== '') ? $dddOne.$phoneTwo : null,
            $payload['fone'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $digits = $this->digitsOnly((string) ($candidate ?? ''));
            if ($digits === '') {
                continue;
            }

            $normalized = $this->normalizePhoneDigits($digits);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function normalizePhoneDigits(string $digits): string
    {
        $normalized = $digits;

        while (strlen($normalized) > 11 && str_starts_with($normalized, '55')) {
            $normalized = substr($normalized, 2);
        }

        if (strlen($normalized) > 11) {
            $normalized = substr($normalized, 0, 11);
        }

        if (strlen($normalized) < 10) {
            return '';
        }

        return $normalized;
    }

    private function formatCep(string $digits): string
    {
        if (strlen($digits) !== 8) {
            return '';
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }
}
