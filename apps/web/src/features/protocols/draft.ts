import type { ProtocolFormValues, ProtocolItemDraft } from '@/features/protocols/schema'

const STORAGE_PREFIX = 'gestao-protocol-draft:'

export interface ProtocolDraft extends ProtocolFormValues {
  suggestedDirty: boolean
  minDirty: boolean
  items: ProtocolItemDraft[]
  addQuantity: string
}

function draftKey(protocolId?: number): string {
  return `${STORAGE_PREFIX}${protocolId ?? 'new'}`
}

export function saveProtocolDraft(protocolId: number | undefined, draft: ProtocolDraft): void {
  sessionStorage.setItem(draftKey(protocolId), JSON.stringify(draft))
}

export function loadProtocolDraft(protocolId: number | undefined): ProtocolDraft | null {
  const raw = sessionStorage.getItem(draftKey(protocolId))
  if (!raw) {
    return null
  }
  try {
    return JSON.parse(raw) as ProtocolDraft
  } catch {
    return null
  }
}

export function clearProtocolDraft(protocolId: number | undefined): void {
  sessionStorage.removeItem(draftKey(protocolId))
}
