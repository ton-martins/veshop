<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\Plan;
use Tests\TestCase;

class ContractorActivePlanNameTest extends TestCase
{
    public function test_active_plan_name_uses_settings_when_loaded_plan_name_is_null(): void
    {
        $contractor = new Contractor([
            'settings' => [
                'active_plan_name' => 'Plano Configurado',
            ],
        ]);

        $contractor->setRelation('plan', new Plan([
            'name' => null,
        ]));

        $this->assertSame('Plano Configurado', $contractor->activePlanName());
    }

    public function test_active_plan_name_returns_sem_plano_when_both_sources_are_empty(): void
    {
        $contractor = new Contractor([
            'settings' => [],
        ]);

        $contractor->setRelation('plan', new Plan([
            'name' => null,
        ]));

        $this->assertSame('Sem plano', $contractor->activePlanName());
    }
}

