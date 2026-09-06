import type { LucideProps } from 'lucide-react';
import type { ComponentType } from 'react';

interface FeatureCardProps {
    icon: ComponentType<LucideProps>;
    title: string;
    description: string;
    onClick?: () => void;
    gradient?: boolean;
    badge?: string;
}

const FeatureCard = ({ icon: Icon, title, description, onClick, gradient = false, badge }: FeatureCardProps) => {
    const surface = gradient
        ? "bg-gradient-to-br from-indigo-600 to-purple-700 text-white shadow-xl shadow-indigo-500/40"
        : "bg-white text-gray-800 shadow-sm border border-gray-100";

    const interactive = onClick ? "cursor-pointer hover:shadow-xl hover:-translate-y-1" : "";

    const iconColor = gradient ? "text-white" : "text-indigo-600";
    const descColor = gradient ? "text-indigo-50/90" : "text-gray-500";
    const titleColor = gradient ? "text-white" : "text-gray-900";

    return (
        <div
            onClick={onClick}
            className={`relative rounded-2xl p-6 transition-all duration-300 ease-out ${surface} ${interactive}`}
        >
            {badge && (
                <span className={`absolute top-4 right-4 text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full ${gradient ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600'}`}>
                    {badge}
                </span>
            )}
            <div className={`p-3 rounded-xl inline-flex items-center justify-center mb-4 ${gradient ? 'bg-white/20' : 'bg-indigo-50'}`}>
                <Icon className={`h-6 w-6 ${iconColor}`} />
            </div>
            <div className={`text-lg font-semibold mb-1.5 ${titleColor}`}>{title}</div>
            <p className={`text-sm leading-relaxed ${descColor}`}>{description}</p>
        </div>
    );
};

export default FeatureCard;
