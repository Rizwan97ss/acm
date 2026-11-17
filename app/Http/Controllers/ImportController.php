<?php

namespace App\Http\Controllers;

use App\Services\Import\ImportBatch;
use App\Services\Import\ImportColumn;
use App\Services\Import\Importer;
use App\Services\Import\ImportRegistry;
use App\Services\Import\ImportService;
use App\Services\Import\TemplateWriter;
use App\Services\Tenancy\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Spreadsheet imports: download a template, upload a filled file to preview every row's
 * problems, then import the whole file in one go. The uploaded file is kept on the private
 * disk only between preview and import; the session remembers which file belongs to whom.
 */
class ImportController extends Controller
{
    private const string DISK = 'local';

    public function __construct(
        private ImportRegistry $registry,
        private ImportService $imports,
        private CurrentSchool $currentSchool,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('imports/index', [
            'importers' => array_map(fn (Importer $importer): array => [
                'key' => $importer->key(),
                'title' => $importer->title(),
                'description' => $importer->description(),
            ], $this->registry->availableTo($request->user())),
        ]);
    }

    public function show(Request $request, string $type): Response
    {
        $importer = $this->importer($request, $type);
        $pending = $request->session()->get($this->sessionKey($importer));

        return Inertia::render('imports/show', [
            'importer' => [
                'key' => $importer->key(),
                'title' => $importer->title(),
                'description' => $importer->description(),
                'columns' => array_map(fn (ImportColumn $column): array => $column->toArray(), $importer->columns()),
            ],
            'preview' => is_array($pending) ? [
                'token' => $pending['token'],
                'file_name' => $pending['file_name'],
                ...$pending['report'],
            ] : null,
        ]);
    }

    public function template(Request $request, string $type, TemplateWriter $writer): BinaryFileResponse
    {
        $importer = $this->importer($request, $type);
        $path = tempnam(sys_get_temp_dir(), 'import-template-').'.xlsx';

        $writer->write($importer, $path);

        return response()->download($path, Str::slug($importer->title()).'-template.xlsx')->deleteFileAfterSend();
    }

    public function preview(Request $request, string $type): RedirectResponse
    {
        $importer = $this->importer($request, $type);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
        ], ['file.mimes' => __('Upload an .xlsx or .csv file.')]);

        $this->discardPending($request, $importer);

        $file = $request->file('file');
        $extension = Str::lower($file->getClientOriginalExtension()) === 'xlsx' ? 'xlsx' : 'csv';
        $storedPath = $file->storeAs('imports', Str::uuid().'.'.$extension, self::DISK);

        if ($storedPath === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be saved. Try again.')]);
        }

        try {
            $report = $this->imports->preview($importer, Storage::disk(self::DISK)->path($storedPath), $extension, $this->batch($request));
        } catch (ValidationException $exception) {
            Storage::disk(self::DISK)->delete($storedPath);

            throw $exception;
        }

        $request->session()->put($this->sessionKey($importer), [
            'token' => Str::random(40),
            'path' => $storedPath,
            'extension' => $extension,
            'file_name' => Str::limit($file->getClientOriginalName(), 120),
            'report' => $report,
        ]);

        Log::info('Spreadsheet import previewed.', ['importer' => $importer->key(), 'rows' => $report['total'], 'invalid' => $report['invalid'], 'user_id' => $request->user()->id]);

        return to_route('imports.show', $importer->key());
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $importer = $this->importer($request, $type);
        $pending = $request->session()->get($this->sessionKey($importer));

        $request->validate(['token' => ['required', 'string']]);

        if (! is_array($pending) || ! hash_equals($pending['token'], (string) $request->input('token'))) {
            throw ValidationException::withMessages(['file' => __('This preview has expired. Upload the file again.')]);
        }

        $path = Storage::disk(self::DISK)->path($pending['path']);

        if (! is_file($path)) {
            $request->session()->forget($this->sessionKey($importer));

            throw ValidationException::withMessages(['file' => __('This preview has expired. Upload the file again.')]);
        }

        $count = $this->imports->import($importer, $path, $pending['extension'], $this->batch($request), $pending['file_name']);

        $this->discardPending($request, $importer);

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} :count record imported.|[2,*] :count records imported.', $count, ['count' => $count])]);

        return to_route('imports.show', $importer->key());
    }

    public function destroy(Request $request, string $type): RedirectResponse
    {
        $this->discardPending($request, $this->importer($request, $type));

        return to_route('imports.show', $type);
    }

    private function importer(Request $request, string $type): Importer
    {
        $importer = $this->registry->find($type);

        abort_if($importer === null, 404);
        abort_unless($importer->authorize($request->user()), 403);

        return $importer;
    }

    private function batch(Request $request): ImportBatch
    {
        return new ImportBatch($request->user(), (int) $this->currentSchool->id());
    }

    private function discardPending(Request $request, Importer $importer): void
    {
        $pending = $request->session()->pull($this->sessionKey($importer));

        if (is_array($pending) && is_string($pending['path'] ?? null)) {
            Storage::disk(self::DISK)->delete($pending['path']);
        }
    }

    private function sessionKey(Importer $importer): string
    {
        return 'imports.'.$importer->key();
    }
}
