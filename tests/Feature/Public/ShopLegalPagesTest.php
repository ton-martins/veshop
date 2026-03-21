<?php

namespace Tests\Feature\Public;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopLegalPagesTest extends TestCase
{
    public function test_terms_page_is_accessible(): void
    {
        $response = $this->get(route('legal.terms'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/Legal/Terms'));
    }

    public function test_privacy_page_is_accessible(): void
    {
        $response = $this->get(route('legal.privacy'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Public/Legal/Privacy'));
    }
}

