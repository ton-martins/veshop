<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractorStorageQuotaService
{
    public function resolveUsageBytes(Contractor $contractor): int
    {
        $root = $this->rootPath($contractor);
        $disk = Storage::disk('public');

        if (! $disk->exists($root)) {
            return 0;
        }

        $usage = 0;
        foreach ($disk->allFiles($root) as $filePath) {
            try {
                $usage += (int) $disk->size($filePath);
            } catch (\Throwable) {
                // ignora arquivos inacessíveis para não interromper o fluxo de upload
            }
        }

        return max(0, (int) $usage);
    }

    public function resolveLimitBytes(Contractor $contractor): ?int
    {
        $contractor->loadMissing('plan:id,storage_limit_gb');

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $rawFromSettings = $settings['storage_limit_gb'] ?? null;
        $rawFromPlan = $contractor->plan?->storage_limit_gb;
        $rawValue = $rawFromSettings !== null && $rawFromSettings !== ''
            ? $rawFromSettings
            : $rawFromPlan;

        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $limitGb = (float) $rawValue;
        if (! is_finite($limitGb) || $limitGb <= 0) {
            return null;
        }

        return (int) floor($limitGb * 1024 * 1024 * 1024);
    }

    public function resolveRemainingBytes(Contractor $contractor): ?int
    {
        $limit = $this->resolveLimitBytes($contractor);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->resolveUsageBytes($contractor));
    }

    public function assertCanStoreBytes(Contractor $contractor, int $incomingBytes): void
    {
        $incoming = max(0, $incomingBytes);
        if ($incoming <= 0) {
            return;
        }

        $limit = $this->resolveLimitBytes($contractor);
        if ($limit === null) {
            return;
        }

        $usage = $this->resolveUsageBytes($contractor);
        if (($usage + $incoming) <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'storage_quota' => 'Limite de armazenamento do plano atingido. Remova arquivos ou ajuste seu plano.',
        ]);
    }

    public function resolveProductImageLimitByPlan(Contractor $contractor): int
    {
        $limitBytes = $this->resolveLimitBytes($contractor);
        $limitGb = $limitBytes !== null ? ($limitBytes / (1024 * 1024 * 1024)) : null;

        if ($limitGb !== null && $limitGb <= 1.0) {
            return 3;
        }

        return 5;
    }

    public function rootPath(Contractor $contractor): string
    {
        return "contractors/{$contractor->id}";
    }
}

