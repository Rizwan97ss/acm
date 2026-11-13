import { ChevronDownIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

/**
 * A styled native <select>. Native controls are fully accessible, work with
 * Inertia's <Form> serialisation and behave well on mobile.
 */
function NativeSelect({ className, children, ...props }: React.ComponentProps<"select">) {
  return (
    <div className={cn("relative", className)}>
      <select
        data-slot="native-select"
        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 aria-invalid:border-destructive dark:bg-input/30 h-9 w-full appearance-none rounded-md border bg-transparent py-1 pr-8 pl-3 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm [&>option]:bg-popover [&>option]:text-popover-foreground"
        {...props}
      >
        {children}
      </select>
      <ChevronDownIcon className="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden />
    </div>
  )
}

export { NativeSelect }
