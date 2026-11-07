import type { ReactNode } from "react";
import Container from "@/components/container";
import Topbar from "@/components/layout/topbar";

/** The frame every admin page sits in: the top bar plus the standard content width and spacing. */
function Page({ children }: { children: ReactNode }) {
  return (
    <>
      <Topbar />
      <Container className="space-y-6 mt-6 px-4">{children}</Container>
    </>
  );
}

export default Page;
