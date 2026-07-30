<?php

// Guards the admin panel's brand identity after the Seabound Souls →
// Seabound Sessions rename. The panel's brand name is set explicitly on the
// provider rather than inherited from APP_NAME, so a stale or unset APP_NAME
// in an environment can never surface the old name (or "Laravel") to an admin.

namespace Tests\Feature\Filament;

use Filament\Facades\Filament;
use Tests\TestCase;

class AdminPanelBrandingTest extends TestCase
{
    public function test_panel_brand_name_is_the_current_brand(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame('Seabound Sessions', Filament::getBrandName());
    }

    public function test_brand_name_is_independent_of_app_name(): void
    {
        config(['app.name' => 'Something Else']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame('Seabound Sessions', Filament::getBrandName());
    }

    public function test_login_page_shows_the_new_brand_and_not_the_old_one(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Seabound Sessions');
        $response->assertDontSee('Seabound Souls');
    }
}
