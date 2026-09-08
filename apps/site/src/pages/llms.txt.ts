import type { APIRoute } from 'astro'
import { llmsTxt } from '../content/site'

export const GET: APIRoute = () =>
  new Response(`\uFEFF${llmsTxt}`, {
    headers: {
      'Content-Type': 'text/plain; charset=utf-8',
    },
  })
