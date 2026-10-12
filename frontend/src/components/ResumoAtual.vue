<script setup>
import { computed } from 'vue';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { data, dataHora } from '../lib/formato.js';
import CartaoNumero from './CartaoNumero.vue';

const { parametros } = useFiltros();

// O resumo é sempre "até hoje": usa só os filtros de satélite e bioma
const { dados, erro } = useConsulta('resumo', () => ({
    satelite: parametros.value.satelite,
    bioma: parametros.value.bioma,
}));

const anoAnterior = computed(() => (dados.value ? Number(dados.value.data_referencia.slice(0, 4)) - 1 : ''));
</script>

<template>
    <section aria-labelledby="titulo-resumo">
        <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titulo-resumo" class="text-sm font-semibold">Situação atual</h2>
            <p v-if="dados" class="text-xs text-tinta-2">
                Atualizado em {{ dataHora(dados.ultima_atualizacao) }} · datas em GMT, como no INPE
            </p>
        </div>

        <p v-if="erro" class="cartao p-4 text-sm text-status-alta">Não foi possível carregar o resumo: {{ erro }}</p>

        <div v-else class="grid gap-3 sm:grid-cols-3">
            <CartaoNumero
                titulo="Hoje"
                :valor="dados?.hoje.total ?? null"
                :detalhe="dados ? `${data(dados.data_referencia)} · parcial, o INPE atualiza ao longo do dia` : ''"
                :anterior="dados?.hoje.ano_anterior ?? null"
                :variacao="dados?.hoje.variacao ?? null"
                :rotulo-anterior="`mesmo dia de ${anoAnterior}`"
            />
            <CartaoNumero
                titulo="No mês"
                :valor="dados?.mes.total ?? null"
                :detalhe="dados ? `De ${data(dados.mes.inicio)} até hoje` : ''"
                :anterior="dados?.mes.ano_anterior ?? null"
                :variacao="dados?.mes.variacao ?? null"
                :rotulo-anterior="`mesmo período de ${anoAnterior}`"
            />
            <CartaoNumero
                titulo="No ano"
                :valor="dados?.ano.total ?? null"
                :detalhe="dados ? `De ${data(dados.ano.inicio)} até hoje` : ''"
                :anterior="dados?.ano.ano_anterior ?? null"
                :variacao="dados?.ano.variacao ?? null"
                :rotulo-anterior="`mesmo período de ${anoAnterior}`"
            />
        </div>
    </section>
</template>
