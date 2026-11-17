import { ArrowRight, Check, ChevronDown, Quote, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { faqs, owner, plans } from './content';
import { Reveal } from './motion';
import { Container, SectionHeading } from './sections';

/**
 * Customer quotes and client names come only from `owner` in content.ts. With none
 * filled in, production shows nothing here; development shows labelled slots so the
 * owner can see where verified proof will appear.
 */
export function Testimonials() {
    const { testimonials, clients } = owner;
    const showPlaceholders = import.meta.env.DEV && testimonials.length === 0;

    if (
        testimonials.length === 0 &&
        clients.length === 0 &&
        !showPlaceholders
    ) {
        return null;
    }

    return (
        <section
            aria-labelledby="testimonials-title"
            className="py-20 md:py-28"
        >
            <Container>
                <SectionHeading
                    id="testimonials-title"
                    eyebrow="Trusted by schools"
                    title="What school leaders say."
                />

                {clients.length > 0 && (
                    <Reveal className="mt-10 flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
                        {clients.map((client) => (
                            <span
                                key={client}
                                className="text-lg font-semibold text-muted-foreground"
                            >
                                {client}
                            </span>
                        ))}
                    </Reveal>
                )}

                <div className="mt-12 grid gap-5 md:grid-cols-3">
                    {testimonials.map((testimonial, index) => (
                        <Reveal
                            key={testimonial.name}
                            as="figure"
                            delay={index * 100}
                            className="flex flex-col rounded-3xl border bg-card p-7 shadow-sm"
                        >
                            <Quote
                                className="size-7 text-gold-500"
                                aria-hidden
                            />
                            <blockquote className="mt-4 flex-1 leading-relaxed">
                                “{testimonial.quote}”
                            </blockquote>
                            <figcaption className="mt-6 text-sm">
                                <span className="font-semibold">
                                    {testimonial.name}
                                </span>
                                <span className="block text-muted-foreground">
                                    {testimonial.role},{' '}
                                    {testimonial.organization}
                                </span>
                            </figcaption>
                        </Reveal>
                    ))}

                    {showPlaceholders &&
                        [1, 2, 3].map((slot) => (
                            <div
                                key={slot}
                                className="rounded-3xl border-2 border-dashed border-gold-300 bg-gold-50/50 p-7 text-sm text-gold-900 dark:border-gold-500/40 dark:bg-gold-500/5 dark:text-gold-200"
                            >
                                <p className="font-semibold">
                                    Placeholder: testimonial {slot}
                                </p>
                                <p className="mt-2">
                                    Add a verified quote, with permission, to{' '}
                                    <code>owner.testimonials</code> in
                                    resources/js/components/landing/content.ts.
                                    This box only appears in development.
                                </p>
                            </div>
                        ))}
                </div>
            </Container>
        </section>
    );
}

export function Plans() {
    return (
        <section
            id="plans"
            aria-labelledby="plans-title"
            className="scroll-mt-20 bg-gradient-to-b from-transparent via-gold-50/40 to-transparent py-20 md:py-28 dark:via-gold-500/5"
        >
            <Container>
                <SectionHeading
                    id="plans-title"
                    eyebrow="Plans"
                    title="Every module included. Choose how you run it."
                >
                    Pricing depends on your school’s size and setup. Tell us
                    about your school and we’ll prepare a quote.
                </SectionHeading>
                <div className="mt-14 grid gap-6 lg:grid-cols-3">
                    {plans.map((plan, index) => (
                        <Reveal
                            key={plan.name}
                            delay={index * 100}
                            className={cn(
                                'relative flex flex-col rounded-3xl border p-8 shadow-sm',
                                plan.featured
                                    ? 'border-transparent bg-gradient-to-b from-navy-900 to-navy-800 text-white shadow-2xl shadow-navy-900/30 lg:-my-4 lg:py-12'
                                    : 'bg-card',
                            )}
                        >
                            {plan.featured && (
                                <span className="absolute -top-3 left-8 inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-gold-300 to-gold-500 px-3 py-1 text-xs font-semibold text-navy-950 shadow">
                                    <Sparkles className="size-3" aria-hidden />
                                    Most complete
                                </span>
                            )}
                            <h3 className="text-xl font-semibold">
                                {plan.name}
                            </h3>
                            <p
                                className={cn(
                                    'mt-2 text-sm',
                                    plan.featured
                                        ? 'text-white/75'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {plan.summary}
                            </p>
                            <p className="mt-6 text-3xl font-bold tracking-tight">
                                {plan.price ?? 'Custom quote'}
                            </p>
                            <ul className="mt-6 flex-1 space-y-3 text-sm">
                                {plan.includes.map((item) => (
                                    <li key={item} className="flex gap-3">
                                        <Check
                                            className={cn(
                                                'mt-0.5 size-4 shrink-0',
                                                plan.featured
                                                    ? 'text-gold-300'
                                                    : 'text-emerald-500',
                                            )}
                                            aria-hidden
                                        />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                            <Button
                                asChild
                                size="lg"
                                className={cn(
                                    'mt-8 rounded-full',
                                    plan.featured
                                        ? 'bg-gradient-to-r from-gold-300 to-gold-500 text-navy-950 hover:brightness-105'
                                        : '',
                                )}
                                variant={plan.featured ? 'default' : 'outline'}
                            >
                                <a href="#contact">Request a quote</a>
                            </Button>
                        </Reveal>
                    ))}
                </div>
            </Container>
        </section>
    );
}

export function Faq() {
    return (
        <section
            id="faq"
            aria-labelledby="faq-title"
            className="scroll-mt-20 py-20 md:py-28"
        >
            <Container className="max-w-4xl">
                <SectionHeading
                    id="faq-title"
                    eyebrow="FAQ"
                    title="Common questions, straight answers."
                />
                <Reveal className="mt-12 divide-y rounded-3xl border bg-card shadow-sm">
                    {faqs.map((faq) => (
                        <details
                            key={faq.question}
                            className="group px-6 py-1 [&_summary::-webkit-details-marker]:hidden"
                        >
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-4 py-5 font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none">
                                {faq.question}
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted transition group-open:rotate-180 group-open:bg-navy-700 group-open:text-white">
                                    <ChevronDown
                                        className="size-4"
                                        aria-hidden
                                    />
                                </span>
                            </summary>
                            <p className="pb-6 leading-relaxed text-muted-foreground">
                                {faq.answer}
                            </p>
                        </details>
                    ))}
                </Reveal>
            </Container>
        </section>
    );
}

export function CtaBand({ title, text }: { title: string; text: string }) {
    return (
        <section aria-label="Get started" className="py-10">
            <Container>
                <Reveal className="relative overflow-hidden rounded-[2rem] bg-gradient-to-r from-gold-300 via-gold-400 to-orange-400 p-8 shadow-xl shadow-gold-900/15 md:flex md:items-center md:justify-between md:p-12">
                    <div
                        className="pointer-events-none absolute -bottom-20 -left-10 size-64 rounded-full bg-white/30 blur-2xl"
                        aria-hidden
                    />
                    <div className="relative max-w-2xl text-navy-950">
                        <p className="text-2xl font-bold tracking-tight md:text-3xl">
                            {title}
                        </p>
                        <p className="mt-2 text-navy-950/75">{text}</p>
                    </div>
                    <Button
                        asChild
                        size="lg"
                        className="relative mt-6 h-12 shrink-0 rounded-full bg-navy-950 px-7 text-white shadow-xl hover:bg-navy-800 md:mt-0"
                    >
                        <a href="#contact">
                            Book a demo
                            <ArrowRight aria-hidden />
                        </a>
                    </Button>
                </Reveal>
            </Container>
        </section>
    );
}
