import { Head, usePage } from '@inertiajs/react';
import type { Option } from '@/components/landing/contact';
import Contact from '@/components/landing/contact';
import {
    CtaBand,
    Faq,
    Plans,
    Testimonials,
} from '@/components/landing/conversion';
import type { Facts } from '@/components/landing/features';
import {
    FeatureTour,
    Integrations,
    Security,
    Stats,
} from '@/components/landing/features';
import Hero from '@/components/landing/hero';
import LandingFooter from '@/components/landing/landing-footer';
import LandingNav from '@/components/landing/landing-nav';
import { useLandingMotion } from '@/components/landing/motion';
import {
    About,
    HowItWorks,
    Modules,
    UseCases,
    WhyUs,
} from '@/components/landing/sections';

type Props = {
    facts: Facts;
    inquiryOptions: { interests: Option[]; schoolSizes: Option[] };
};

const description =
    'A complete, secure school management ERP: admissions, timetables, attendance, exams, fees, payroll and parent communication in one connected system.';

export default function Welcome({ facts, inquiryOptions }: Props) {
    const { branding, name } = usePage().props;
    const brand = branding?.name ?? name;

    useLandingMotion();

    return (
        <>
            <Head title="School management ERP">
                <meta
                    head-key="description"
                    name="description"
                    content={description}
                />
                <meta head-key="og:type" property="og:type" content="website" />
                <meta
                    head-key="og:title"
                    property="og:title"
                    content={`${brand} | School management ERP`}
                />
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={description}
                />
                <meta
                    head-key="twitter:card"
                    name="twitter:card"
                    content="summary"
                />
            </Head>

            <div className="landing-page min-h-screen bg-background text-foreground">
                <a
                    href="#main"
                    className="sr-only z-[60] rounded-full bg-navy-900 px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
                >
                    Skip to content
                </a>
                <LandingNav brand={brand} />

                <main id="main">
                    <Hero modules={facts.modules} />
                    <Stats facts={facts} />
                    <About />
                    <Modules count={facts.modules} />
                    <WhyUs />
                    <HowItWorks />
                    <FeatureTour />
                    <Security />
                    <CtaBand
                        title="Ready to see it with your own data?"
                        text="Book a walkthrough and we’ll show you the workflows that matter most to your school."
                    />
                    <UseCases />
                    <Integrations facts={facts} />
                    <Testimonials />
                    <Plans />
                    <Faq />
                    <Contact
                        interests={inquiryOptions.interests}
                        schoolSizes={inquiryOptions.schoolSizes}
                    />
                </main>

                <LandingFooter brand={brand} />
            </div>
        </>
    );
}
