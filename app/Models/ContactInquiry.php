<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContactInquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A demo or sales enquiry sent from the public landing page. Not tied to a school:
 * enquiries are platform-level leads read under Administration → Enquiries.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $organization
 * @property string|null $role
 * @property string|null $school_size
 * @property string $interest
 * @property string $message
 * @property string|null $ip_address
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'organization', 'role', 'school_size', 'interest', 'message', 'ip_address'])]
class ContactInquiry extends Model
{
    /** @use HasFactory<ContactInquiryFactory> */
    use HasFactory;

    public const array INTERESTS = [
        'demo' => 'Book a demo',
        'pricing' => 'Pricing and plans',
        'migration' => 'Moving from another system',
        'partnership' => 'Partnership',
        'other' => 'Something else',
    ];

    public const array SCHOOL_SIZES = [
        'under_300' => 'Fewer than 300 students',
        '300_1000' => '300 – 1,000 students',
        '1000_3000' => '1,000 – 3,000 students',
        'over_3000' => 'More than 3,000 students',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'read_at' => 'datetime',
        ];
    }
}
