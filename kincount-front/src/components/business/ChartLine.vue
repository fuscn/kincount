<!-- src/components/business/ChartLine.vue -->
<template>
  <div ref="chartRef" style="width: 100%; height: 240px"></div>
</template>

<script setup>
import { onMounted, watch, ref, nextTick } from 'vue'
import * as echarts from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

echarts.use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = defineProps({
  xData: Array,
  series: Array
})

const chartRef = ref(null)
let chart = null

function render() {
  if (!chartRef.value) return
  
  if (!chart) {
    chart = echarts.init(chartRef.value)
  }
  
  const validXData = props.xData && Array.isArray(props.xData) ? props.xData : []
  const validSeries = props.series && Array.isArray(props.series) ? props.series : []
  
  // 检查是否所有数据都是0
  const isAllZeroData = validSeries.length > 0 && 
                       validSeries[0].data && 
                       validSeries[0].data.every(val => val === 0)
  
  const option = {
    tooltip: {
      trigger: 'axis',
      formatter: function (params) {
        return `${params[0].axisValue}<br/>${params[0].marker} ${params[0].seriesName}: ¥${params[0].data}`
      }
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '3%',
      top: '10%',
      containLabel: true
    },
    xAxis: {
      type: 'category',
      data: validXData,
      axisLabel: {
        rotate: 45
      }
    },
    yAxis: {
      type: 'value',
      min: isAllZeroData ? 0 : 'dataMin', // 如果全为0，固定y轴从0开始
      axisLabel: {
        formatter: '¥{value}'
      }
    },
    series: validSeries.map(s => ({
      type: 'line',
      smooth: true,
      symbol: 'circle',
      symbolSize: 6,
      itemStyle: {
        color: '#1989fa'
      },
      lineStyle: {
        color: '#1989fa',
        width: 2
      },
      ...s
    }))
  }
  
  chart.setOption(option, true)
}

function initChart() {
  nextTick(() => {
    render()
  })
}

function resizeChart() {
  if (chart) {
    chart.resize()
  }
}

onMounted(() => {
  initChart()
  window.addEventListener('resize', resizeChart)
})

watch(() => [props.xData, props.series], () => {
  initChart()
}, { deep: true })
</script>