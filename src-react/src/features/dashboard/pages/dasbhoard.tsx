import {
    Sparkles,
    Layers,
    Zap,
    BookOpen,
    Youtube,
    MessageCircle,
    Users,
    MousePointerClick,
    LayoutGrid,
} from 'lucide-react';
import StatCard from '@/features/dashboard/components/stat-card';
import FeatureCard from '@/features/dashboard/components/featured-card';
import { useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import Topbar from '@/components/layout/topbar';
import Container from '@/components/container';

const Dashboard = () => {
    const navigate = useNavigate();

    return (
        <>
            <Topbar />
            <Container className="space-y-6 mt-6 px-4">
                {/* Hero Section */}
                <div className="relative overflow-hidden rounded-3xl p-8 lg:p-12 bg-gradient-to-br from-indigo-800 to-purple-900 shadow-2xl shadow-indigo-900/40 text-white">
                    <div className="relative z-10 max-w-2xl">
                        <div className="text-4xl lg:text-5xl font-extrabold mb-4 tracking-tight">
                            Welcome to Elemacy
                        </div>
                        <p className="text-lg opacity-85 mb-6">
                            Supercharge your Elementor experience with pro-level features. Build stunning websites faster with our powerful addon tools.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-3">
                            <Button
                                size="lg"
                                variant="secondary"
                                onClick={() => navigate('/popups')}
                                className="w-full sm:w-auto"
                            >
                                <Sparkles className="mr-2 h-5 w-5" />
                                Create Your First Popup
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => console.log('Watch Tutorial clicked')}
                                className="bg-white/10 border-white/30 hover:bg-white/20 text-white w-full sm:w-auto"
                            >
                                <Youtube className="mr-2 h-5 w-5" />
                                Watch Tutorial
                            </Button>
                        </div>
                    </div>

                    {/* Decorative elements */}
                    <div className="absolute top-0 right-0 w-96 h-96 bg-white/5 rounded-full blur-3xl opacity-50" />
                    <div className="absolute bottom-0 right-1/4 w-64 h-64 bg-indigo-300/20 rounded-full blur-3xl opacity-30" />
                </div>

                {/* Video Embed Example */}
                <div className="rounded-3xl overflow-hidden shadow-xl border border-gray-200 bg-white">
                    <div className="p-6 border-b border-gray-100">
                        <div className="text-xl font-bold text-gray-900">Getting Started with Elemacy</div>
                        <p className="text-sm text-gray-500 mt-1">
                            Watch this quick introduction to learn the basics
                        </p>
                    </div>
                    <div className="aspect-video bg-gray-50 flex items-center justify-center p-8">
                        <iframe width="100%" height="100%" src="https://www.youtube.com/embed/ozwmlFencJI?si=nSwznxC8iXLxIKD0" title="YouTube video player" frameBorder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen></iframe>
                    </div>
                </div>

                {/* Stats */}
                <div>
                    <div className="text-2xl font-bold mb-6 text-gray-900">Performance Metrics</div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard
                            icon={MousePointerClick}
                            label="Active Popups"
                            value="12"
                            trend={{ value: "23%", isPositive: true }}
                        />
                        <StatCard
                            icon={Layers}
                            label="Theme Templates"
                            value="8"
                            trend={{ value: "12%", isPositive: true }}
                        />
                        <StatCard
                            icon={Users}
                            label="Total Views"
                            value="45.2K"
                            trend={{ value: "8%", isPositive: true }}
                        />
                    </div>
                </div>

                {/* Features Grid */}
                <div>
                    <div className="text-2xl font-bold mb-6 text-gray-900">Core Features</div>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <FeatureCard
                            icon={Sparkles}
                            title="Advanced Popups"
                            description="Create beautiful popups with advanced targeting and triggers"
                            onClick={() => navigate('/popups')}
                        />
                        <FeatureCard
                            icon={Layers}
                            title="Theme Builder"
                            description="Design custom headers, footers, and templates"
                            onClick={() => navigate('/theme-builder')}
                        />
                        <FeatureCard
                            icon={LayoutGrid}
                            title="Widgets"
                            description="Manage and toggle all your essential widgets"
                            onClick={() => navigate('/widgets')}
                        />
                        <FeatureCard
                            icon={Zap}
                            title="Pro Modules"
                            description="Access premium widgets and advanced functionality"
                            gradient
                            onClick={() => navigate('/modules')}
                        />
                    </div>
                </div>

                {/* Resources */}
                <div>
                    <div className="text-2xl font-bold mb-6 text-gray-900">Resources & Support</div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <FeatureCard
                            icon={BookOpen}
                            title="Documentation"
                            description="Comprehensive guides and API references"
                            onClick={() => window.open('https://docs.elemacy.com', '_blank')}
                        />
                        <FeatureCard
                            icon={Youtube}
                            title="Video Tutorials"
                            description="Step-by-step video guides and tips"
                            onClick={() => window.open('https://youtube.com/elemacy', '_blank')}
                        />
                        <FeatureCard
                            icon={MessageCircle}
                            title="Community Support"
                            description="Join our community and get help"
                            onClick={() => window.open('https://community.elemacy.com', '_blank')}
                        />
                    </div>
                </div>
            </Container>
        </>
    );
};

export default Dashboard;