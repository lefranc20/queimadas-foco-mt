<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { decimal, numero } from '../lib/formato.js';

const LIMITE_INICIAL = 10;

const { parametros } = useFiltros();
const rota = useRoute();
const todos = ref(false);

const { dados, carregando, erro } = useConsulta('municipios/ranking', () => ({
    ...parametros.value,
    limite: todos.value ? 200 : LIMITE_INICIAL,
}));

const maximo = computed(() => dados.value?.municipios[0]?.total || 1);
</script>

<template>
    <section class="cartao flex flex-col p-4" aria-labelledby="titulo-ranking">
        <div class="mb-3">
            <h3 id="titulo-ranking" class="text-sm font-semibold">Municípios com mais focos</h3>
            <p class="text-xs text-tinta-2">
                <template v-if="dados">{{ numero(dados.total) }} focos no período · % do total do estado</template>
                <template v-else>Carregando…</template>
            </p>
        </div>

        <p v-if="erro" class="text-sm text-status-alta">{{ erro }}</p>
        <p v-else-if="dados && !dados.municipios.length" class="py-8 text-center text-sm text-tinta-2">Nenhum foco no período.</p>

        <ol v-else class="space-y-2.5 overflow-y-auto pr-1" :class="{ 'opacity-50 transition-opacity': carregando }">
            <li v-for="(municipio, indice) in dados?.municipios ?? []" :key="municipio.id">
                <div class="flex items-baseline justify-between gap-2 text-sm">
                    <RouterLink
                        :to="{ name: 'municipio', params: { id: municipio.id }, query: rota.query }"
                        class="truncate hover:underline"
                    >
                        <span class="mr-1.5 inline-block w-5 text-right text-xs text-tinta-suave tabular-nums">{{ indice + 1 }}</span>
                        {{ municipio.nome }}
                    </RouterLink>
                    <span class="shrink-0 tabular-nums">
                        {{ numero(municipio.total) }}
                        <span class="text-xs text-tinta-2">({{ decimal(municipio.percentual) }}%)</span>
                    </span>
                </div>
                <div class="mt-1 ml-6.5 h-1.5 rounded-full bg-superficie-2">
                    <div class="h-full rounded-full bg-serie-1" :style="{ width: `${(municipio.total / maximo) * 100}%` }"></div>
                </div>
            </li>
        </ol>

        <button
            v-if="dados && (todos || dados.municipios.length === LIMITE_INICIAL)"
            type="button"
            class="mt-3 self-start text-xs font-medium text-serie-1 hover:underline"
            @click="todos = !todos"
        >
            {{ todos ? 'Mostrar só os 10 primeiros' : 'Ver todos os municípios' }}
        </button>
    </section>
</template>
