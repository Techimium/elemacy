import * as Lucide from 'lucide-react';
import { type LucideProps } from 'lucide-react';

interface DynamicIconProps {
    name: string;
    className?: string;
}

function DynamicIcon({ name, className }: DynamicIconProps) {
    const iconName = name
        .split('-')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join('') as keyof typeof Lucide;

    const IconComponent = (Lucide[iconName] as React.FC<LucideProps>) || Lucide.HelpCircle;

    return (
        <IconComponent className={className || "h-8 w-8 text-gray-500"} />
    );
}

export default DynamicIcon;