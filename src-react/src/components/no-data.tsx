import { __ } from "@wordpress/i18n";
import { Card } from "./ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "./ui/empty";
import { TextSearch } from "lucide-react";

interface NoDataProps {
    title?: string;
    description?: string;
    icon?: React.ReactNode;
}

function NoData({ title, description, icon = <TextSearch /> }: NoDataProps) {
    const displayTitle = title || __("No Data", "elemacy");
    const displayDescription = description || __("No data found", "elemacy");

    return (
        <Card>
            <Empty>
                <EmptyHeader>
                    <EmptyMedia className="w-16 h-16" variant="icon" >
                        {icon}
                    </EmptyMedia>
                    <EmptyTitle> {displayTitle} </EmptyTitle>
                    <EmptyDescription>
                        {displayDescription}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>
    )
};

export default NoData;
