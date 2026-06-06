import { __ } from "@wordpress/i18n";
import {
    Layers,
    Target,
    MessageCircle,
    LayoutGrid,
    FileText,
    Tags,
    Code2,
    Blocks,
    BookOpen,
    Video,
    Zap,
    Sparkles,
} from 'lucide-react';
import { useNavigate } from 'react-router';
import FeatureCard from '@/features/dashboard/components/featured-card';
import ProUpsell from '@/features/dashboard/components/pro-upsell';
import { Slot } from '@/components/slot';
import { Slots } from '@/lib/slots';
import { Button } from '@/components/ui/button';
import Topbar from '@/components/layout/topbar';
import Container from '@/components/container';

const Dashboard = () => {
    const navigate = useNavigate();

    return (
        <>
            <Topbar />
            <Container className="space-y-10 my-8 px-4">
                {/* Hero */}
                <div className="relative overflow-hidden rounded-3xl p-8 lg:p-12 bg-gradient-to-br from-indigo-700 via-indigo-800 to-purple-900 shadow-2xl shadow-indigo-900/40 text-white">
                    <div className="relative z-10 max-w-2xl">
                        <span className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider bg-white/10 ring-1 ring-white/20 rounded-full px-3 py-1 mb-5">
                            <Sparkles className="h-3.5 w-3.5" /> {__('All-in-one Elementor toolkit', 'elemacy')}
                        </span>
                        <div className="text-4xl lg:text-5xl font-extrabold mb-4 tracking-tight">
                            {__('Welcome to Elemacy', 'elemacy')}
                        </div>
                        <div className="text-lg text-indigo-100/90 mb-7 max-w-xl">
                            {__('Theme builder, popups, loop & form builders, dynamic tags and more — all in one place. Works with the free version of Elementor.', 'elemacy')}
                        </div>
                        <div className="flex flex-col sm:flex-row gap-3">
                            <Button
                                size="lg"
                                variant="secondary"
                                onClick={() => navigate('/modules')}
                                className="w-full sm:w-auto cursor-pointer"
                            >
                                <Zap className="mr-2 h-5 w-5" />
                                {__('Manage Modules', 'elemacy')}
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => window.open('https://elemacy.com', '_blank')}
                                className="bg-white/10 border-white/30 hover:bg-white/20 text-white w-full sm:w-auto cursor-pointer"
                            >
                                <BookOpen className="mr-2 h-5 w-5" />
                                {__('Documentation', 'elemacy')}
                            </Button>
                        </div>
                    </div>

                    <div className="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl opacity-50" />
                    <div className="absolute -bottom-10 right-1/4 w-64 h-64 bg-fuchsia-400/20 rounded-full blur-3xl opacity-40" />
                </div>

                {/* Intro video */}
                <div className="rounded-3xl overflow-hidden shadow-lg border border-gray-200 bg-white">
                    <div className="p-6 border-b border-gray-100 flex items-center gap-3">
                        <div className="p-2.5 rounded-xl bg-indigo-50">
                            <Video className="h-5 w-5 text-indigo-600" />
                        </div>
                        <div>
                            <div className="text-lg font-bold text-gray-900">{__('Getting started with Elemacy', 'elemacy')}</div>
                            <div className="text-sm text-gray-500">{__('A quick tour of the plugin and its features.', 'elemacy')}</div>
                        </div>
                    </div>
                    <div className="aspect-video bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center">
                        <div className="flex flex-col items-center gap-3 text-gray-400">
                            <Video className="h-12 w-12" />
                            <span className="text-base font-medium">{__('Video coming soon', 'elemacy')}</span>
                        </div>
                        {/* When the video is ready, replace the placeholder above with:
                        <iframe className="w-full h-full" src="https://www.youtube.com/embed/VIDEO_ID" title={__('Elemacy introduction', 'elemacy')} frameBorder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen></iframe>
                        */}
                    </div>
                </div>

                {/* Free features */}
                <section>
                    <div className="mb-6">
                        <div className="text-2xl font-bold text-gray-900">{__('Everything included for free', 'elemacy')}</div>
                        <div className="text-sm text-gray-500 mt-1">{__('A complete website-building suite — enable only the modules you need.', 'elemacy')}</div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                        <FeatureCard
                            icon={Layers}
                            title={__('Theme Builder', 'elemacy')}
                            description={__('Headers, footers, single, archive, 404 and search templates for any theme.', 'elemacy')}
                            onClick={() => navigate('/theme-builder')}
                        />
                        <FeatureCard
                            icon={Target}
                            title={__('Display Conditions', 'elemacy')}
                            description={__('Assign templates anywhere with include and exclude rules.', 'elemacy')}
                            onClick={() => navigate('/theme-builder')}
                        />
                        <FeatureCard
                            icon={MessageCircle}
                            title={__('Popups & Floating', 'elemacy')}
                            description={__('Popups, top/bottom bars, banners and floating elements.', 'elemacy')}
                            onClick={() => navigate('/popups')}
                        />
                        <FeatureCard
                            icon={LayoutGrid}
                            title={__('Loop Builder', 'elemacy')}
                            description={__('Dynamic grids and carousels with AJAX pagination.', 'elemacy')}
                            onClick={() => navigate('/widgets')}
                        />
                        <FeatureCard
                            icon={FileText}
                            title={__('Form Builder', 'elemacy')}
                            description={__('Custom forms with email notifications, built in Elementor.', 'elemacy')}
                            onClick={() => navigate('/widgets')}
                        />
                        <FeatureCard
                            icon={Tags}
                            title={__('Dynamic Tags', 'elemacy')}
                            description={__('Post, site, archive, author, WooCommerce and ACF data.', 'elemacy')}
                        />
                        <FeatureCard
                            icon={Code2}
                            title={__('Custom CSS', 'elemacy')}
                            description={__('Per widget, section, column or page — no child theme needed.', 'elemacy')}
                        />
                        <FeatureCard
                            icon={Blocks}
                            title={__('Widgets Library', 'elemacy')}
                            description={__('Nav Menu, Site Logo, Search and ACF widgets including ACF Repeater.', 'elemacy')}
                            onClick={() => navigate('/widgets')}
                        />
                    </div>
                </section>

                {/* Pro: free renders the upsell; Elemacy Pro replaces it via the slot when installed. */}
                <Slot name={Slots.DASHBOARD_PRO} fallback={<ProUpsell />} />

                {/* Resources */}
                <section>
                    <div className="mb-6">
                        <div className="text-2xl font-bold text-gray-900">{__('Resources & support', 'elemacy')}</div>
                        <div className="text-sm text-gray-500 mt-1">{__('Learn faster and get help when you need it.', 'elemacy')}</div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <FeatureCard
                            icon={BookOpen}
                            title={__('Documentation', 'elemacy')}
                            description={__('Guides, tutorials and references for every feature.', 'elemacy')}
                            onClick={() => window.open('https://elemacy.com', '_blank')}
                        />
                        <FeatureCard
                            icon={Video}
                            title={__('Video Tutorials', 'elemacy')}
                            description={__('Step-by-step walkthroughs on our YouTube channel.', 'elemacy')}
                            onClick={() => window.open('https://www.youtube.com/@Techimium', '_blank')}
                        />
                        <FeatureCard
                            icon={MessageCircle}
                            title={__('Community & Support', 'elemacy')}
                            description={__('Join the community and reach the team for help.', 'elemacy')}
                            onClick={() => window.open('https://www.facebook.com/techimium/', '_blank')}
                        />
                    </div>
                </section>
            </Container>
        </>
    );
};

export default Dashboard;
