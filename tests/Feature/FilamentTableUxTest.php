<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilamentTableUxTest extends TestCase
{
    public function test_secondary_columns_are_optional_by_default(): void
    {
        $checks = [
            app_path(
                'Filament/Resources/Tasks/TaskResource.php',
            ) => [
                "TextColumn::make('priority_score')",
                'isToggledHiddenByDefault: true',
            ],
            app_path(
                'Filament/Resources/Projects/ProjectResource.php',
            ) => [
                "TextColumn::make('type')",
                "TextColumn::make('horizon')",
                'isToggledHiddenByDefault: true',
            ],
            app_path(
                'Filament/Resources/ServiceOrders/ServiceOrderResource.php',
            ) => [
                "TextColumn::make('amount')",
                "TextColumn::make('days_in_stage')",
                'isToggledHiddenByDefault: true',
            ],
        ];

        foreach ($checks as $file => $needles) {
            $content = file_get_contents($file);

            $this->assertIsString($content);

            foreach ($needles as $needle) {
                $this->assertStringContainsString(
                    $needle,
                    $content,
                );
            }
        }
    }
}
