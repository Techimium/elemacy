import { __ } from "@wordpress/i18n";
import {
    ChevronUp,
    ChevronDown,
    type LucideProps
} from 'lucide-react';
import type { ComponentType } from 'react';

interface StatCardProps {
    icon: ComponentType<LucideProps>;
    label: string;
    value: string;
    trend: {
        value: string;
        isPositive: boolean;
    };
}

const StatCard = ({ icon: Icon, label, value, trend }: StatCardProps) => {
    const TrendIcon = trend.isPositive ? ChevronUp : ChevronDown;
    const trendColor = trend.isPositive ? 'text-green-500' : 'text-red-500';

    return (
        <div className="flex flex-col space-y-2 rounded-xl bg-white p-6 shadow-xl border border-gray-100 transition-transform hover:scale-[1.02]">
            <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-gray-500">{label}</p>
                <Icon className="h-5 w-5 text-indigo-500" />
            </div>
            <div className="text-3xl font-bold text-gray-800">{value}</div>
            <div className="flex items-center text-sm">
                <TrendIcon className={`h-4 w-4 mr-1 ${trendColor}`} />
                <span className={`${trendColor} font-semibold`}>{trend.value}</span>
                <span className="text-gray-500 ml-1">{__('vs last month', 'elemacy')}</span>
            </div>
        </div>
    );
};

export default StatCard