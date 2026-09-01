<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_root_redirects_to_mi_dia(): void
    {
        $this->get('/')
            ->assertRedirect('/mi-dia');
    }
}
