import { __ } from "@wordpress/i18n";
import { Link } from "react-router";
import { Card } from "./ui/card";
import { Empty, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "./ui/empty";
import { PowerOff } from "lucide-react";

function InactiveModule() {
    return (
        <Card>
            <Empty>
                <EmptyHeader>
                    <EmptyMedia className="w-16 h-16" variant="icon" >
                        <PowerOff />
                    </EmptyMedia>
                    <EmptyTitle> {__('Module is inactive', 'elemacy')} </EmptyTitle>
                    <EmptyDescription>
                        {__('Module is inactive. Get started by enabling the module from the ', 'elemacy')}
                        <Link to="/modules" className="font-bold">{__('Modules page', 'elemacy')}</Link>
                        {'.'}
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        </Card>
    )
};

export default InactiveModule;
