<?php

namespace Nfgarching\Files\Controllers;

// const MODEL_FILE = 'Nfgarching\Files\Models\File';
// use App\Http\Controllers\Controller;
use App\Traits\MainControllerTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Events\NewFiles;
use Nfgarching\Files\Jobs\InspectPdf;
use Nfgarching\Files\Jobs\ProcessFile;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Requests\ActionFileRequest;
use Nfgarching\Files\Services\ConversionImg;
use Nfgarching\Files\Services\ConversionOcr;
use Nfgarching\Files\Services\ConversionPdf;

class FileController
{
    use MainControllerTrait;

    public function index(Request $request)
    {
        $startTime = microtime(true);

        return view('files::index', [
            'files' => $this->get_rows($this->filteredQuery($request), $request),
            'request' => $request,
            'startTime' => $startTime,
            'jahre' => $this->jahre(),
            'diskOptions' => $this->distinctOptions('disk'),
            'stateOptions' => $this->distinctOptions('state'),
            'typeOptions' => FileType::options(),
        ]);
    }

    /**
     * Dateien eingeschränkt auf die Filter der Liste (Disk, Status, Typ, Jahr
     * der Anlage). Suche und Sortierung ergänzen get_rows()/getNavigationData()
     * – so blättert show() durch dieselbe Liste.
     */
    private function filteredQuery(Request $request): Builder
    {
        return File::query()
            ->when($request->disk, fn ($q, $v) => $q->where('disk', $v))
            ->when($request->state, fn ($q, $v) => $q->where('state', $v))
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->jahr, fn ($q, $v) => $q->whereYear('created_at', $v));
    }

    /**
     * Vorkommende Werte einer Spalte als Auswahl (Wert => Wert).
     *
     * @return array<string, string>
     */
    private function distinctOptions(string $column): array
    {
        return File::query()->whereNotNull($column)->distinct()->orderBy($column)
            ->pluck($column, $column)->all();
    }

    /**
     * Auswahl der Jahre (neuestes zuerst) zwischen ältester und jüngster Datei.
     *
     * @return array<int, int>
     */
    private function jahre(): array
    {
        $min = File::min('created_at');
        $max = File::max('created_at');

        if (! $min || ! $max) {
            return [];
        }

        $jahre = range((int) substr($max, 0, 4), (int) substr($min, 0, 4));

        return array_combine($jahre, $jahre);
    }

    public function action(Request $request, $id)
    {
        $file = File::findOrFail($id); // dd($id, $request->all(), $file);
        switch ($request->switchTo) {

            case 'IMG_CONVERSION':
                dump('Running job IMG_CONVERSION for File ID: '.$id);
                // $job = new \Nfgarching\Files\Jobs\ProcessFile($id);
                $job = new ConversionImg($id);
                $job->handle();
                dd('Job completed!');
                break;

            case 'OCR_EXECUTE':
                dump('Running job OCR_EXECUTE for File ID: '.$id);
                // $job = new \Nfgarching\Files\Jobs\ProcessFile($id);
                $job = new ConversionOcr($id);
                $job->handle();
                dd('Job completed!');
                break;

            case 'PDF_CONVERSION':
                dump('Running job PDF_CONVERSION for File ID: '.$id);
                $job = new ConversionPdf($id);
                $job->handle();
                dd('Job completed!');
                break;

            case 'ORIGINAL':
                // Schalter „Original Dokument“; an = Original behalten bzw. aus dem
                // Papierkorb zurückholen, aus = Original durch Archivfassung ersetzen
                $file->setOriginalDocument($request->boolean('value'));
                $file->refresh();

                return back()->with('message', match (true) {
                    $file->isOriginalDocument() => 'Als Original Dokument gekennzeichnet'.($file->archive()['original']['restored_at'] ?? null ? ', Original wiederhergestellt.' : ', das Original bleibt Hauptdatei.'),
                    $file->isOriginalReplaced() => 'Kennzeichnung „Original Dokument“ entfernt: Archivfassung ist jetzt die Hauptdatei, Original im Papierkorb.',
                    default => 'Kennzeichnung „Original Dokument“ entfernt. Das Original wird ersetzt, sobald die Archivfassung geprüft und gesiegelt ist.',
                });

            case 'ProcessFile':
                dump('Running job Process for File ID: '.$id);
                ProcessFile::dispatchSync($id);
                dd('Job completed!');
                break;

            case 'TestingCreateDocument':
                dump('Running job TestingCreateDokument for File ID: '.$id);
                // dd($id, $request->all());
                // ProcessFile::dispatchSync($id);
                NewFiles::dispatch(
                    [$id],
                    $request->model_type ?? 'Nfgarching\Documents\Models\Document',
                    $request->model_id,
                    'Test Upload',
                );
                // NewFiles::dispatch(files_ids: $id, subject: 'Test Upload');
                // ,
                //     'model_type' => 'App\Models\Dokument',
                //     'model_id' => 123,

                //     ]);
                dd('Job completed!');
                break;

            case 'TestingCreateDokument':
                dump('Running job TestingCreateDokument for File ID: '.$id);
                // dd($id, $request->all());
                // dd($id, $request);
                // ProcessFile::dispatchSync($id);
                NewFiles::dispatch(
                    [$id],
                    $request->model_type ?? 'App\Models\Document',
                    $request->model_id,
                    'Test Upload',
                );
                // NewFiles::dispatch(files_ids: $id, subject: 'Test Upload');
                // ,
                //     'model_type' => 'App\Models\Dokument',
                //     'model_id' => 123,

                //     ]);
                dd('Job completed!');
                break;

            case 'TestingUpdate':
                dump('Running job TestingUpdate for File ID: '.$id);
                if (! $request->model_type || ! $request->model_id) {
                    dd('model_type and model_id are required for TestingUpdate');
                }
                NewFiles::dispatch(
                    [$id],
                    $request->model_type,
                    $request->model_id,
                );
                dd('Job completed!');
                break;

            default:
                // code...
                dd($request->all(), $id);
                break;

        }

        return back()->withInput();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        dd($request->all());
        switch ($request->switchTo) {

            case 'change':
                foreach ($request->collect('checks') as $keyid) {
                    if ($request->state) {
                        File::find($keyid)->update(['state' => $request->state]);
                    }
                    if ($request->type) {
                        File::find($keyid)->update(['type' => $request->type]);
                    }
                    if ($request->typ) {
                        File::find($keyid)->update(['typ' => $request->typ]);
                    }
                }

                // dd($request->all());
                return back()->withInput();

            case 'delete':
                // code...
                break;

            case 'testing':
                // code...
                break;

            case 'value':
                // code...
                break;

            default:
                throw new \Exception("switchTo nicht programmiert: $request->switchTo");
        }
    }

    /**
     * Display the specified resource.
     */
    public function edit($id)
    {
        dd($id);

        // $prop = (new Ocr)->run(File::find($id));
        // dd($prop);

        return view('files::show', [
            'file' => File::find($id),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function show(File $file, Request $request)
    {
        $navigation = $this->getNavigationData($file, $request, $this->filteredQuery($request));

        return view('files::show', [
            'file' => $file,
            'navigation' => $navigation,
            'request' => $request,
            'startTime' => microtime(true),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, File $file)
    // {
    //     //
    // }

    /**
     * Prüft das PDF sofort neu (Eigenschaften, Signaturen, PDF/A, Prüfsummen).
     * Dauert einige Sekunden: veraPDF startet eine JVM, pyHanko fragt ggf.
     * OCSP/CRL ab.
     */
    public function inspect(File $file, Request $request): RedirectResponse
    {
        // Listenfilter der Detailseite (Blättern) mitnehmen, Tab „PDF“ öffnen
        $back = redirect()->route('files.show', ['file' => $file, ...$request->except('_token'), 'tab' => 'pdf']);

        if ($file->type !== FileType::Pdf) {
            return $back->with('message', 'Nur PDFs können geprüft werden');
        }

        InspectPdf::dispatchSync($file->id);

        return $back->with('message', 'PDF „'.Str::limit($file->name, 60).'“ geprüft');
    }

    /**
     * Löscht den Datei-Datensatz samt abgeleiteter Props (OCR usw.). Die Datei
     * auf der Disk bleibt erhalten, weil Dokumente über ihre Attachment-Liste
     * direkt auf den Pfad verweisen.
     */
    public function destroy(File $file): RedirectResponse
    {
        $this->deleteFile($file);

        return back()->with('message', 'Datei „'.Str::limit($file->name, 60).'“ gelöscht');
    }

    /**
     * Sammelaktion aus der Liste. „delete“ löscht die markierten Dateien wie
     * destroy() – nur die Datensätze, die Dateien auf der Disk bleiben.
     */
    public function bulkAction(ActionFileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['switchTo'] !== 'delete') {
            return back()->with('message', 'Diese Aktion ist noch nicht implementiert');
        }

        $files = File::findMany($validated['checks']);

        DB::connection((new File)->getConnectionName())->transaction(function () use ($files) {
            $files->each(fn (File $file) => $this->deleteFile($file));
        });

        $deleted = $files->count();

        return back()->with('message', $deleted === 1 ? '1 Datei gelöscht' : "$deleted Dateien gelöscht");
    }

    private function deleteFile(File $file): void
    {
        $file->delete();
    }
}
