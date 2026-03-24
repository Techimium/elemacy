import { Card } from "./ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "./ui/empty";
import { TextSearch } from "lucide-react";

interface NoDataProps {
    title?: string;
    description?: string;
    icon?: React.ReactNode;
}

function NoData({ title = "No Data", description = "No data found", icon = <TextSearch /> }: NoDataProps) {
    return (
        <Card>
            <Empty>
                <EmptyHeader>
                    <EmptyMedia className="w-16 h-16" variant="icon" >
                        {icon}
                    </EmptyMedia>
                    <EmptyTitle> {title} </EmptyTitle>
                    <EmptyDescription>
                        {description}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>
    )
};

export default NoData;
