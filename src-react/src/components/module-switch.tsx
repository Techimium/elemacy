import { __ } from "@wordpress/i18n";
import { useId } from "react"

import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { useToggleModuleMutation } from "@/services/module";

interface ModuleSwitchProps {
  isEnabled?: boolean
  name: string
}

export default function ModuleSwitch({ isEnabled, name }: ModuleSwitchProps) {
  const id = useId()
  const { mutateAsync: toggleModule } = useToggleModuleMutation();

  return (
    <div className="flex">
      <div className="relative inline-grid h-7 grid-cols-[1fr_1fr] items-center text-sm font-medium">
        <Switch
          id={id}
          defaultChecked={isEnabled}
          onCheckedChange={(value) => toggleModule({ isEnabled: value, name })}
          className="peer absolute inset-0 h-[inherit] w-auto rounded-md data-[state=unchecked]:bg-input/50 [&_span]:z-10 [&_span]:h-full [&_span]:w-1/2 [&_span]:rounded-sm [&_span]:transition-transform [&_span]:duration-300 [&_span]:ease-[cubic-bezier(0.16,1,0.3,1)] [&_span]:data-[state=checked]:translate-x-full [&_span]:data-[state=checked]:rtl:-translate-x-full"
        />
        <span className="pointer-events-none relative ms-0.5 flex items-center justify-center px-2 text-center transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] peer-data-[state=checked]:invisible peer-data-[state=unchecked]:translate-x-full peer-data-[state=unchecked]:rtl:-translate-x-full">
          <span className="text-[10px] font-medium uppercase">{__('Off', 'elemacy')}</span>
        </span>
        <span className="pointer-events-none relative me-0.5 flex items-center justify-center px-2 text-center transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] peer-data-[state=checked]:-translate-x-full peer-data-[state=checked]:text-background peer-data-[state=unchecked]:invisible peer-data-[state=checked]:rtl:translate-x-full">
          <span className="text-[10px] font-medium uppercase">{__('On', 'elemacy')}</span>
        </span>
      </div>
      <Label htmlFor={id} className="sr-only">
        {__('Toggle Module', 'elemacy')}
      </Label>
    </div>
  )
}
