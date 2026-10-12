<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { TODOS_SATELITES, useFiltros } from '../composables/useFiltros.js';
import { config } from '../lib/config.js';
import { hoje, PERIODOS } from '../lib/datas.js';

const props = defineProps({
    metadados: { type: Object, default: null },
    municipios: { type: Array, default: () => [] },
});

const { filtros, atualizar } = useFiltros();
const roteador = useRouter();

const outrosSatelites = computed(() =>
    (props.metadados?.satelites ?? []).filter((satelite) => satelite !== config.sateliteReferencia),
);
const biomas = computed(() => props.metadados?.biomas ?? ['Amazônia', 'Cerrado', 'Pantanal']);

function alterarData(campo, valor) {
    if (!valor) return;
    const { inicio, fim } = filtros.value;
    const novo = { inicio, fim, [campo]: valor };
    // mantém o intervalo válido se o usuário inverter as datas
    if (novo.inicio > novo.fim) campo === 'inicio' ? (novo.fim = novo.inicio) : (novo.inicio = novo.fim);
    atualizar(novo);
}

function irParaMunicipio(evento) {
    const id = evento.target.value;
    evento.target.value = '';
    if (id) roteador.push({ name: 'municipio', params: { id }, query: roteador.currentRoute.value.query });
}
</script>

<template>
    <section class="cartao flex flex-wrap items-end gap-x-5 gap-y-3 p-3" aria-label="Filtros">
        <div>
            <span class="rotulo mb-1.5 block">Período</span>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Períodos prontos">
                    <button
                        v-for="periodo in PERIODOS"
                        :key="periodo.id"
                        type="button"
                        class="botao-aba"
                        :aria-pressed="filtros.periodo === periodo.id"
                        @click="atualizar({ periodo: periodo.id })"
                    >
                        {{ periodo.rotulo }}
                    </button>
                </div>
                <div class="flex items-center gap-1.5 text-sm text-tinta-2">
                    <input
                        type="date"
                        class="campo"
                        aria-label="Data inicial"
                        min="2003-01-01"
                        :max="hoje()"
                        :value="filtros.inicio"
                        @change="alterarData('inicio', $event.target.value)"
                    />
                    <span>a</span>
                    <input
                        type="date"
                        class="campo"
                        aria-label="Data final"
                        min="2003-01-01"
                        :max="hoje()"
                        :value="filtros.fim"
                        @change="alterarData('fim', $event.target.value)"
                    />
                </div>
            </div>
        </div>

        <label class="block">
            <span class="rotulo mb-1.5 block">Satélite</span>
            <select class="campo" :value="filtros.satelite" @change="atualizar({ satelite: $event.target.value })">
                <option :value="config.sateliteReferencia">Referência ({{ config.sateliteReferencia }})</option>
                <option :value="TODOS_SATELITES">Todos os satélites</option>
                <option v-for="satelite in outrosSatelites" :key="satelite" :value="satelite">{{ satelite }}</option>
            </select>
        </label>

        <label class="block">
            <span class="rotulo mb-1.5 block">Bioma</span>
            <select class="campo" :value="filtros.bioma" @change="atualizar({ bioma: $event.target.value })">
                <option value="">Todos</option>
                <option v-for="bioma in biomas" :key="bioma" :value="bioma">{{ bioma }}</option>
            </select>
        </label>

        <label v-if="municipios.length" class="block">
            <span class="rotulo mb-1.5 block">Município</span>
            <select class="campo max-w-56" @change="irParaMunicipio">
                <option value="">Ver um município…</option>
                <option v-for="municipio in municipios" :key="municipio.id" :value="municipio.id">{{ municipio.nome }}</option>
            </select>
        </label>
    </section>
</template>
