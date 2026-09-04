<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    #[DataProvider('publicPages')]
    public function test_public_pages_render_successfully(string $path): void
    {
        $this->get($path)->assertOk();
    }

    public static function publicPages(): array
    {
        return [
            'landing' => ['/'],
            'wizard' => ['/wizard'],
            'about' => ['/about'],
            'validate' => ['/validate'],
            'export' => ['/export'],
        ];
    }
}
