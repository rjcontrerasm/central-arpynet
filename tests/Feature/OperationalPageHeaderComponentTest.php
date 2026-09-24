<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class OperationalPageHeaderComponentTest extends TestCase
{
    public function test_operational_page_header_renders_shared_shell_and_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
<x-operational-page-header
    active="projects"
    title="Proyectos"
    subtitle="Avance y siguientes acciones"
>
    <x-slot:actions>
        <a href="/proyectos/nuevo">Nuevo proyecto</a>
    </x-slot:actions>
</x-operational-page-header>
BLADE);

        $this->assertStringContainsString(
            'Central ARPYNET',
            $html,
        );

        $this->assertStringContainsString(
            route('daily-ops.show'),
            $html,
        );

        $this->assertStringContainsString(
            '<h1>Proyectos</h1>',
            $html,
        );

        $this->assertStringContainsString(
            'Avance y siguientes acciones',
            $html,
        );

        $this->assertStringContainsString(
            'operational-header-actions',
            $html,
        );

        $this->assertStringContainsString(
            'Nuevo proyecto',
            $html,
        );
    }
}
