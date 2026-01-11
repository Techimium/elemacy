import { Card } from "./ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "./ui/empty";
import { LayoutTemplateIcon } from "lucide-react";

function InactiveModule() {
    return (
        <Card>
            <Empty>
                <EmptyHeader>
                    <EmptyMedia className="w-16 h-16" variant="icon" >
                        <LayoutTemplateIcon />
                    </EmptyMedia>
                    < EmptyTitle > Module is inactive </EmptyTitle>
                    <EmptyDescription>
                        Module is inactive. Get started by enabling
                        the module.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>
    )
};

export default InactiveModule;
