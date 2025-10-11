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

function TemplateCard() {
  return (
    <Card>
      <div className="px-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-1">
            <span className="h-2 w-2 rounded-full bg-green-500"></span>
            <span className="font-semibold text-gray-900">Header</span>
          </div>
          <DropdownMenu>
            <DropdownMenuTrigger><EllipsisIcon size={16} aria-hidden="true" /></DropdownMenuTrigger>
            <DropdownMenuContent>
              <DropdownMenuLabel>Actions</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem>Edit</DropdownMenuItem>
              <DropdownMenuItem>Duplicate</DropdownMenuItem>
              <DropdownMenuItem className="text-red-500">Delete</DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
        <div className="flex items-center justify-center w-full h-32 uppercase text-4xl font-bold text-gray-300 rounded bg-gray-100 my-4">
          H
        </div>
        <div className="text-xs text-gray-500">Modified 2 days ago</div>
      </div>
    </Card>
  );
}

export default TemplateCard;
