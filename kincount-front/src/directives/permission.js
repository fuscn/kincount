import { useAuthStore } from '@/store/modules/auth'

export const permission = {
  mounted(el, binding) {
    const { value } = binding
    const authStore = useAuthStore()
    
    if (value && !authStore.hasPerm(value)) {
      el.parentNode && el.parentNode.removeChild(el)
    }
  },
  
  updated(el, binding) {
    const { value } = binding
    const authStore = useAuthStore()
    
    if (value && !authStore.hasPerm(value)) {
      el.style.display = 'none'
    } else {
      el.style.display = ''
    }
  }
}