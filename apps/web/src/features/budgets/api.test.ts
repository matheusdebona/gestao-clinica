import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/lib/api', () => ({
  api: {
    raw: vi.fn(),
  },
}))

import { api } from '@/lib/api'
import { blobFromFetchData, downloadDocument } from './api'

describe('blobFromFetchData', () => {
  it('returns an existing Blob without wrapping again', () => {
    const original = new Blob(['pdf-bytes'], { type: 'application/pdf' })
    expect(blobFromFetchData(original, 'application/pdf')).toBe(original)
  })

  it('builds a Blob from ArrayBuffer payload', () => {
    const bytes = new Uint8Array([37, 80, 68, 70]).buffer
    const blob = blobFromFetchData(bytes, 'application/pdf')
    expect(blob).toBeInstanceOf(Blob)
    expect(blob.type).toBe('application/pdf')
    expect(blob.size).toBe(4)
  })

  it('throws when ofetch left the body empty', () => {
    expect(() => blobFromFetchData(undefined)).toThrow('Empty download body')
  })
})

describe('downloadDocument', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
  })

  it('uses ofetch _data when it is already a Blob instead of Response.blob()', async () => {
    const pdf = new Blob(['%PDF'], { type: 'application/pdf' })
    const blob = vi.fn(() => {
      throw new Error('body stream already read')
    })
    vi.mocked(api.raw).mockResolvedValue({
      headers: { get: () => 'application/pdf' },
      _data: pdf,
      blob,
    } as never)

    const click = vi.fn()
    const link = { href: '', download: '', click }
    vi.stubGlobal('document', {
      createElement: () => link,
    })
    const createObjectURL = vi.fn(() => 'blob:pdf')
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL })

    await downloadDocument(12, 'orcamento.pdf')

    expect(api.raw).toHaveBeenCalledWith('/documents/12/download', {
      responseType: 'blob',
      headers: { Accept: 'application/pdf' },
    })
    expect(blob).not.toHaveBeenCalled()
    expect(createObjectURL).toHaveBeenCalledWith(pdf)
    expect(link.download).toBe('orcamento.pdf')
    expect(link.href).toBe('blob:pdf')
    expect(click).toHaveBeenCalledOnce()
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:pdf')
  })
})
