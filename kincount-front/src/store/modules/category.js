import { defineStore } from 'pinia'
import {
    getCategoryList,
    getCategoryTree,
    addCategory,
    updateCategory,
    deleteCategory,
    updateCategoryStatus
} from '@/api/category'

export const useCategoryStore = defineStore('category', {
    state: () => ({
        list: [],
        total: 0,
        keyword: '',
        tree: []
    }),

    actions: {
        async loadList(params = {}) {
            if (this.keyword) params.keyword = this.keyword
            const res = await getCategoryList(params)

            // 统一成 { list: [], total: number }
            if (Array.isArray(res)) return { list: res, total: res.length }
            if (res.list) return { list: res.list, total: res.total }
            if (res.data?.list) return { list: res.data.list, total: res.data.total }
            return { list: [], total: 0 }
        },

        async loadTree(keyword = '') {
            this.keyword = keyword
            const res = await getCategoryTree({ keyword })

            // 处理返回数据
            let treeData = []
            if (Array.isArray(res)) {
                treeData = res
            } else if (res && res.data) {
                treeData = res.data
            } else if (res && res.list) {
                treeData = res.list
            }

            // 添加层级信息，所有节点默认折叠
            const addLevelInfo = (nodes, level = 0) => {
                return nodes.map(node => ({
                    ...node,
                    _level: level,
                    _expanded: false, // 所有节点默认折叠
                    children: node.children ? addLevelInfo(node.children, level + 1) : []
                }))
            }

            this.tree = addLevelInfo(treeData)
            return this.tree
        },
        async toggleStatus(id, status) {
            await updateCategoryStatus(id, status)
        },

        async deleteRow(id) {
            await deleteCategory(id)
        }

        // 注意：这里移除了 buildTree 方法，因为 API 已经返回树形结构
    }
})