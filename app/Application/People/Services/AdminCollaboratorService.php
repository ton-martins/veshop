<?php

namespace App\Application\People\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Collaborator;
use App\Models\Contractor;
use App\Models\ServiceCategory;
use App\Support\BrazilData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminCollaboratorService
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $isServices = $contractor->niche() === Contractor::NICHE_SERVICES;

        $collaborators = Collaborator::query()
            ->where('contractor_id', $contractor->id)
            ->with([
                'serviceCategories:id,name',
                'serviceAppointments' => static fn ($query) => $query
                    ->where('starts_at', '>=', now()->subDay())
                    ->orderBy('starts_at')
                    ->limit(3)
                    ->select(['id', 'collaborator_id', 'starts_at', 'ends_at', 'status', 'title']),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(static fn (Collaborator $collaborator): array => [
                'id' => (int) $collaborator->id,
                'name' => (string) $collaborator->name,
                'email' => trim((string) ($collaborator->email ?? '')),
                'phone' => trim((string) ($collaborator->phone ?? '')),
                'job_title' => trim((string) ($collaborator->job_title ?? '')),
                'photo_url' => trim((string) ($collaborator->photo_url ?? '')),
                'notes' => trim((string) ($collaborator->notes ?? '')),
                'is_active' => (bool) $collaborator->is_active,
                'service_category_ids' => $collaborator->serviceCategories
                    ->pluck('id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->values()
                    ->all(),
                'service_categories' => $collaborator->serviceCategories
                    ->map(static fn (ServiceCategory $category): array => [
                        'id' => (int) $category->id,
                        'name' => (string) $category->name,
                    ])
                    ->values()
                    ->all(),
                'recent_appointments' => $collaborator->serviceAppointments
                    ->map(static fn ($appointment): array => [
                        'id' => (int) $appointment->id,
                        'title' => (string) $appointment->title,
                        'starts_at' => optional($appointment->starts_at)?->format('d/m H:i'),
                        'status' => (string) $appointment->status,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $serviceCategories = $isServices
            ? ServiceCategory::query()
                ->where('contractor_id', $contractor->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(static fn (ServiceCategory $category): array => [
                    'id' => (int) $category->id,
                    'name' => (string) $category->name,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Admin/Collaborators/Index', [
            'niche' => $contractor->niche(),
            'collaborators' => $collaborators,
            'serviceCategories' => $serviceCategories,
            'stats' => [
                'total' => count($collaborators),
                'active' => collect($collaborators)->where('is_active', true)->count(),
                'with_photo' => collect($collaborators)->filter(static fn (array $row): bool => trim((string) ($row['photo_url'] ?? '')) !== '')->count(),
                'with_categories' => collect($collaborators)->filter(static fn (array $row): bool => count($row['service_category_ids'] ?? []) > 0)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $this->validatePayload($request, $contractor);
        $collaborator = Collaborator::query()->create($this->persistPayload($contractor, $data, $request->file('photo')));
        $this->syncServiceCategories($contractor, $collaborator, $data['service_category_ids'] ?? []);

        return back()->with('status', 'Colaborador criado com sucesso.');
    }

    public function update(Request $request, Collaborator $collaborator): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo nao encontrado.');

        $collaborator = $this->resolveOwnedCollaborator($contractor, $collaborator);
        $data = $this->validatePayload($request, $contractor, $collaborator);
        $collaborator->fill($this->persistPayload($contractor, $data, $request->file('photo'), $collaborator))->save();
        $this->syncServiceCategories($contractor, $collaborator, $data['service_category_ids'] ?? []);

        return back()->with('status', 'Colaborador atualizado com sucesso.');
    }

    public function destroy(Request $request, Collaborator $collaborator): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $collaborator = $this->resolveOwnedCollaborator($contractor, $collaborator);
        $this->deleteStoredFileFromPublicUrl($collaborator->photo_url);
        $collaborator->serviceCategories()->detach();
        $collaborator->delete();

        return back()->with('status', 'Colaborador removido com sucesso.');
    }

    private function resolveOwnedCollaborator(Contractor $contractor, Collaborator $collaborator): Collaborator
    {
        abort_unless((int) $collaborator->contractor_id === (int) $contractor->id, 404);

        return $collaborator->loadMissing('serviceCategories:id,name');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Contractor $contractor, ?Collaborator $collaborator = null): array
    {
        $phone = BrazilData::normalizePhone($request->input('phone'));

        $request->merge([
            'phone' => $phone !== '' ? $phone : null,
            'is_active' => $request->boolean('is_active', true),
            'remove_photo' => $request->boolean('remove_photo'),
            'service_category_ids' => collect($request->input('service_category_ids', []))
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);

        $rules = [
            'name' => ['required', 'string', 'max:180'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('collaborators', 'email')
                    ->ignore($collaborator?->id)
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\(\d{2}\)\s\d{4,5}-\d{4}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'service_category_ids' => ['nullable', 'array'],
            'service_category_ids.*' => [
                'integer',
                Rule::exists('service_categories', 'id')->where(
                    static fn ($query) => $query->where('contractor_id', $contractor->id)->where('is_active', true)
                ),
            ],
        ];

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function persistPayload(
        Contractor $contractor,
        array $data,
        ?UploadedFile $photoFile = null,
        ?Collaborator $collaborator = null
    ): array {
        $payload = [
            'contractor_id' => $contractor->id,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
            'job_title' => trim((string) ($data['job_title'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if (($data['remove_photo'] ?? false) === true && $collaborator) {
            $this->deleteStoredFileFromPublicUrl($collaborator->photo_url);
            $payload['photo_url'] = null;
        }

        if ($photoFile instanceof UploadedFile) {
            if ($collaborator) {
                $this->deleteStoredFileFromPublicUrl($collaborator->photo_url);
            }

            $baseId = $collaborator?->id ?? 'new';
            $photoPath = $photoFile->store("contractors/{$contractor->id}/collaborators/{$baseId}", 'public');
            $payload['photo_url'] = Storage::disk('public')->url($photoPath);
        }

        return $payload;
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function syncServiceCategories(Contractor $contractor, Collaborator $collaborator, array $categoryIds): void
    {
        if ($contractor->niche() !== Contractor::NICHE_SERVICES) {
            $collaborator->serviceCategories()->detach();
            return;
        }

        $safeIds = collect($categoryIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $collaborator->serviceCategories()->sync($safeIds);
    }

    private function deleteStoredFileFromPublicUrl(?string $publicUrl): void
    {
        if (! $publicUrl) {
            return;
        }

        $path = parse_url($publicUrl, PHP_URL_PATH);
        $normalizedPath = is_string($path) && $path !== '' ? $path : $publicUrl;
        $prefix = '/storage/';

        if (! str_starts_with($normalizedPath, $prefix)) {
            return;
        }

        $relativePath = ltrim(substr($normalizedPath, strlen($prefix)), '/');
        if ($relativePath === '') {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
