import { Lock } from 'lucide-react';
import { __ } from '@wordpress/i18n';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';

interface ProFeaturePlaceholderProps {
    title: string;
    description: string;
    upgradeUrl?: string;
}

export function ProFeaturePlaceholder({
    title,
    description,
    upgradeUrl = 'https://elemacy.com/pro',
}: ProFeaturePlaceholderProps) {
    return (
        <Empty className="py-5">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Lock />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            <EmptyContent>
                <Button size="sm" asChild>
                    <a href={upgradeUrl} target="_blank" rel="noopener noreferrer">
                        {__('Upgrade to Pro', 'elemacy')}
                    </a>
                </Button>
            </EmptyContent>
        </Empty>
    );
}
