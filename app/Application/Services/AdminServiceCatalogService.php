<?php

namespace App\Application\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use App\Services\ProductImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceCatalogService
{
    use ResolvesCurrentContractor;

    public function __construct(
        private readonly ProductImageProcessor $imageProcessor,
    ) {}

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $categoryId = (int) $request->integer('category_id', 0);

        $query = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->with('category:id,name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($categoryId > 0) {
            $query->where('service_category_id', $categoryId);
        }

        $services = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ServiceCatalog $service): array => $this->toServicePayload($service));

        $totalServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $averagePrice = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('base_price');

        $averageDuration = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('duration_minutes');

        $categories = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ServiceCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Services/Catalog', [
            'services' => $services,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId > 0 ? $categoryId : null,
            ],
            'stats' => [
                'total' => $totalServices,
                'active' => $activeServices,
                'avg_price' => $averagePrice !== null ? round((float) $averagePrice, 2) : null,
                'avg_duration' => $averageDuration !== null ? (int) round((float) $averageDuration) : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $payload = $this->validatePayload($request, $contractor);
        $payload['contractor_id'] = $contractor->id;
        $payload = $this->resolveImagePayload($request, $contractor, null, $payload);

        ServiceCatalog::query()->create($payload);

        return back()->with('status', 'Serviço cadastrado com sucesso.');
    }

    public function update(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceCatalog = $this->resolveOwnedService($contractor, $serviceCatalog);
        $payload = $this->validatePayload($request, $contractor, (int) $serviceCatalog->id);
        $payload = $this->resolveImagePayload($request, $contractor, $serviceCatalog, $payload);

        $serviceCatalog->fill($payload)->save();

        return back()->with('status', 'Serviço atualizado com sucesso.');
    }

    public function destroy(Request $request, ServiceCatalog $serviceCatalog): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceCatalog = $this->resolveOwnedService($contractor, $serviceCatalog);
        $this->deleteServiceImage($contractor, $serviceCatalog->image_url);
        $serviceCatalog->delete();

        return back()->with('status', 'Serviço removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Contractor $contractor, ?int $ignoreServiceId = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('service_catalogs', 'code')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id))
                    ->ignore($ignoreServiceId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'service_category_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        $validated['code'] = $code === '' ? null : $code;

        if (array_key_exists('description', $validated)) {
            $description = trim((string) ($validated['description'] ?? ''));
            $validated['description'] = $description === '' ? null : $description;
        }

        if (array_key_exists('image_url', $validated)) {
            $imageUrl = trim((string) ($validated['image_url'] ?? ''));
            $validated['image_url'] = $imageUrl === '' ? null : $imageUrl;
        }

        return $validated;
    }

    private function resolveOwnedService(Contractor $contractor, ServiceCatalog $serviceCatalog): ServiceCatalog
    {
        abort_unless((int) $serviceCatalog->contractor_id === (int) $contractor->id, 404);

        return $serviceCatalog;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveImagePayload(
        Request $request,
        Contractor $contractor,
        ?ServiceCatalog $serviceCatalog,
        array $data
    ): array {
        $currentImageUrl = $serviceCatalog?->image_url;
        $currentStoragePath = $this->normalizeServiceStoragePath($contractor, $currentImageUrl);
        $removeImage = (bool) ($data['remove_image'] ?? false);
        $submittedImageUrl = trim((string) ($data['image_url'] ?? ''));
        $uploadedImage = $request->file('image_file');

        if ($uploadedImage instanceof UploadedFile) {
            if ($currentStoragePath) {
                $this->deleteServiceImage($contractor, $currentStoragePath);
            }

            try {
                $processed = $this->imageProcessor->processAndStore($uploadedImage, $contractor, 'services/catalog');
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'image_file' => 'Nao foi possivel processar a imagem enviada. Verifique se o servidor possui GD com suporte a WebP e tente novamente.',
                ]);
            }
            $data['image_url'] = $processed['url'];
        } elseif ($removeImage) {
            if ($currentStoragePath) {
                $this->deleteServiceImage($contractor, $currentStoragePath);
            }
            $data['image_url'] = null;
        } elseif ($submittedImageUrl !== '') {
            $submittedStoragePath = $this->normalizeServiceStoragePath($contractor, $submittedImageUrl);

            if (! $submittedStoragePath && $currentStoragePath) {
                $this->deleteServiceImage($contractor, $currentStoragePath);
            } elseif (
                $submittedStoragePath &&
                $currentStoragePath &&
                $submittedStoragePath !== $currentStoragePath
            ) {
                $this->deleteServiceImage($contractor, $currentStoragePath);
            }
        } elseif ($serviceCatalog) {
            $data['image_url'] = $serviceCatalog->image_url;
        }

        unset($data['image_file'], $data['remove_image']);

        return $data;
    }

    private function normalizeServiceStoragePath(Contractor $contractor, mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $path = parse_url($raw, PHP_URL_PATH);
        $candidate = is_string($path) && $path !== '' ? $path : $raw;

        if (str_starts_with($candidate, '/storage/')) {
            $candidate = substr($candidate, strlen('/storage/'));
        } elseif (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }

        $candidate = ltrim($candidate, '/');
        if ($candidate === '') {
            return null;
        }

        $prefix = "contractors/{$contractor->id}/services/catalog/";
        if (! str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }

    private function deleteServiceImage(Contractor $contractor, mixed $value): void
    {
        $path = $this->normalizeServiceStoragePath($contractor, $value);
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toServicePayload(ServiceCatalog $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'code' => $service->code,
            'description' => $service->description,
            'image_url' => $this->normalizePublicAssetUrl($service->image_url),
            'service_category_id' => $service->service_category_id,
            'category_name' => $service->category?->name,
            'duration_minutes' => (int) $service->duration_minutes,
            'base_price' => (float) $service->base_price,
            'is_active' => (bool) $service->is_active,
            'status_label' => $service->is_active ? 'Ativo' : 'Inativo',
        ];
    }

    private function normalizePublicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }
}
