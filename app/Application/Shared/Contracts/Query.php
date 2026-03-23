<?php

namespace App\Application\Shared\Contracts;

interface Query
{
    /**
     * Executa uma leitura sem efeito colateral.
     */
    public function execute(mixed ...$args): mixed;
}
