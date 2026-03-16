<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopFavoriteController extends Controller
{
    public function store(Request $request, string $slug, int $product): JsonResponse
    {
        [$contractor, $customer] = $this->resolveContext($request, $slug);

        $productModel = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $product)
            ->where('is_active', true)
            ->firstOrFail();

        ShopCustomerFavorite::query()->firstOrCreate([
            'contractor_id' => $contractor->id,
            'shop_customer_id' => $customer->id,
            'product_id' => $productModel->id,
        ]);

        return response()->json([
            'ok' => true,
            'favorited' => true,
            'product_id' => (int) $productModel->id,
        ]);
    }

    public function destroy(Request $request, string $slug, int $product): JsonResponse
    {
        [$contractor, $customer] = $this->resolveContext($request, $slug);

        ShopCustomerFavorite::query()
            ->where('contractor_id', $contractor->id)
            ->where('shop_customer_id', $customer->id)
            ->where('product_id', $product)
            ->delete();

        return response()->json([
            'ok' => true,
            'favorited' => false,
            'product_id' => $product,
        ]);
    }

    /**
     * @return array{0: Contractor, 1: ShopCustomer}
     */
    private function resolveContext(Request $request, string $slug): array
    {
        $contractor = Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');

        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        return [$contractor, $customer];
    }
}
