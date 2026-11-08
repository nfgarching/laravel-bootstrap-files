<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * file_props hielt nur das OCR-Ergebnis (key 'link2text') als Verweis auf
     * die Textdatei. Es wird als Link in die Datei selbst übernommen:
     *  - files.custom_properties.ocr     = {type, disk, text, size, error, at}
     *  - files.generated_conversions.ocr = Pfad des OCR-PDFs (neben preview/thumb)
     * Danach wird file_props gelöscht.
     */
    public function up(): void
    {
        if (! Schema::hasTable('file_props')) {
            return;
        }

        // Je Datei der jüngste link2text-Eintrag
        $props = DB::table('file_props')
            ->where('key', 'link2text')
            ->orderBy('id')
            ->get()
            ->keyBy('file_id');

        foreach ($props as $fileId => $prop) {
            $file = DB::table('files')->where('id', $fileId)->first();

            if (! $file) {
                continue;
            }

            $data = json_decode((string) $prop->data, true) ?: [];
            $disk = $data['disk'] ?? $file->disk;
            $textPath = $data['file'] ?? null;
            $failed = in_array($prop->type, ['noocr', 'errocr'], true) || ! $textPath;

            $ocr = array_filter([
                'type' => $failed && ! in_array($prop->type, ['noocr', 'errocr'], true) ? 'errocr' : $prop->type,
                'disk' => $disk,
                'text' => $failed ? null : $textPath,
                'size' => $failed ? null : ($data['size'] ?? null),
                'error' => $failed && $prop->memo ? Str::limit(trim($prop->memo), 1000) : null,
                'at' => $prop->updated_at ?? $prop->created_at,
            ], fn ($value) => $value !== null);

            $custom = json_decode((string) $file->custom_properties, true) ?: [];
            $custom['ocr'] = $ocr;

            $conversions = json_decode((string) $file->generated_conversions, true) ?: [];
            $pdfPath = $textPath ? preg_replace('/\.txt$/', '.pdf', $textPath) : null;
            if (! $failed && $pdfPath && $this->exists($disk, $pdfPath)) {
                $conversions['ocr'] = $pdfPath;
            }

            DB::table('files')->where('id', $fileId)->update([
                'custom_properties' => json_encode($custom, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'generated_conversions' => $conversions ? json_encode($conversions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            ]);
        }

        Schema::drop('file_props');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('file_props')) {
            Schema::create('file_props', function (Blueprint $table) {
                $table->id();
                $table->foreignId('file_id')
                    ->constrained('files')
                    ->onDelete('cascade');
                $table->string('key')->nullable();
                $table->string('type')->nullable();
                $table->string('label')->nullable();
                $table->string('value')->nullable();
                $table->json('data')->nullable();
                $table->text('memo')->nullable();
                $table->timestamps();
            });
        }

        DB::table('files')->whereNotNull('custom_properties')->orderBy('id')->each(function ($file) {
            $custom = json_decode((string) $file->custom_properties, true) ?: [];
            $ocr = $custom['ocr'] ?? null;

            if (! $ocr) {
                return;
            }

            DB::table('file_props')->insert([
                'file_id' => $file->id,
                'key' => 'link2text',
                'type' => $ocr['type'] ?? null,
                'data' => empty($ocr['text']) ? null : json_encode([
                    'disk' => $ocr['disk'] ?? $file->disk,
                    'file' => $ocr['text'],
                    'size' => $ocr['size'] ?? null,
                ]),
                'memo' => $ocr['error'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            unset($custom['ocr']);
            $conversions = json_decode((string) $file->generated_conversions, true) ?: [];
            unset($conversions['ocr']);

            DB::table('files')->where('id', $file->id)->update([
                'custom_properties' => $custom ? json_encode($custom) : null,
                'generated_conversions' => $conversions ? json_encode($conversions) : null,
            ]);
        });
    }

    private function exists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }
};
