<?php

namespace App\Enums;

/**
 * Events that can be delivered to webhook endpoints. Payloads carry identifiers and
 * figures, never encrypted personal fields.
 */
enum WebhookEvent: string
{
    case StudentCreated = 'student.created';
    case PaymentRecorded = 'payment.recorded';
    case AnnouncementPublished = 'announcement.published';
    case CertificateIssued = 'certificate.issued';
    case Ping = 'ping';

    public function label(): string
    {
        return match ($this) {
            self::StudentCreated => 'Student created',
            self::PaymentRecorded => 'Payment recorded',
            self::AnnouncementPublished => 'Announcement published',
            self::CertificateIssued => 'Certificate issued',
            self::Ping => 'Test ping',
        };
    }

    /**
     * Events an endpoint can subscribe to (ping is always allowed).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function subscribable(): array
    {
        $events = [];

        foreach (self::cases() as $case) {
            if ($case !== self::Ping) {
                $events[] = ['value' => $case->value, 'label' => $case->label()];
            }
        }

        return $events;
    }
}
