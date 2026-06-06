import { __ } from '@wordpress/i18n';
import {
    Sparkles,
    Target,
    MousePointerClick,
    SlidersHorizontal,
    BarChart3,
    KeyRound,
    Crown,
    ArrowRight,
} from 'lucide-react';
import type { LucideProps } from 'lucide-react';
import type { ComponentType } from 'react';
import { Button } from '@/components/ui/button';

const PRO_URL = 'https://elemacy.com';

interface ProFeature {
    icon: ComponentType<LucideProps>;
    title: string;
    description: string;
}

const proFeatures: ProFeature[] = [
    {
        icon: Sparkles,
        title: __('Animations', 'elemacy'),
        description: __('Scroll, entrance and on-load animations for any element, powered by GSAP.', 'elemacy'),
    },
    {
        icon: Target,
        title: __('Advanced Display Conditions', 'elemacy'),
        description: __('Front page, blog, 404, search, author, taxonomy and date archives, and more.', 'elemacy'),
    },
    {
        icon: MousePointerClick,
        title: __('Advanced Popup Triggers', 'elemacy'),
        description: __('Scroll, exit intent, inactivity, scroll-to-element and on-popup-close.', 'elemacy'),
    },
    {
        icon: SlidersHorizontal,
        title: __('Advanced Popup Rules', 'elemacy'),
        description: __('Once-per period, devices, schedule, page views, sessions, referrer, roles and browser.', 'elemacy'),
    },
    {
        icon: BarChart3,
        title: __('Analytics & A/B Testing', 'elemacy'),
        description: __('Track popup impressions and conversions, and run experiments.', 'elemacy'),
    },
    {
        icon: KeyRound,
        title: __('Updates & Priority Support', 'elemacy'),
        description: __('One-click updates and priority support from the Elemacy team.', 'elemacy'),
    },
];

const ProUpsell = () => (
    <section className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-gray-900 via-indigo-950 to-purple-950 p-8 lg:p-10 text-white shadow-2xl">
        <div className="relative z-10">
            <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-8">
                <div className="max-w-xl">
                    <span className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider bg-amber-400/15 text-amber-300 ring-1 ring-amber-400/30 rounded-full px-3 py-1 mb-4">
                        <Crown className="h-3.5 w-3.5" /> {__('Elemacy Pro', 'elemacy')}
                    </span>
                    <div className="text-3xl font-extrabold tracking-tight mb-2">{__('Do more with Elemacy Pro', 'elemacy')}</div>
                    <div className="text-indigo-100/80">
                        {__('Unlock animations, advanced display conditions, smarter popups and analytics. Pro only adds capabilities — nothing in the free plugin is limited.', 'elemacy')}
                    </div>
                </div>
                <Button
                    size="lg"
                    onClick={() => window.open(PRO_URL, '_blank')}
                    className="shrink-0 cursor-pointer bg-amber-400 text-amber-950 hover:bg-amber-300"
                >
                    {__('Upgrade to Pro', 'elemacy')}
                    <ArrowRight className="ml-2 h-5 w-5" />
                </Button>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {proFeatures.map(({ icon: Icon, title, description }) => (
                    <div key={title} className="rounded-2xl bg-white/5 ring-1 ring-white/10 p-5 hover:bg-white/10 transition-colors">
                        <div className="p-2.5 rounded-xl inline-flex bg-amber-400/15 mb-3">
                            <Icon className="h-5 w-5 text-amber-300" />
                        </div>
                        <div className="font-semibold mb-1">{title}</div>
                        <div className="text-sm text-indigo-100/70 leading-relaxed">{description}</div>
                    </div>
                ))}
            </div>
        </div>
        <div className="absolute -top-16 -right-16 w-80 h-80 bg-amber-400/10 rounded-full blur-3xl" />
    </section>
);

export default ProUpsell;
