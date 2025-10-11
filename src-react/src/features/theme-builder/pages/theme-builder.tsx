import { Button } from "@/components/ui/button";
import Container from "@/components/container";
import { Card } from "@/components/ui/card";
import Topbar from "@/components/layout/topbar";
import ModuleSwitch from "@/components/module-switch";
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { LayoutTemplateIcon } from "lucide-react";
import TemplateCard from "../components/template-card";
import { useState } from "react";

function ThemeBuilder() {
  const [isEnabled, setIsEnabled] = useState(false);

  const templates: number[] = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
  return (
    <>
      <Topbar />

      <Container className="space-y-6 mt-6">
        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6">
          <div>
            {/* Title (replaced h1 with div) */}
            <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight">
              <span>Theme Builder</span>
              <ModuleSwitch
                checked={isEnabled}
                onCheckedChange={(isChecked) => setIsEnabled(isChecked)}
              />
            </div>
            {/* Subtitle (replaced p with div) */}
            <div className="text-gray-500 mt-1">
              Manage your site structure templates for a full theme experience.
            </div>
          </div>

          {/* Global Add Button */}
          <Button
            size="lg"
            onClick={() => console.log("Global Add New Template")}
          >
            Create New Template
          </Button>
        </Card>

        <div
          className={`grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 ${
            !isEnabled ? "blur-xs" : ""
          }`}
        >
          {templates.map((template) => (
            <TemplateCard key={template} />
          ))}
        </div>

        {templates.length === 0 && (
          <Card>
            <Empty>
              <EmptyHeader>
                <EmptyMedia className="w-16 h-16" variant="icon">
                  <LayoutTemplateIcon />
                </EmptyMedia>
                <EmptyTitle>No Templates Yet</EmptyTitle>
                <EmptyDescription>
                  You haven't created any templates yet. Get started by creating
                  your first template.
                </EmptyDescription>
              </EmptyHeader>
              <EmptyContent>
                <div className="flex gap-2">
                  <Button>Create Template</Button>
                  <Button variant="outline">Import Template</Button>
                </div>
              </EmptyContent>
            </Empty>
          </Card>
        )}
      </Container>
    </>
  );
}

export default ThemeBuilder;
