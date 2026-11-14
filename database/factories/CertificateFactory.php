<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_id' => fn (array $attributes) => Student::query()->withoutGlobalScopes()->whereKey($attributes['student_id'])->value('school_id'),
            'type' => CertificateType::Bonafide,
            'serial_no' => Str::upper(fake()->unique()->bothify('CRT-####-#####')),
            'title' => 'Bonafide Certificate',
            'body' => 'This is to certify that the student is enrolled.',
            'verification_code' => Str::random(32),
        ];
    }
}
