<?php

namespace Nfgarching\Files\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Kyslik\ColumnSortable\Sortable;
use League\Flysystem\UnableToCreateDirectory;
use Nfgarching\Core\Traits\Searchable;
use Nfgarching\Files\Database\Factories\FileFactory;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Jobs\ProcessFile;
use Nfgarching\Files\Traits\HasArchive;

// use Nfgarching\Core\Traits\Searchable;

class File extends Model
{
    use HasArchive, HasFactory, Searchable, Sortable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'files';

    /**
     * Files liegen immer auf der Default-Connection.
     *
     * Ohne diese Fixierung erben Relations von Legacy-Models (Connection 'akv',
     * z.B. Dokument::files()) deren Connection und laufen in
     * "Table 'AkV.files' doesn't exist".
     */
    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('database.default');
    }

    /**
     * Allowing Mass Assignment
     */
    protected $guarded = [
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_conversions' => 'array',
            'manipulations' => 'array',
            'custom_properties' => 'array',
            'metadata' => 'array',
            'type' => FileType::class,
        ];
    }

    public $searchable = [
        'state',
        'type',
        // 'typ',
        'disk',
        'name',
        'memo',
    ];

    public $sortable = [
        'id',
        'state',
        'type',
        // 'typ',
        'disk',
        'name',
        'size',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function formatBytes($precision = 1)
    {
        return Number::fileSize($this->size);

        dd('####');

        if (! $this->size) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($this->size > 1024) {
            $this->size /= 1024;
            $i++;
        }

        return number_format($this->size, $precision, ',', '.').''.$units[$i];
    }

    public function getPath(string $type = '')
    {
        $pathclass = config('files.path_generator');

        switch ($type) {

            case 'conversions':
                $path = (new $pathclass)->getPathForConversions($this);
                break;

            case 'ocr':
                $path = (new $pathclass)->getPathForOcr($this);
                break;

            case 'archive':
                $path = (new $pathclass)->getPathForArchive($this);
                break;

            case 'responsive_images':
                $path = (new $pathclass)->getPathForResponsiveImages($this);
                break;

            default:
                $path = (new $pathclass)->getPath($this);

        }

        // dd($path);
        Storage::disk($this->disk)->makeDirectory($path);

        return $path;
    }

    public function getPathToFile()
    {
        $pathclass = config('files.path_generator');
        $path2file = (new $pathclass)->getPath($this).'/'.$this->file;

        // dd($pathclass, $path2file);

        return $path2file;
    }

    /**
     * Setzt einzelne Schlüssel einer JSON-Spalte (z.B. generated_conversions,
     * custom_properties), ohne die übrigen Schlüssel zu verlieren.
     *
     * PDF- und OCR-Konvertierung laufen parallel in der Queue; liest jeder Job
     * das ganze Array und schreibt es zurück, überschreibt er die Einträge des
     * anderen. Deshalb wird die Zeile gesperrt frisch gelesen und gemischt.
     * Ein Wert null entfernt den Schlüssel.
     *
     * @param  array<string, mixed>  $values
     */
    public function mergeJson(string $column, array $values): void
    {
        $merged = $this->getConnection()->transaction(function () use ($column, $values) {
            $fresh = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $data = $fresh->{$column} ?? [];
            foreach ($values as $key => $value) {
                if ($value === null) {
                    unset($data[$key]);
                } else {
                    $data[$key] = $value;
                }
            }

            $fresh->{$column} = $data ?: null;
            $fresh->save();

            return $fresh->{$column};
        });

        $this->{$column} = $merged;
        $this->syncOriginalAttribute($column);
    }

    /**
     * OCR-Ergebnis als Links (custom_properties.ocr):
     * type (ocrtext|text|digitext|noocr|errocr), disk, text (Pfad der
     * Textdatei), size, error (nur bei noocr/errocr), at.
     *
     * @return array<string, mixed>|null
     */
    public function ocr(): ?array
    {
        return $this->custom_properties['ocr'] ?? null;
    }

    public function ocrFailed(): bool
    {
        return in_array($this->ocr()['type'] ?? null, ['noocr', 'errocr'], true);
    }

    /**
     * Archivierungsergebnis (custom_properties.archive):
     * status (archived|skipped|error), path, sha256, source_sha256,
     * pdfa, validation, signature (je mit status), at.
     *
     * @return array<string, mixed>|null
     */
    public function archive(): ?array
    {
        return $this->custom_properties['archive'] ?? null;
    }

    /**
     * Pfad der Archivfassung (PDF/A, gesiegelt) in generated_conversions.archive.
     */
    public function archivePdfPath(): ?string
    {
        return $this->generated_conversions['archive'] ?? null;
    }

    public function isArchiveSigned(): bool
    {
        return ($this->archive()['signature']['status'] ?? null) === 'signed';
    }

    /**
     * Ergebnis der PDF-Prüfung (custom_properties.inspection): at, trust,
     * variants.original / variants.archive mit properties, signatures,
     * pdfa und integrity. Siehe Services\Inspection\PdfInspection.
     *
     * @return array<string, mixed>|null
     */
    public function inspection(): ?array
    {
        return $this->custom_properties['inspection'] ?? null;
    }

    /**
     * Pfad des durchsuchbaren OCR-PDFs (generated_conversions.ocr).
     */
    public function ocrPdfPath(): ?string
    {
        return $this->generated_conversions['ocr'] ?? null;
    }

    public function ocrTextPath(): ?string
    {
        return $this->ocr()['text'] ?? null;
    }

    /**
     * Inhalt der OCR-Textdatei, null wenn kein OCR vorliegt oder die Datei fehlt.
     */
    public function ocrText(): ?string
    {
        $path = $this->ocrTextPath();
        $disk = $this->ocr()['disk'] ?? $this->disk;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->get($path);
    }

    /**
     * OCR-Text für Dokument-OCR-Feld und Detailansicht, inkl. Hinweis bei
     * fehlgeschlagenem OCR oder fehlender Textdatei.
     */
    public function propsText(): string
    {
        $ocr = $this->ocr();

        if (! $ocr) {
            return '';
        }

        if ($this->ocrFailed()) {
            return "--- OCR nicht durchgeführt ({$ocr['type']}) ---\n".
                (! empty($ocr['error']) ? $ocr['error']."\n" : '');
        }

        $text = $this->ocrText();

        if ($text === null) {
            return '--- OCR-Textdatei fehlt: '.($ocr['text'] ?? '')." ---\n";
        }

        return "--- OCR-Text ---\n\n".$text;
    }

    public static function getValidationLogic()
    {
        return [
            'required',
            \Illuminate\Validation\Rules\File::types(['jpg', 'pdf', 'png'])
                ->min(1)
                ->max('10mb'),
        ];
    }

    public static function linkDiskFile($disk, $file, $arr_input = [])
    {
        dump(get_class().' -> '.__FUNCTION__);
        dump($disk, $file, $arr_input);

        $arr = [
            'model_type' => 'App\Models\User',
            'model_id' => 1,

            'select' => true,
            'sorting' => '10',
            'suchbegriff' => '',
            'status' => 'active',
            // 'type' => $upload->extension(),
            'typ' => 'link',

            'disk' => $disk,
            'file' => $file,

            'hash' => md5_file(Storage::disk($disk)->path($file)),
            // 'mime' => Storage::disk($disk)->mimeType($file),
            // 'mime' => mime_content_type(Storage::disk($disk)->path($file)),
            'name' => basename($file),
            'node' => fileinode(Storage::disk($disk)->path($file)),
            'size' => Storage::disk($disk)->size($file),
            'type' => FileType::fromExtension(strtolower(pathinfo(Storage::disk($disk)->path($file), PATHINFO_EXTENSION)))->value,

            // 'memo' => strlen($path),
            // 'memo' => json_encode($upload),
        ];
        dd($arr);

        return File::create(array_merge($arr, $arr_input));
    }

    public static function moveDiskFile($disk, $oldFile, $arr_input = [])
    {
        // dump(get_class() . ' -> ' . __FUNCTION__);
        // dump($disk, $oldFile, $arr_input,);

        if ($disk != config('files.mvfile.save_disk')) {
            echo '<br><hr><br>';
            dump("Move on Disk \"$disk\" not possible");
            echo '<br><hr><br>';
            echo '<a href='.route('disks.show', $disk).'>Zurück zur Disk: '.$disk.'</a>';
            echo '<br>';
            echo '<a href='.route('disks.index').'>Zurück zu Storage</a>';
            echo '<br>';
            echo '<a href='.route('startpage').'>Zurück zu HOME</a>';
            dd('---');
        }

        // $oldPath = Storage::disk($disk)->path($oldFile);
        $extension = strtolower(pathinfo(Storage::disk($disk)->path($oldFile), PATHINFO_EXTENSION));

        $arr = [
            'model_type' => 'App\Models\User',
            'model_id' => 1,

            'select' => true,
            'sorting' => '10',
            'status' => 'uncomplete',
            'type' => FileType::fromExtension($extension)->value,
            'typ' => 'mvfile',

            'disk' => $disk,
            'file' => $oldFile,

            'hash' => md5_file(Storage::disk($disk)->path($oldFile)),
            // 'mime' => Storage::disk($disk)->mimeType($oldFile),
            'name' => basename($oldFile),
            'node' => 0,
            'type' => FileType::fromExtension($extension)->value,
            'size' => Storage::disk($disk)->size($oldFile),

            // 'memo' => strlen($path),
            // 'memo' => json_encode($upload),
        ];
        dd($arr);
        $file = File::create(array_merge($arr, $arr_input)); // echo $file->hash; echo $file->size;

        $newPath = config('files.save_path').'/'.$file->id; // dump($newPath);
        $newFile = $newPath.'/'.Str::random(8).".$extension"; // dump($newFile);

        try {
            if (Storage::disk($disk)->move($oldFile, $newFile)) {
                $newPath = Storage::disk($disk)->path($newFile); // dump($newPath);
                // dump('Move done to ' . $newPath);
            } else {
                dd('Error by Move');
            }
        } catch (UnableToCreateDirectory $th) {
            throw $th;
        }

        $arr = [
            'status' => 'active',

            // 'disk' => $disk,
            'file' => $newFile,

            // 'mime' => mime_content_type(Storage::disk($disk)->path($newFile)),
            'node' => fileinode(Storage::disk($disk)->path($newFile)),
            // 'size' => Storage::disk($disk)->size($newFile),

            // 'memo' => strlen($path),
            // 'memo' => json_encode($upload),
        ];
        // dump($arr);
        $file->update(array_merge($arr, $arr_input)); // echo $file->hash; echo $file->size;

        // dump($arr_input, $disk, $newFile, $file);
        return $file;
    }

    /**
     * @param  array<string, mixed>  $arr_input  Spaltenwerte; zusätzlich 'own' (bool)
     *                                           für „Eigenes Dokument“, sonst Vorbelegung
     *                                           nach Import-Disk bzw. Erzeugerprogramm
     */
    public static function importFromDisk(string $sourceDisk, string $sourceFile, array $arr_input = []): self
    {
        $explicitOwn = array_key_exists('own', $arr_input) ? (bool) $arr_input['own'] : null;
        unset($arr_input['own'], $arr_input['file_type']);

        $extension = strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION));

        try {
            $hash = md5_file(Storage::disk($sourceDisk)->path($sourceFile));
        } catch (\Throwable) {
            $hash = md5($sourceFile);
        }
        try {
            $mime = Storage::disk($sourceDisk)->mimeType($sourceFile);
        } catch (\Throwable) {
            $mime = 'application/octet-stream';
        }
        try {
            $size = Storage::disk($sourceDisk)->size($sourceFile);
        } catch (\Throwable) {
            $size = 0;
        }

        $model_type = config('files.default_model_type');
        if ($model_type == 'App\Models\User') {
            $model_id = auth()->id() ?? 1;
        } else {
            $model_id = 0;
        }

        // Phase 1: preliminary record (same pattern as storeUpload)
        $file = static::create([
            'model_type' => $model_type,
            'model_id' => $model_id,

            'collection_name' => 'default',
            'state' => 'incomplete',
            'type' => FileType::fromExtension($extension),
            'typ' => $extension,

            'disk' => $sourceDisk,
            'file' => $sourceFile,

            'hash' => $hash,
            'mime' => $mime,
            'name' => basename($sourceFile),
            'size' => $size,

            'order_column' => '1',

        ]);
        // Phase 2: copy to save disk using path_generator (same as storeUpload)
        $save_disk = config('files.save_disk');
        $pathclass = config('files.path_generator');
        $save_path = (new $pathclass)->getPath($file);
        $destFile = $save_path.'/'.$file->id.'-'.Str::random(6).'.'.$extension;
        Storage::disk($save_disk)->makeDirectory($save_path);
        $stream = Storage::disk($sourceDisk)->readStream($sourceFile);
        Storage::disk($save_disk)->writeStream($destFile, $stream);
        Storage::disk($sourceDisk)->delete($sourceFile);

        // Phase 3: update to active (same as storeUpload)
        $localPath = Storage::disk($save_disk)->path($destFile);
        // $hash = is_file($localPath) ? md5_file($localPath) : md5($destFile.$size);

        try {
            $hash = md5_file(Storage::disk($sourceDisk)->path($destFile));
        } catch (\Throwable) {
            $hash = md5($destFile);
        }

        $file->update(array_merge([
            'state' => 'active',
            'disk' => $save_disk,
            'file' => $destFile,
            'hash' => $hash,
        ], $arr_input));

        Log::channel('files')->info('#'.$file->id.' Inbox Import: '.$file->name);

        $file->markOwnFromDisk($explicitOwn, $sourceDisk);

        // ProcessFile::dispatch($file->id);
        ProcessFile::dispatchSync($file->id);

        return $file;
    }

    /**
     * Vorbelegung „Eigenes Dokument“ für ein PDF, bevor die Verarbeitung startet.
     */
    protected function markOwnFromDisk(?bool $explicit, ?string $sourceDisk = null): void
    {
        if ($this->type !== FileType::Pdf) {
            return;
        }

        $local = tempnam(sys_get_temp_dir(), 'own');
        try {
            $stream = Storage::disk($this->disk)->readStream($this->file);
            if ($stream) {
                file_put_contents($local, $stream);
                fclose($stream);
            }
            $this->mergeJson('custom_properties', ['own' => static::detectOwn($local, $explicit, $sourceDisk)]);
        } catch (\Throwable $e) {
            Log::channel('files')->warning('#'.$this->id.' Eigenes Dokument: Erkennung fehlgeschlagen: '.$e->getMessage());
        } finally {
            @unlink($local);
        }
    }

    public static function storeFile($data)
    {
        dd($data);
    }

    /**
     * @param  array<string, mixed>  $options  'own' (bool) setzt „Eigenes Dokument“
     *                                         ausdrücklich, z. B. für von der App
     *                                         erzeugte PDFs; sonst Vorbelegung nach
     *                                         Erzeugerprogramm
     */
    public static function storeUpload($upload, array $options = [])
    {
        $explicitOwn = array_key_exists('own', $options) ? (bool) $options['own'] : null;

        // dump($upload, $arr_input);
        if (! $upload->isValid()) {
            dd('Upload Error: '.$upload->getErrorMessage());
        }

        $model_type = config('files.default_model_type');
        if ($model_type == 'App\Models\User') {
            $model_id = auth()->id() ?? 1;
        } else {
            $model_id = 0;
        }

        $file = static::create([
            'model_type' => $model_type,
            'model_id' => $model_id,

            'collection_name' => 'default',
            'state' => 'incomplete',
            'type' => FileType::fromExtension($upload->extension())->value,
            'typ' => $upload->extension(),

            'disk' => 'upload',
            'file' => $upload->getRealPath(),

            'hash' => md5_file($upload->getRealPath()),
            'mime' => $upload->getMimeType(),
            'name' => $upload->getClientOriginalName(),
            'size' => $upload->getSize(),

            'order_column' => '1',

            // 'memo' => strlen($path),
            // 'memo' => json_encode($upload),
        ]);
        // $file = File::create(array_merge($arr, $arr_input));

        $save_disk = config('files.save_disk');
        $pathclass = config('files.path_generator');
        $save_path = (new $pathclass)->getPath($file);
        $file_path = $upload->storeAs(
            $save_path,
            $file->id.'-'.Str::random(6).'.'.$upload->extension(),
            $save_disk
        );
        // dump($upload, $save_disk, $save_path, $file_path);

        $file->update([
            'state' => 'active',
            'disk' => $save_disk,
            'file' => $file_path,
            // 'file' => basename($file_path),
            // 'memo' => strlen($path),
            // 'memo' => json_encode($upload),
        ]); // dump($arr);
        // $file->update(array_merge($arr, $arr_input));
        Log::channel('files')->info('#'.$file->id.' '.$file->name.' uploaded successfully');

        $file->markOwnFromDisk($explicitOwn);

        ProcessFile::dispatchSync($file->id);

        // dump($upload,  $arr_input, $save_disk, $save_path, $file_path, $file);
        return $file;
    }

    protected static function newFactory(): Factory
    {
        return FileFactory::new();
    }
}
