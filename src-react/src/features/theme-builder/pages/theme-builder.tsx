import Page from "@/components/layout/page";
import PageHeader from "@/components/layout/page-header";

import { CreateTemplateModal } from "../components/create-template-modal";
import { useModuleQuery } from "@/services/module";
import TemplateList from "../components/template-list";
import { useState } from "react";
import InactiveModule from "@/components/inactive-module";
import Spinner from "@/components/spinner";

function ThemeBuilder() {
  const { data: module, isLoading } = useModuleQuery({ name: 'theme-builder' });
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  return (
    <Page>

        {isLoading ? (
          <div className="flex justify-center items-center h-64">
            <Spinner />
          </div>
        ) : (
          <>
            <PageHeader title={module?.title} description={module?.description}>
              <CreateTemplateModal isOpen={isCreateOpen} isDisabled={!module?.is_active} onOpenChange={setIsCreateOpen} />
            </PageHeader>
            {module?.is_active ? <TemplateList setIsOpen={setIsCreateOpen} /> : <InactiveModule />}
          </>
        )}
    </Page>
  );
}

export default ThemeBuilder;
