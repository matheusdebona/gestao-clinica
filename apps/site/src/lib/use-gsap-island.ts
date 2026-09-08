import { onMounted, onUnmounted, type Ref } from 'vue'
import { getGsap } from './gsap'

type Context = ReturnType<ReturnType<typeof getGsap>['gsap']['context']>

type SetupArgs = {
  gsap: ReturnType<typeof getGsap>['gsap']
  reduceMotion: boolean
  isDesktop: boolean
  root: HTMLElement
}

/**
 * Vue island helper: register ScrollTrigger once; create tweens inside
 * gsap.context + matchMedia (prefers-reduced-motion); revert on unmount.
 */
export function useGsapIsland(root: Ref<HTMLElement | null>, setup: (args: SetupArgs) => void) {
  let ctx: Context | undefined

  onMounted(() => {
    const el = root.value
    if (!el) return

    const { gsap } = getGsap()
    ctx = gsap.context(() => {
      const mm = gsap.matchMedia()
      mm.add(
        {
          reduceMotion: '(prefers-reduced-motion: reduce)',
          motionOk: '(prefers-reduced-motion: no-preference)',
          isDesktop: '(min-width: 960px)',
        },
        (context) => {
          setup({
            gsap,
            reduceMotion: Boolean(context.conditions?.reduceMotion),
            isDesktop: Boolean(context.conditions?.isDesktop),
            root: el,
          })
        },
      )
    }, el)
  })

  onUnmounted(() => {
    ctx?.revert()
  })
}
