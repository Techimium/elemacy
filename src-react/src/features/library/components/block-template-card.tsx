import { __ } from "@wordpress/i18n";
import { Card } from "@/components/ui/card";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { EllipsisIcon } from "lucide-react";
import type { BlockTemplate } from "@/features/library/schemas/block-template";
import { useBlockTemplateTypes } from "@/features/library/services/block-template";

interface BlockTemplateCardProps {
  template: BlockTemplate;
  onEdit: (template: BlockTemplate) => void;
  onDelete: (template: BlockTemplate) => void;
  onDuplicate: (template: BlockTemplate) => void;
  onEditWithElementor: (template: BlockTemplate) => void;
}

function BlockTemplateCard({ template, onEdit, onDelete, onDuplicate, onEditWithElementor }: BlockTemplateCardProps) {
  const { data: templateTypes = [] } = useBlockTemplateTypes();
  return (
    <Card>
      <div className="px-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1">
            <span className={`h-2 w-2 rounded-full ${template.status === 'publish' ? 'bg-green-500' : 'bg-gray-400'}`}></span>
            <span className="font-semibold text-gray-900">{template.title}</span>
          </div>
          <DropdownMenu>
            <DropdownMenuTrigger><EllipsisIcon size={16} aria-hidden="true" /></DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuLabel>{__('Actions', 'elemacy')}</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => onEdit(template)}>{__('Edit', 'elemacy')}</DropdownMenuItem>
              <DropdownMenuItem onClick={() => onEditWithElementor(template)}>{__('Edit with Elementor', 'elemacy')}</DropdownMenuItem>
              <DropdownMenuItem onClick={() => onDuplicate(template)}>{__('Duplicate', 'elemacy')}</DropdownMenuItem>
              <DropdownMenuItem className="text-red-500" onClick={() => onDelete(template)}>{__('Delete', 'elemacy')}</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <div className="flex items-center justify-center w-full h-32 uppercase text-4xl font-bold text-gray-300 rounded bg-gray-100 my-4">
          {template.type ? template.type.charAt(0) : 'T'}
        </div>
        <div className="text-xs text-gray-500">
          {templateTypes.find((t) => t.value === template.type)?.label || template.type}
        </div>
      </div>
    </Card>
  );
}

export default BlockTemplateCard;
