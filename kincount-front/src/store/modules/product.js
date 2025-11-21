// src/store/modules/product.js
import { defineStore } from 'pinia'
import {
  getSkuList,
  getSkuDetail,
  getProductSkus,
  getSkuSelectList
} from '@/api/product'

export const useProductStore = defineStore('product', {
  state: () => ({
    skuList: [],          // 最后一次 SKU 列表
    total: 0,             // 总条数
    currentSku: {},       // 当前查看/编辑的 SKU
    productSkus: [],      // 某商品下的全部 SKU（规格矩阵）
    skuSelectOptions: []  // 下拉/搜索候选 SKU
  }),

  actions: {
    // 加载 SKU 分页列表（替代原商品列表）
    async loadSkuList(params) {
      const { list, total } = await getSkuList(params)
      this.skuList = list
      this.total = total
    },

    // 加载单个 SKU 详情（替代原商品详情）
    async loadSkuDetail(skuId) {
      this.currentSku = await getSkuDetail(skuId)
    },

    // 加载某商品下的全部 SKU（规格切换用）
    async loadProductSkus(productId) {
      this.productSkus = await getProductSkus(productId)
    },

    // SKU 选择器搜索（远程下拉）
    async searchSkuSelect(keyword, limit = 20) {
      const list = await getSkuSelectList({ keyword, limit })
      this.skuSelectOptions = list
    },

    // 清空当前 SKU
    resetCurrentSku() {
      this.currentSku = {}
    }
  }
})