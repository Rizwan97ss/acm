<?php

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('local'));

test('documents are stored privately and downloaded only by their audience', function () {
    $setup = communitySchool();
    $uploader = userWithPermissions(['documents.view', 'documents.create', 'documents.delete'], $setup['school']);

    $this->actingAs($uploader)->post(route('records.documents.store'), [
        'title' => 'Class 1 reading list', 'audience' => 'guardians', 'school_class_id' => $setup['section']->school_class_id,
        'file' => UploadedFile::fake()->create('reading.pdf', 100, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $document = Document::query()->sole();
    Storage::disk('local')->assertExists($document->path);
    expect($document->path)->not->toContain('reading');

    $this->actingAs(userWithPermissions(['documents.view'], $setup['school']))
        ->get(route('records.documents.download', $document))->assertNotFound();
    $this->actingAs($setup['otherGuardianUser'])->get(route('records.documents.download', $document))->assertForbidden();

    $this->actingAs($uploader)->get(route('records.documents.download', $document))
        ->assertOk()
        ->assertDownload('reading.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('readers only list documents addressed to them', function () {
    $setup = communitySchool();
    $reader = userWithPermissions(['documents.view'], $setup['school']);
    foreach (['everyone' => 'Handbook', 'guardians' => 'Parents only'] as $audience => $title) {
        Document::query()->create(['school_id' => $setup['school']->id, 'title' => $title, 'audience' => $audience, 'path' => "documents/x/{$title}", 'original_name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
    }

    $this->actingAs($reader)->get(route('records.documents.index'))
        ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.title', 'Handbook'));
});

test('executable and disguised files are rejected', function () {
    $setup = communitySchool();
    $uploader = userWithPermissions(['documents.view', 'documents.create'], $setup['school']);

    foreach ([UploadedFile::fake()->create('evil.php', 1, 'application/x-php'), UploadedFile::fake()->create('page.html', 1, 'text/html'), UploadedFile::fake()->create('huge.pdf', 20000, 'application/pdf')] as $file) {
        $this->actingAs($uploader)->post(route('records.documents.store'), ['title' => 'Bad', 'audience' => 'everyone', 'file' => $file])
            ->assertSessionHasErrors('file');
    }

    expect(Document::query()->count())->toBe(0);
});
