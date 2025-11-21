<template>
  <div ref="chartRef" style="width: 100%; height: 240px"></div>
</template>

<script setup>
import { onMounted, watch, ref } from 'vue'
import * as echarts from 'echarts/core'
import { PieChart } from 'echarts/charts'
import { TooltipComponent, LegendComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([PieChart, TooltipComponent, LegendComponent, CanvasRenderer])

const props = defineProps({
  data: Array // [{ value: 335, name: '直接访问' }, ...]
})

const chartRef = ref(null)
let chart = null

function render() {
  if (!chart) chart = echarts.init(chartRef.value)
  chart.setOption({
    tooltip: { trigger: 'item' },
    legend: { bottom: 0 },
    series: [
      {
        type: 'pie',
        radius: ['40%', '70%'],
        avoidLabelOverlap: false,
        itemStyle: { borderRadius: 10, borderColor: '#fff', borderWidth: 2 },
        label: { show: false, position: 'center' },
        emphasis: { label: { show: true, fontSize: '20', fontWeight: 'bold' } },
        labelLine: { show: false },
        data: props.data
      }
    ]
  })
}

onMounted(render)
watch(() => props.data, render)
</script>