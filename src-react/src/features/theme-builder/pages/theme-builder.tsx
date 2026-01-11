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
import { LayoutTemplateIcon, Loader2 } from "lucide-react";
import TemplateCard from "@/features/theme-builder/components/template-card";
import { useState } from "react";
import { useDeleteTemplateMutation, useTemplates } from "@/features/theme-builder/services/template";
import { EditTemplateModal } from "@/features/theme-builder/components/edit-template-modal";
import type { Template } from "@/features/theme-builder/schemas/template";
import { CreateTemplateModal } from "../components/create-template-modal";

function ThemeBuilder() {
  const [isEnabled, setIsEnabled] = useState(false);
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  const [editingTemplate, setEditingTemplate] = useState<Template | null>(null);
  const [isEditOpen, setIsEditOpen] = useState(false);

  const { data: templates, isLoading } = useTemplates();
  const { mutateAsync: deleteTemplate } = useDeleteTemplateMutation();

  const handleEdit = (template: Template) => {
    setEditingTemplate(template);
    setIsEditOpen(true);
  };

  const handleDelete = async (template: Template) => {
    deleteTemplate(template.id);
  };

  return (
    <>
      <Topbar />

      <Container className="space-y-6 mt-6">
        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6">
          <div>
            <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight">
              <span>Theme Builder</span>
              <ModuleSwitch
                checked={isEnabled}
                onCheckedChange={(isChecked) => setIsEnabled(isChecked)}
              />
            </div>
            <div className="text-gray-500 mt-1">
              Manage your site structure templates for a full theme experience.
            </div>
          </div>

          <Button
            size="lg"
            onClick={() => setIsCreateOpen(true)}
          >
            Create New Template
          </Button>
        </Card>

        <CreateTemplateModal
          open={isCreateOpen}
          onOpenChange={setIsCreateOpen}
        />

        <EditTemplateModal
          template={editingTemplate}
          open={isEditOpen}
          onOpenChange={setIsEditOpen}
        />

        {isLoading ? (
          <div className="flex justify-center items-center h-64">
            <Loader2 className="h-8 w-8 animate-spin text-gray-500" />
          </div>
        ) : (
          <>
            {templates && templates.length > 0 ? (
              <div
                className={`grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 ${!isEnabled ? "blur-xs" : ""
                  }`}
              >
                {templates.map((template) => (
                  <TemplateCard
                    key={template.id}
                    template={template}
                    onEdit={handleEdit}
                    onDelete={handleDelete}
                  />
                ))}
              </div>
            ) : (
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
                      <Button onClick={() => setIsCreateOpen(true)}>Create Template</Button>
                      <Button variant="outline">Import Template</Button>
                    </div>
                  </EmptyContent>
                </Empty>
              </Card>
            )}
          </>
        )}
      </Container>
    </>
  );
}

export default ThemeBuilder;
