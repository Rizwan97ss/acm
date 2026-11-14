<?php

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function certificateSchool(): array
{
    $setup = teachingSchool();
    $clerk = userWithPermissions(['certificates.view', 'certificates.create', 'certificates.edit', 'certificates.delete'], $setup['school']);
    $principal = userWithPermissions(['certificates.view', 'certificates.approve'], $setup['school']);

    return [...$setup, 'clerk' => $clerk, 'principal' => $principal];
}

test('a draft is prefilled, numbered, and issued only by an approver', function () {
    $setup = certificateSchool();

    $this->actingAs($setup['clerk'])->post(route('records.certificates.store'), ['admission_no' => $setup['student']->admission_no, 'type' => 'bonafide'])
        ->assertSessionHasNoErrors();

    $certificate = Certificate::query()->sole();
    expect($certificate->serial_no)->toBe('CRT-'.now()->format('Y').'-00001')
        ->and($certificate->body)->toContain('Ali')->toContain($setup['student']->admission_no)
        ->and($certificate->status)->toBe(CertificateStatus::Draft);

    $this->actingAs($setup['clerk'])->post(route('records.certificates.issue', $certificate))->assertForbidden();
    $this->actingAs($setup['principal'])->post(route('records.certificates.issue', $certificate))->assertSessionHasNoErrors();

    expect($certificate->fresh())->status->toBe(CertificateStatus::Issued)->issued_by->toBe($setup['principal']->id);

    $this->actingAs($setup['clerk'])->put(route('records.certificates.update', $certificate), ['title' => 'Changed', 'body' => 'Changed'])
        ->assertSessionHasErrors('certificate');
    $this->actingAs($setup['clerk'])->delete(route('records.certificates.destroy', $certificate))->assertSessionHasErrors('certificate');
});

test('public verification shows issued and revoked certificates but not drafts', function () {
    $setup = certificateSchool();
    $certificate = Certificate::factory()->create(['student_id' => $setup['student']->id]);

    $this->get(route('certificates.verify', $certificate->verification_code))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('standalone/verify-certificate')->where('result', null));

    $certificate->forceFill(['status' => CertificateStatus::Issued, 'issued_on' => now()])->save();
    $this->get(route('certificates.verify', $certificate->verification_code))
        ->assertInertia(fn (Assert $page) => $page->where('result.valid', true)->where('result.serial_no', $certificate->serial_no)->missing('result.body'));

    $this->actingAs($setup['principal'])->post(route('records.certificates.revoke', $certificate), ['reason' => 'Issued in error'])->assertSessionHasNoErrors();
    auth()->logout();
    $this->get(route('certificates.verify', $certificate->verification_code))
        ->assertInertia(fn (Assert $page) => $page->where('result.valid', false)->where('result.revoked', true));
});

test('students see and print only their own issued certificates', function () {
    $setup = certificateSchool();
    $studentUser = userWithPermissions(['certificates.view'], $setup['school']);
    $setup['student']->update(['user_id' => $studentUser->id]);
    $issued = Certificate::factory()->create(['student_id' => $setup['student']->id, 'status' => CertificateStatus::Issued, 'issued_on' => now()]);
    $draft = Certificate::factory()->create(['student_id' => $setup['student']->id]);
    $someoneElse = Certificate::factory()->create(['status' => CertificateStatus::Issued]);

    $this->actingAs($studentUser)->get(route('records.certificates.index'))
        ->assertInertia(fn (Assert $page) => $page->has('certificates.data', 1)->where('certificates.data.0.serial_no', $issued->serial_no)->where('can.create', false));

    $this->actingAs($studentUser)->get(route('records.certificates.print', $issued))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('standalone/certificate')->where('certificate.verify_url', route('certificates.verify', $issued->verification_code)));
    $this->actingAs($studentUser)->get(route('records.certificates.print', $draft))->assertNotFound();
    $this->actingAs($studentUser)->get(route('records.certificates.print', $someoneElse))->assertNotFound();
});
