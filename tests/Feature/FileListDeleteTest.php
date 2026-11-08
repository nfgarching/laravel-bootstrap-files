<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('die Liste zeigt Filter, Sortierung und je Datei einen Lösch-Button', function () {
    $file = File::factory()->create(['disk' => 'data']);

    $this->get(route('files.index', ['select' => 1]))
        ->assertOk()
        ->assertSee('filesFilterModal')
        ->assertSee('Sortieren')
        ->assertSee('form="delete-file-'.$file->id.'"', false)
        ->assertSee('action="'.route('files.destroy', $file).'"', false)
        ->assertSee('action="'.route('files.bulk-action').'"', false)
        ->assertSee('name="checks[]"', false);
});

test('Filter, Suche und Sortierung schränken die Liste ein', function () {
    $hit = File::factory()->create(['disk' => 'data', 'state' => 'active', 'name' => 'Treffer.pdf']);
    $miss = File::factory()->create(['disk' => 'local', 'state' => 'active', 'name' => 'Anderes.pdf']);

    $this->get(route('files.index', ['disk' => 'data', 'state' => 'active', 'sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertSee('Treffer.pdf')
        ->assertDontSee('Anderes.pdf')
        ->assertSee('Disk: data');

    $this->get(route('files.index', ['search' => 'Anderes']))
        ->assertOk()
        ->assertSee('Anderes.pdf')
        ->assertDontSee('Treffer.pdf');
});

test('der Lösch-Button löscht den Datensatz, andere Dateien bleiben', function () {
    $file = File::factory()->create(['name' => 'Zu löschen.pdf']);
    $other = File::factory()->create();

    $this->from(route('files.index', ['disk' => 'data']))
        ->delete(route('files.destroy', $file))
        ->assertRedirect(route('files.index', ['disk' => 'data']))
        ->assertSessionHas('message', 'Datei „Zu löschen.pdf“ gelöscht');

    expect(File::find($file->id))->toBeNull()
        ->and(File::find($other->id))->not->toBeNull();
});

test('Delete bei Select löscht die markierten Dateien', function () {
    [$a, $b, $keep] = File::factory()->count(3)->create();

    $this->from(route('files.index'))
        ->post(route('files.bulk-action'), ['switchTo' => 'delete', 'checks' => [$a->id, $b->id]])
        ->assertRedirect(route('files.index'))
        ->assertSessionHas('message', '2 Dateien gelöscht');

    expect(File::pluck('id')->all())->toBe([$keep->id]);
});

test('Delete ohne markierte Dateien löscht nichts und meldet das', function () {
    File::factory()->create();

    $this->from(route('files.index'))
        ->post(route('files.bulk-action'), ['switchTo' => 'delete'])
        ->assertRedirect(route('files.index'))
        ->assertSessionHas('message', 'Bitte mindestens eine Datei auswählen.');

    expect(File::count())->toBe(1);
});
