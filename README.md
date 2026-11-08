# nfgarching/files

Laravel-Package für Dateiverwaltung (Upload, Speicherung, Konvertierung, Anzeige).

---

## Installation

Das Package wird als **Composer-Path-Repository** eingebunden. In der
Root-`composer.json`:

```jsonc
"repositories": [
    {
        "type": "path",
        "url": "packages/nfgarching/files",
        "options": { "symlink": true }
    }
]
```

```bash
composer require nfgarching/files:@dev
php artisan migrate          # legt die Tabelle files an
```

Composer legt einen Symlink unter `vendor/nfgarching/files` an; der
`FileServiceProvider` wird automatisch über Laravels Package-Discovery
(`extra.laravel.providers`) registriert – kein manueller Eintrag in
`bootstrap/providers.php` nötig.

> Hinweis: Die App nutzt `minimum-stability: stable`, das Path-Package läuft auf
> `dev-main`. Deshalb wird mit dem Stability-Flag `:@dev` requiren.

### Alt. Installation mit Monorepo

```bash
mkdir -p packages/nfgarching
cd packages/nfgarching
git clone git@github.com:nfgarching/laravel-bootstrap-files.git files
```

`composer.json` (Autoload-Eintrag):

```json
"autoload": {
    "psr-4": {
        "Nfgarching\\Files\\": "packages/nfgarching/files/src/"
    }
}
```

`bootstrap/providers.php`:

```php
Nfgarching\Files\FileServiceProvider::class,
```

```bash
composer dump-autoload
php artisan migrate
```

---

## Blade-Komponenten

### `<x-file.link :file="$file" />`

Öffnet eine Datei per Klick in einem Bootstrap-Modal. Unterstützt Bild, PDF und Video inline. Mit `Shift` → neues Fenster, mit `Ctrl/Cmd` → neuer Tab.

| Prop | Typ | Default | Beschreibung |
| --- | --- | --- | --- |
| `file` | `File` | – | `File`-Model-Instanz |
| `preview` | `bool` | `true` | Vorschau-Thumbnail im Tooltip |
| `thumb` | `bool` | `false` | Thumbnail statt Icon im Button |
| `popup` | `string` | `'left=100,top=100,width=800,height=800'` | Fenster-Optionen für Shift-Klick |

```blade
<x-file.link :file="$file" />
<x-file.link :file="$file" :thumb="true" />
```

---

### `<x-file.filter.select field="disk" />`

Self-contained Filterkomponente für Datei-Listen. Liest den aktuellen Request-Zustand selbst, baut die Gruppenabfrage auf dem `File`-Model auf und berücksichtigt aktive Cross-Feld-Filter.

Unterstützte Felder: `disk`, `status`, `type`

| Prop | Typ | Beschreibung |
| --- | --- | --- |
| `field` | `string` | Spaltenname aus der `files`-Tabelle |

```blade
<x-file.filter.select field="disk" />
<x-file.filter.select field="status" />
<x-file.filter.select field="type" />
```

Enum-Felder (z. B. `type` → `FileType`) werden automatisch erkannt: der Optionstext verwendet `->label()` statt des Rohwerts.

---

### `<x-card.files :item="$model" />`

Zeigt alle Dateien eines beliebigen Eloquent-Models als Tabelle in einer Card an. Das Model muss eine `files()`-Relation besitzen.

```blade
<x-card.files :item="$dokument" />
```

---

## Filterable File-Liste in eigenen Controllern

### 1. Controller

```php
use Nfgarching\Files\Models\File;

public function index(Request $request)
{
    $where = [];

    if ($request->disk)   $where[] = ['disk',   $request->disk];
    if ($request->status) $where[] = ['status', $request->status];
    if ($request->type)   $where[] = ['type',   $request->type];

    $files = File::search($request->search)
        ->where($where)
        ->sortable(['id' => 'desc'])
        ->paginate($request->integer('per_page', 8))
        ->withQueryString();

    return view('mein-package::index', [
        'files'   => $files,
        'request' => $request,
    ]);
}
```

### 2. View

```blade
<x-table.filter>
    <x-table.filter.cnt_per_page route="mein-route.index" :request="$request" />
    <x-table.filter.search field="search" :oldval="$request->search" />
    <x-file.filter.select field="disk" />
    <x-file.filter.select field="status" />
    <x-file.filter.select field="type" />
</x-table.filter>

@forelse ($files as $file)
    <x-file.link :file="$file" />
    {{ $file->name }}
    {{ $file->type->label() }}
    {{ $file->formatBytes() }}
@empty
    Keine Dateien gefunden.
@endforelse

{{ $files->links() }}
```

`<x-table.filter>` erzeugt ein `<form method="GET">` und einen Submit-Button. Alle Filter-Komponenten sind Kindelemente dieses Formulars.

---

## FileType-Enum

`Nfgarching\Files\Enums\FileType` — Backing-Values in der Datenbank: `image`, `pdf`, `video`, `other`.

| Case | Value | `label()` | `icon()` | `color()` |
| --- | --- | --- | --- | --- |
| `Image` | `image` | Bild | `bi bi-file-image` | `success` |
| `Pdf` | `pdf` | PDF | `bi bi-file-pdf` | `danger` |
| `Video` | `video` | Video | `bi bi-film` | `primary` |
| `Other` | `other` | Sonstiges | `bi bi-file` | `secondary` |

Hilfsmethoden:

```php
FileType::fromExtension('jpg');        // → FileType::Image
FileType::fromMimeType('image/png');   // → FileType::Image
FileType::options();                   // → ['image' => 'Bild', 'pdf' => 'PDF', ...]
```

---

## File-Model: nützliche Methoden

| Methode | Beschreibung |
| --- | --- |
| `$file->type` | `FileType`-Enum-Instanz (automatischer Cast) |
| `$file->formatBytes()` | Lesbare Dateigröße (`1,2 MB`) |
| `$file->getPath()` | Verzeichnispfad auf dem Disk |
| `$file->getPathToFile()` | Vollständiger Pfad zur Datei |
| `File::storeUpload($upload)` | Upload-Objekt speichern und verarbeiten |
| `File::importFromDisk($disk, $file)` | Bestehende Datei von Disk importieren |

---

## Abhängigkeiten

- `kyslik/column-sortable` — Sortierbare Tabellenspalten (`->sortable()`)
- Bootstrap 5 — Modals, Dropdowns, Tooltips (JS muss initialisiert sein)
- Bootstrap Icons (`bi bi-*`)
