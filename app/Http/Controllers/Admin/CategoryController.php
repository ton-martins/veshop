<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\Services\AdminCategoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly AdminCategoryService $service,
    ) {}

    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        return $this->service->update($request, $category);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        return $this->service->destroy($request, $category);
    }
}
