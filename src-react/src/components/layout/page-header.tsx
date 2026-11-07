import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";

interface PageHeaderProps {
  title: ReactNode;
  description?: ReactNode;
  /** Page-level actions (e.g. a "Create" button), shown opposite the title. */
  children?: ReactNode;
}

/** The title card every admin page opens with. */
function PageHeader({ title, description, children }: PageHeaderProps) {
  return (
    <Card className="flex flex-col sm:flex-row sm:justify-between sm:items-center px-6 border-gray-100 shadow-sm">
      <div>
        <div className="text-4xl font-extrabold text-gray-900 leading-tight tracking-tight">
          {title}
        </div>
        {description && <div className="text-gray-500 mt-2 max-w-2xl">{description}</div>}
      </div>
      {children}
    </Card>
  );
}

export default PageHeader;
