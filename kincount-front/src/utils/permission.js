// src/utils/permission.js
import { useAuthStore } from '@/store/modules/auth'

export const permission = {
  mounted(el, binding) {
    const auth = useAuthStore()
    const { value } = binding
    
    
    // 开发环境可以保留日志，但进行权限检查
    if (value && !auth.hasPerm(value)) {
      el.parentNode && el.parentNode.removeChild(el)
    }
  }
}