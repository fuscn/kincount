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

        async loadTree() {
            this.tree = await getCategoryTree()
            return this.tree
        },

        async toggleStatus(id, status) {
            await updateCategoryStatus(id, status)
        },

        async deleteRow(id) {
            await deleteCategory(id)
        }
    }
})