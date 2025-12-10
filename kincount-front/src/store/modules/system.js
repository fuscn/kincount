// src/store/modules/system.js
import { defineStore } from 'pinia'
import { 
  getRoleList, 
  getRoleDetail, 
  addRole, 
  updateRole, 
  deleteRole,
  getRoleOptions,
  getRolePermissions,
  getSystemConfigs,
  saveSystemConfigs,
  getSystemConfigGroup,
  getSystemInfo,
  getSystemLogs,
  clearSystemLogs
} from '@/api/system'

export const useSystemStore = defineStore('system', {
  state: () => ({
    // 角色管理相关状态
    roleList: [],
    roleTotal: 0,
    currentRole: null,
    roleOptions: [], // 角色下拉选项
    permissions: [], // 权限列表（用于角色表单）
    
    // 系统配置相关状态
    systemConfigs: {}, // 所有配置
    configGroups: {},  // 按分组存储的配置
    systemInfo: {},    // 系统信息
    systemLogs: [],    // 系统日志
    logsTotal: 0,
    
    // 加载状态
    loading: {
      roles: false,
      roleDetail: false,
      permissions: false,
      configs: false,
      logs: false
    }
  }),

  getters: {
    // 角色列表
    roles: (state) => state.roleList,
    
    // 角色总数
    totalRoles: (state) => state.roleTotal,
    
    // 加载状态
    isLoading: (state) => (key) => state.loading[key] || false,
    
    // 获取角色选项（格式化后的下拉选项）
    formattedRoleOptions: (state) => {
      return state.roleOptions.map(role => ({
        label: role.name,
        value: role.id,
        disabled: role.status === 0 // 禁用的角色不可选
      }))
    },
    
    // 获取系统配置值
    configValue: (state) => (key) => {
      return state.systemConfigs[key]?.config_value || ''
    },
    
    // 按分组获取配置
    configsByGroup: (state) => (group) => {
      return state.configGroups[group] || []
    }
  },

  actions: {
    /* ========================================
     * 角色管理相关 Actions
     * ======================================== */
    
    // 加载角色列表
    async loadRoleList(params = {}) {
      try {
        this.loading.roles = true
        
        const { page = 1, limit = 15, keyword = '' } = params
        
        const res = await getRoleList({
          page,
          limit,
          keyword
        })
        
        // 根据你的API响应结构调整
        const data = res.data || res
        this.roleList = data.list || []
        this.roleTotal = data.total || 0
        
        return {
          list: this.roleList,
          total: this.roleTotal,
          page: data.page || page,
          pageSize: data.page_size || limit
        }
      } catch (error) {
        console.error('加载角色列表失败:', error)
        throw error
      } finally {
        this.loading.roles = false
      }
    },

    // 加载角色详情
    async loadRoleDetail(id) {
      try {
        this.loading.roleDetail = true
        
        const res = await getRoleDetail(id)
        const data = res.data || res
        
        this.currentRole = data
        
        // 转换permissions格式（如果是对象转数组）
        if (data.permissions && typeof data.permissions === 'object') {
          data.permissions = Object.values(data.permissions)
        }
        
        return data
      } catch (error) {
        console.error('加载角色详情失败:', error)
        throw error
      } finally {
        this.loading.roleDetail = false
      }
    },

    // 新增角色
    async createRole(roleData) {
      try {
        const res = await addRole(roleData)
        return res.data || res
      } catch (error) {
        console.error('创建角色失败:', error)
        throw error
      }
    },

    // 更新角色
    async updateRole(id, roleData) {
      try {
        const res = await updateRole(id, roleData)
        
        // 如果更新的是当前角色，更新store中的currentRole
        if (this.currentRole && this.currentRole.id === id) {
          this.currentRole = { ...this.currentRole, ...roleData }
        }
        
        return res.data || res
      } catch (error) {
        console.error('更新角色失败:', error)
        throw error
      }
    },

    // 删除角色
    async removeRole(id) {
      try {
        const res = await deleteRole(id)
        
        // 从列表中移除
        this.roleList = this.roleList.filter(role => role.id !== id)
        this.roleTotal = Math.max(0, this.roleTotal - 1)
        
        return res.data || res
      } catch (error) {
        console.error('删除角色失败:', error)
        throw error
      }
    },

    // 获取角色下拉选项
    async loadRoleOptions() {
      try {
        const res = await getRoleOptions()
        const data = res.data || res
        
        this.roleOptions = Array.isArray(data) ? data : []
        return this.roleOptions
      } catch (error) {
        console.error('加载角色选项失败:', error)
        this.roleOptions = []
        throw error
      }
    },

    // 获取权限列表（用于角色表单）
    async loadPermissions() {
      try {
        this.loading.permissions = true
        
        const res = await getRolePermissions()
        const data = res.data || res || []
        
        // 格式化权限数据
        this.permissions = Array.isArray(data) ? data : []
        
        return this.permissions
      } catch (error) {
        console.error('加载权限列表失败:', error)
        this.permissions = []
        throw error
      } finally {
        this.loading.permissions = false
      }
    },

    // 设置当前角色（用于编辑）
    setCurrentRole(role) {
      this.currentRole = role
    },

    // 清除当前角色
    clearCurrentRole() {
      this.currentRole = null
    },

    /* ========================================
     * 系统配置相关 Actions
     * ======================================== */
    
    // 加载所有系统配置
    async loadSystemConfigs() {
      try {
        this.loading.configs = true
        
        const res = await getSystemConfigs()
        const data = res.data || res || []
        
        // 转换为键值对格式
        const configMap = {}
        const groupedConfigs = {}
        
        data.forEach(config => {
          configMap[config.config_key] = config
          
          const group = config.config_group || 'default'
          if (!groupedConfigs[group]) {
            groupedConfigs[group] = []
          }
          groupedConfigs[group].push(config)
        })
        
        this.systemConfigs = configMap
        this.configGroups = groupedConfigs
        
        return {
          all: this.systemConfigs,
          groups: this.configGroups
        }
      } catch (error) {
        console.error('加载系统配置失败:', error)
        throw error
      } finally {
        this.loading.configs = false
      }
    },

    // 保存系统配置
    async saveConfigs(configData) {
      try {
        const res = await saveSystemConfigs(configData)
        
        // 保存成功后重新加载配置
        await this.loadSystemConfigs()
        
        return res.data || res
      } catch (error) {
        console.error('保存系统配置失败:', error)
        throw error
      }
    },

    // 加载分组配置
    async loadConfigGroup(group) {
      try {
        const res = await getSystemConfigGroup(group)
        const data = res.data || res || []
        
        // 更新分组配置
        this.configGroups[group] = data
        
        // 同时更新到总配置中
        data.forEach(config => {
          this.systemConfigs[config.config_key] = config
        })
        
        return data
      } catch (error) {
        console.error(`加载${group}分组配置失败:`, error)
        throw error
      }
    },

    // 更新单个配置值（本地缓存）
    updateConfigValue(key, value) {
      if (this.systemConfigs[key]) {
        this.systemConfigs[key].config_value = value
      }
    },

    // 获取单个配置
    getConfig(key) {
      return this.systemConfigs[key] || null
    },

    /* ========================================
     * 系统信息相关 Actions
     * ======================================== */
    
    // 加载系统信息
    async loadSystemInfo() {
      try {
        const res = await getSystemInfo()
        const data = res.data || res || {}
        
        this.systemInfo = data
        return data
      } catch (error) {
        console.error('加载系统信息失败:', error)
        throw error
      }
    },

    // 加载系统日志
    async loadSystemLogs(params = {}) {
      try {
        this.loading.logs = true
        
        const { page = 1, limit = 20 } = params
        
        const res = await getSystemLogs({
          page,
          limit
        })
        
        const data = res.data || res
        this.systemLogs = data.list || []
        this.logsTotal = data.total || 0
        
        return {
          list: this.systemLogs,
          total: this.logsTotal
        }
      } catch (error) {
        console.error('加载系统日志失败:', error)
        throw error
      } finally {
        this.loading.logs = false
      }
    },

    // 清空系统日志
    async clearLogs() {
      try {
        await clearSystemLogs()
        
        // 清空本地日志
        this.systemLogs = []
        this.logsTotal = 0
        
        return true
      } catch (error) {
        console.error('清空系统日志失败:', error)
        throw error
      }
    },

    /* ========================================
     * 辅助方法
     * ======================================== */
    
    // 重置store状态
    reset() {
      this.roleList = []
      this.roleTotal = 0
      this.currentRole = null
      this.roleOptions = []
      this.permissions = []
      
      this.systemConfigs = {}
      this.configGroups = {}
      this.systemInfo = {}
      this.systemLogs = []
      this.logsTotal = 0
    },

    // 初始化store
    async initialize() {
      try {
        // 并行加载常用数据
        await Promise.all([
          this.loadRoleOptions(),
          this.loadSystemConfigs(),
          this.loadSystemInfo()
        ])
      } catch (error) {
        console.error('初始化系统store失败:', error)
        // 不抛出错误，让应用继续运行
      }
    }
  },

  // 持久化配置（可选）
  persist: {
    enabled: true,
    strategies: [
      {
        key: 'system-store',
        storage: localStorage,
        // 只持久化系统配置，不持久化动态数据
        paths: ['systemConfigs', 'configGroups', 'systemInfo']
      }
    ]
  }
})