<?php

namespace App\Http\Controllers\PublicSite;

use App\Application\Storefront\Queries\BuildLandingPageQuery;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(
        private readonly BuildLandingPageQuery $buildLandingPageQuery,
    ) {}

    public function __invoke(): Response
    {
        $payload = $this->buildLandingPageQuery->execute();

        return Inertia::render('Public/Landing', [
            'canLogin' => Route::has('login'),
            'planSections' => $payload['planSections'] ?? [],
        ]);
    }
}
