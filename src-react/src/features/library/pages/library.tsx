import { __ } from "@wordpress/i18n";
import Page from "@/components/layout/page";
import PageHeader from "@/components/layout/page-header";
import { useState } from "react";

import { CreateBlockTemplateModal } from "../components/create-block-template-modal";
import BlockTemplateList from "../components/block-template-list";

function Library() {
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  return (
    <Page>
      <PageHeader
        title={__('Template Library', 'elemacy')}
        description={__('Create, manage, and reuse templates across your site to design faster with Elementor.', 'elemacy')}
      >
        <CreateBlockTemplateModal isOpen={isCreateOpen} onOpenChange={setIsCreateOpen} />
      </PageHeader>

      <BlockTemplateList setIsOpen={setIsCreateOpen} />
    </Page>
  );
}

export default Library;
