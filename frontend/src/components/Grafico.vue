<script setup>
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { comTransparencia, corDoTema, esquemaCor } from '../composables/useEsquemaCor.js';
import { numero } from '../lib/formato.js';

Chart.register(LineController, LineElement, PointElement, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Filler);

const props = defineProps({
    tipo: { type: String, default: 'line' }, // 'line' | 'bar'
    rotulos: { type: Array, required: true },
    // [{ nome, valores, cor: token de app.css, area?: boolean, rotulo?: nome curto para o fim da linha }]
    series: { type: Array, required: true },
    formatarEixo: { type: Function, default: (rotulo) => rotulo },
    formatarTooltip: { type: Function, default: (rotulo) => rotulo },
    formatarValor: { type: Function, default: numero },
    altura: { type: String, default: '16rem' },
});

const canvas = ref(null);
let grafico = null;

/** Linha vertical que acompanha o cursor (crosshair). */
const linhaGuia = {
    id: 'linhaGuia',
    afterDatasetsDraw(chart) {
        const ativo = chart.tooltip?.getActiveElements()?.[0];
        if (!ativo || chart.config.type !== 'line') return;

        const { ctx, chartArea } = chart;
        ctx.save();
        ctx.strokeStyle = corDoTema('eixo');
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(ativo.element.x, chartArea.top);
        ctx.lineTo(ativo.element.x, chartArea.bottom);
        ctx.stroke();
        ctx.restore();
    },
};

/** Rótulo direto no fim de cada linha (só quando há mais de uma série). */
const rotuloFinal = {
    id: 'rotuloFinal',
    afterDatasetsDraw(chart) {
        if (chart.config.type !== 'line' || chart.data.datasets.length < 2) return;

        const { ctx } = chart;
        ctx.save();
        ctx.font = '12px system-ui, sans-serif';
        ctx.fillStyle = corDoTema('tinta-2');
        ctx.textBaseline = 'middle';

        const ocupados = [];
        chart.data.datasets.forEach((serie, indice) => {
            const pontos = chart.getDatasetMeta(indice).data;
            const ultimo = pontos[pontos.length - 1];
            if (!ultimo) return;

            // dentro da área do gráfico e sem sobrepor rótulos de séries que terminam perto
            let y = Math.min(ultimo.y, chart.chartArea.bottom - 7);
            while (ocupados.some((outro) => Math.abs(outro - y) < 14)) y -= 14;
            ocupados.push(y);

            ctx.fillText(props.series[indice].rotulo ?? serie.label, ultimo.x + 6, y);
        });
        ctx.restore();
    },
};

function configuracao() {
    const tinta2 = corDoTema('tinta-2');
    const grade = corDoTema('grade');
    const linha = props.tipo === 'line';
    const comRotulos = linha && props.series.length > 1;

    return {
        type: props.tipo,
        data: {
            labels: props.rotulos,
            datasets: props.series.map((serie) => {
                const cor = corDoTema(serie.cor);
                return {
                    label: serie.nome,
                    data: serie.valores,
                    borderColor: cor,
                    backgroundColor: linha ? comTransparencia(cor, 0.12) : cor,
                    fill: linha && serie.area ? 'origin' : false,
                    borderWidth: linha ? 2 : 0,
                    borderRadius: linha ? 0 : 4,
                    borderSkipped: 'start',
                    maxBarThickness: 28,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointHoverBorderWidth: 2,
                    pointHoverBorderColor: corDoTema('superficie'),
                    pointHoverBackgroundColor: cor,
                    cubicInterpolationMode: 'monotone',
                    spanGaps: true,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { right: comRotulos ? 56 : 4, top: 6 } },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: corDoTema('eixo') },
                    ticks: {
                        color: tinta2,
                        maxRotation: 0,
                        autoSkipPadding: 16,
                        callback(valor) {
                            return props.formatarEixo(this.getLabelForValue(valor));
                        },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: grade },
                    border: { display: false },
                    ticks: { color: tinta2, maxTicksLimit: 6, callback: (valor) => props.formatarValor(valor) },
                },
            },
            plugins: {
                tooltip: {
                    backgroundColor: corDoTema('superficie'),
                    titleColor: corDoTema('tinta'),
                    bodyColor: tinta2,
                    borderColor: corDoTema('eixo'),
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    usePointStyle: true,
                    callbacks: {
                        title: (itens) => props.formatarTooltip(itens[0].label),
                        label: (item) => ` ${item.dataset.label}: ${item.raw === null ? '–' : props.formatarValor(item.raw)}`,
                    },
                },
            },
        },
        plugins: [linhaGuia, rotuloFinal],
    };
}

function desenhar() {
    grafico?.destroy();
    grafico = new Chart(canvas.value, configuracao());
}

onMounted(desenhar);
onBeforeUnmount(() => grafico?.destroy());
watch(() => [props.tipo, props.rotulos, props.series, esquemaCor.value], desenhar);
</script>

<template>
    <div class="relative" :style="{ height: altura }">
        <canvas ref="canvas" role="img" :aria-label="series.map((s) => s.nome).join(', ')"></canvas>
    </div>
</template>
