import type { LucideProps } from 'lucide-react';
import type { ComponentType } from 'react';

interface FeatureCardProps {
    icon: ComponentType<LucideProps>;
    title: string;
    description: string;
    onClick?: () => void;
    gradient?: boolean;
}

const FeatureCard = ({ icon: Icon, title, description, onClick, gradient = false }: FeatureCardProps) => {
    const gradientClasses = gradient
        ? "bg-gradient-to-br from-indigo-600 to-purple-700 text-white shadow-xl shadow-indigo-500/50"
        : "bg-white text-gray-800 shadow-md border border-gray-100";

    const iconColor = gradient ? "text-white" : "text-indigo-600";
    const textColor = gradient ? "text-gray-100" : "text-gray-500";
    const titleColor = gradient ? "text-white" : "text-gray-800";

    return (
        <div
            onClick={onClick}
            className={`rounded-2xl p-6 cursor-pointer transition-all duration-300 ease-in-out hover:shadow-2xl hover:scale-[1.02] ${gradientClasses}`}
        >
            <div className={`p-3 rounded-full inline-flex items-center justify-center mb-4 ${gradient ? 'bg-white/20' : 'bg-indigo-50'}`}>
                <Icon className={`h-6 w-6 ${iconColor}`} />
            </div>
            <div className={`text-xl font-semibold mb-2 ${titleColor}`}>{title}</div>
            <p className={`text-sm ${textColor}`}>{description}</p>
        </div>
    );
};

export default FeatureCard;