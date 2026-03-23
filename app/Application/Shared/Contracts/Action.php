<?php

namespace App\Application\Shared\Contracts;

interface Action
{
    /**
     * Executa um caso de uso com efeito colateral.
     */
    public function execute(mixed ...$args): mixed;
}
