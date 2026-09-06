import { __ } from "@wordpress/i18n";
import Container from "@/components/container";
import { Card } from "@/components/ui/card";
import Topbar from "@/components/layout/topbar";
import { useState } from "react";

import { CreateBlockTemplateModal } from "../components/create-block-template-modal";
import BlockTemplateList from "../components/block-template-list";

function Library() {
  const [isCreateOpen, setIsCreateOpen] = useState(false);

  return (
    <>
      <Topbar />

      <Container className="space-y-6 mt-6 px-4">
        <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6">
          <div>
            <div className="flex items-center gap-5 text-4xl font-extrabold text-gray-900 leading-tight">
              <span>{__('Template Library', 'elemacy')}</span>
            </div>
            <div className="text-gray-500 mt-1">
              {__('Create, manage, and reuse templates across your site to design faster with Elementor.', 'elemacy')}
            </div>
          </div>

          <CreateBlockTemplateModal isOpen={isCreateOpen} onOpenChange={setIsCreateOpen} />
        </Card>

        <BlockTemplateList setIsOpen={setIsCreateOpen} />
      </Container>
    </>
  );
}

export default Library;
