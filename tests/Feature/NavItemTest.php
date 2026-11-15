<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Navbar file-import / file-upload: Klick => iframe-Modal (xl), SHIFT => Popup,
 * CTRL => Tab. Der Abschluss läuft über finishPopup() im Popup-Layout.
 */
test('file-import und file-upload öffnen das iframe-Modal in xl', function () {
    $this->actingAs(User::factory()->create());

    $html = view('files-layout::parts.navitems')->render();

    foreach (['file-import', 'file-upload'] as $route) {
        $url = route($route);

        expect(html_entity_decode($html, ENT_QUOTES))
            ->toContain("openFrameModal('".$url."', \"\", 'xl')")
            ->toContain("event.shiftKey) { openPopup('".$url."'")
            ->toContain("event.ctrlKey) { openTab('".$url."')");
    }
});

test('die Popup-Seite file-upload bringt finishPopup() für Popup und Modal mit', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('file-upload'))
        ->assertOk()
        ->assertSee('window.finishPopup = function', false)
        ->assertSee('window.top.location.href = url', false);
});
