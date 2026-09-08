import { describe, expect, it } from 'vitest'
import { toCardOperatorPayload } from './schema'

describe('toCardOperatorPayload', () => {
  it('sends empty code as null', () => {
    expect(
      toCardOperatorPayload({
        name: ' Stone ',
        code: '  ',
        auto_anticipate: true,
        is_active: true,
      }),
    ).toEqual({
      name: 'Stone',
      code: null,
      auto_anticipate: true,
      is_active: true,
    })
  })
})
