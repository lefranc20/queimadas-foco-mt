<script setup>
import { ref } from 'vue';
import { numero } from '../lib/formato.js';
import Grafico from './Grafico.vue';

/**
 * Cartão com título, legenda e alternância entre gráfico e tabela
 * (a tabela é a forma acessível de ler todos os valores).
 */
defineProps({
    titulo: { type: String, required: true },
    subtitulo: { type: String, default: '' },
    tipo: { type: String, default: 'line' },
    rotulos: { type: Array, default: () => [] },
    series: { type: Array, default: () => [] },
    formatarEixo: { type: Function, default: (rotulo) => rotulo },
    formatarTooltip: { type: Function, default: (rotulo) => rotulo },
    formatarValor: { type: Function, default: numero },
    altura: { type: String, default: '16rem' },
    carregando: { type: Boolean, default: false },
    vazio: { type: String, default: '' },
});

const visao = ref('grafico');
</script>

<template>
    <section class="cartao p-4" :aria-label="titulo">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold">{{ titulo }}</h3>
                <p v-if="subtitulo" class="text-xs text-tinta-2">{{ subtitulo }}</p>
            </div>
            <div class="flex items-center gap-3">
                <ul v-if="series.length > 1" class="flex flex-wrap gap-3 text-xs text-tinta-2" aria-label="Legenda">
                    <li v-for="serie in series" :key="serie.nome" class="flex items-center gap-1.5">
                        <span class="h-0.5 w-3.5 rounded-full" :style="{ background: `var(--${serie.cor})` }"></span>
                        {{ serie.nome }}
                    </li>
                </ul>
                <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Forma de exibição">
                    <button type="button" class="botao-aba" :aria-pressed="visao === 'grafico'" @click="visao = 'grafico'">Gráfico</button>
                    <button type="button" class="botao-aba" :aria-pressed="visao === 'tabela'" @click="visao = 'tabela'">Tabela</button>
                </div>
            </div>
        </div>

        <div :class="{ 'opacity-50 transition-opacity': carregando }">
            <p v-if="vazio" class="grid place-items-center text-sm text-tinta-2" :style="{ height: altura }">{{ vazio }}</p>

            <Grafico
                v-else-if="visao === 'grafico'"
                :tipo="tipo"
                :rotulos="rotulos"
                :series="series"
                :formatar-eixo="formatarEixo"
                :formatar-tooltip="formatarTooltip"
                :formatar-valor="formatarValor"
                :altura="altura"
            />

            <div v-else class="overflow-auto" :style="{ maxHeight: altura }">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 bg-superficie text-left text-xs text-tinta-2">
                        <tr>
                            <th class="py-1.5 pr-4 font-medium">Período</th>
                            <th v-for="serie in series" :key="serie.nome" class="py-1.5 pr-4 text-right font-medium">{{ serie.nome }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(rotulo, indice) in rotulos" :key="rotulo" class="border-t border-grade">
                            <td class="py-1 pr-4">{{ formatarTooltip(rotulo) }}</td>
                            <td v-for="serie in series" :key="serie.nome" class="py-1 pr-4 text-right">
                                {{ serie.valores[indice] === null ? '–' : formatarValor(serie.valores[indice]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
