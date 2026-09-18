import * as React from 'react'
import * as ReactDOM from 'react-dom/client'
import * as ElemacyComponents from '@/components/ui'
import { toast } from 'sonner'
import { registry } from '@/lib/registry'
import { routeRegistry } from '@/lib/route-registry'
import type { ElemacyBridgeCapability } from '@/types/elemacy'

/** Every capability this build of the bridge provides. A consumer should
 * feature-detect against this array rather than branching on `version`
 * alone, so a future release can add or drop one capability without
 * forcing every consumer to key off a major version bump. */
const CAPABILITIES: readonly ElemacyBridgeCapability[] = Object.freeze(['registry', 'routes', 'components', 'toast'])

/** The name a consumer that starts before the bridge exists listens for
 * (design.md D12: Pro must wait for a ready event/promise, or consume an
 * already-ready bridge, before dereferencing runtime values). */
export const ELEMACY_ADMIN_READY_EVENT = 'elemacy:admin-ready'

/**
 * Publishes window.ElemacyShared and announces it. PHP's own
 * AdminScripts::CONTRACT_VERSION (localized as window.elemacy.contract_version)
 * is this version's single source of truth — read here rather than kept as
 * a second, independently-edited copy in JS, so the two can never drift.
 */
export function exposeElemacyGlobals(): void {
  window.ElemacyShared = Object.freeze({
    version: window.elemacy.contract_version,
    capabilities: CAPABILITIES,
    React,
    ReactDOM,
    components: ElemacyComponents,
    toast,
    registry,
    routes: routeRegistry,
  })

  window.dispatchEvent(
    new CustomEvent(ELEMACY_ADMIN_READY_EVENT, {
      detail: { version: window.ElemacyShared.version, capabilities: CAPABILITIES },
    })
  )
}
