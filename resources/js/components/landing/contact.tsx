import { useForm } from '@inertiajs/react';
import { CheckCircle2, Clock, Mail, MapPin, Phone, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/contact';
import { owner } from './content';
import { Reveal } from './motion';
import { Container } from './sections';

export type Option = { value: string; label: string };

type InquiryForm = {
    name: string;
    email: string;
    phone: string;
    organization: string;
    role: string;
    school_size: string;
    interest: string;
    message: string;
    website: string;
};

const empty: InquiryForm = {
    name: '',
    email: '',
    phone: '',
    organization: '',
    role: '',
    school_size: '',
    interest: 'demo',
    message: '',
    website: '',
};

export default function Contact({
    interests,
    schoolSizes,
}: {
    interests: Option[];
    schoolSizes: Option[];
}) {
    const form = useForm<InquiryForm>(empty);
    const [sent, setSent] = useState(false);
    const { contact } = owner;

    const details = [
        {
            icon: Mail,
            label: 'Email',
            value: contact.email,
            href: contact.email ? `mailto:${contact.email}` : null,
        },
        {
            icon: Phone,
            label: 'Phone',
            value: contact.phone,
            href: contact.phone
                ? `tel:${contact.phone.replace(/[^+\d]/g, '')}`
                : null,
        },
        { icon: MapPin, label: 'Address', value: contact.address, href: null },
        { icon: Clock, label: 'Hours', value: contact.hours, href: null },
    ].filter((detail) => detail.value !== '');

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setSent(true);
            },
        });
    };

    return (
        <section
            id="contact"
            aria-labelledby="contact-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container className="grid gap-12 lg:grid-cols-[1fr_1.3fr]">
                <Reveal>
                    <p className="text-sm font-semibold tracking-wider text-royal-600 uppercase dark:text-royal-300">
                        Contact
                    </p>
                    <h2
                        id="contact-title"
                        className="mt-3 text-3xl font-bold tracking-tight text-balance text-navy-950 sm:text-4xl dark:text-white"
                    >
                        See it with your own school in mind.
                    </h2>
                    <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
                        Tell us a little about your school and what you’d like
                        to see. We’ll get back to you to arrange a walkthrough
                        or a quote.
                    </p>
                    {details.length > 0 && (
                        <dl className="mt-8 space-y-4">
                            {details.map((detail) => (
                                <div
                                    key={detail.label}
                                    className="flex items-start gap-3"
                                >
                                    <dt className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-navy-50 text-navy-700 dark:bg-navy-500/15 dark:text-navy-200">
                                        <detail.icon
                                            className="size-4"
                                            aria-hidden
                                        />
                                        <span className="sr-only">
                                            {detail.label}
                                        </span>
                                    </dt>
                                    <dd className="pt-2 text-sm">
                                        {detail.href ? (
                                            <a
                                                href={detail.href}
                                                className="font-medium hover:underline"
                                            >
                                                {detail.value}
                                            </a>
                                        ) : (
                                            detail.value
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    )}
                    <ul className="mt-8 space-y-2 text-sm text-muted-foreground">
                        {[
                            'No obligation: this only starts a conversation.',
                            'Your details are used only to reply to you.',
                        ].map((line) => (
                            <li key={line} className="flex gap-2">
                                <CheckCircle2
                                    className="mt-0.5 size-4 shrink-0 text-emerald-500"
                                    aria-hidden
                                />
                                {line}
                            </li>
                        ))}
                    </ul>
                </Reveal>

                <Reveal
                    delay={120}
                    className="rounded-3xl border bg-card/90 p-6 shadow-xl shadow-navy-900/5 backdrop-blur md:p-8"
                >
                    {sent ? (
                        <div
                            role="status"
                            className="flex h-full flex-col items-center justify-center py-12 text-center"
                        >
                            <span className="flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                                <CheckCircle2 className="size-7" aria-hidden />
                            </span>
                            <h3 className="mt-5 text-xl font-semibold">
                                Thank you, your message is on its way.
                            </h3>
                            <p className="mt-2 max-w-sm text-muted-foreground">
                                We’ve received your enquiry and will reply to
                                the email address you gave us.
                            </p>
                            <Button
                                variant="outline"
                                className="mt-6 rounded-full"
                                onClick={() => setSent(false)}
                            >
                                Send another message
                            </Button>
                        </div>
                    ) : (
                        <form
                            onSubmit={submit}
                            noValidate
                            className="grid gap-5 sm:grid-cols-2"
                        >
                            <FormField
                                id="contact-name"
                                label="Your name"
                                required
                                error={form.errors.name}
                            >
                                <Input
                                    id="contact-name"
                                    autoComplete="name"
                                    required
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="contact-email"
                                label="Work email"
                                required
                                error={form.errors.email}
                            >
                                <Input
                                    id="contact-email"
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="contact-organization"
                                label="School or organisation"
                                error={form.errors.organization}
                            >
                                <Input
                                    id="contact-organization"
                                    autoComplete="organization"
                                    value={form.data.organization}
                                    onChange={(e) =>
                                        form.setData(
                                            'organization',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="contact-role"
                                label="Your role"
                                error={form.errors.role}
                            >
                                <Input
                                    id="contact-role"
                                    autoComplete="organization-title"
                                    placeholder="e.g. Principal"
                                    value={form.data.role}
                                    onChange={(e) =>
                                        form.setData('role', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="contact-phone"
                                label="Phone"
                                error={form.errors.phone}
                            >
                                <Input
                                    id="contact-phone"
                                    type="tel"
                                    autoComplete="tel"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="contact-size"
                                label="School size"
                                error={form.errors.school_size}
                            >
                                <NativeSelect
                                    id="contact-size"
                                    value={form.data.school_size}
                                    onChange={(e) =>
                                        form.setData(
                                            'school_size',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">Choose…</option>
                                    {schoolSizes.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="contact-interest"
                                label="I’m interested in"
                                required
                                error={form.errors.interest}
                                className="sm:col-span-2"
                            >
                                <NativeSelect
                                    id="contact-interest"
                                    value={form.data.interest}
                                    onChange={(e) =>
                                        form.setData('interest', e.target.value)
                                    }
                                >
                                    {interests.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </FormField>
                            <FormField
                                id="contact-message"
                                label="How can we help?"
                                required
                                error={form.errors.message}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="contact-message"
                                    rows={5}
                                    required
                                    value={form.data.message}
                                    onChange={(e) =>
                                        form.setData('message', e.target.value)
                                    }
                                />
                            </FormField>
                            <div className="hidden" aria-hidden>
                                <label htmlFor="contact-website">
                                    Leave this field empty
                                </label>
                                <input
                                    id="contact-website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={form.data.website}
                                    onChange={(e) =>
                                        form.setData('website', e.target.value)
                                    }
                                />
                            </div>
                            <div className="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
                                <p className="text-xs text-muted-foreground">
                                    Fields marked * are required.
                                </p>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                    className="h-12 rounded-full bg-gradient-to-r from-navy-700 to-royal-600 px-7 text-white shadow-lg shadow-navy-700/25 hover:brightness-110"
                                >
                                    {form.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Send aria-hidden />
                                    )}
                                    Send enquiry
                                </Button>
                            </div>
                        </form>
                    )}
                </Reveal>
            </Container>
        </section>
    );
}
