<script setup>
import { computed, shallowRef, watch } from 'vue';
import BarraFiltros from '../components/BarraFiltros.vue';
import CartaoNumero from '../components/CartaoNumero.vue';
import MapaFocos from '../components/MapaFocos.vue';
import PainelGrafico from '../components/PainelGrafico.vue';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { data, decimal, numero, periodoCurto, periodoLongo } from '../lib/formato.js';

const props = defineProps({
    id: { type: Number, required: true },
});

const { filtros, parametros } = useFiltros();
const parametrosMunicipio = computed(() => ({ ...parametros.value, municipio: props.id }));

const { dados: metadados } = useConsulta('metadados');
const { dados: municipios } = useConsulta('municipios');
const { dados: municipio, carregando, erro } = useConsulta(() => `municipios/${props.id}`, parametros);
const { dados: focos } = useConsulta('focos', parametrosMunicipio);

// contorno do município (malha do IBGE) para destacar no mapa
const malha = shallowRef(null);
fetch('/geo/mt-municipios.json')
    .then((resposta) => resposta.json())
    .then((geojson) => (malha.value = geojson));
const contorno = computed(
    () => malha.value?.features.find((feature) => feature.properties.codarea === String(props.id)) ?? null,
);

watch(municipio, (dados) => {
    if (dados) document.title = `${dados.nome} · Focos de Queimadas MT`;
});

const anoFim = computed(() => Number(filtros.value.fim.slice(0, 4)));
const mensal = computed(() => municipio.value?.serie_mensal ?? []);
const seriesMensais = computed(() => [
    { nome: String(anoFim.value), valores: mensal.value.map((p) => p.total), cor: 'serie-1', area: true },
    { nome: String(anoFim.value - 1), valores: mensal.value.map((p) => p.total_ano_anterior), cor: 'tinta-suave' },
]);
const rotulosMensais = computed(() => mensal.value.map((p) => p.periodo));
const porAno = computed(() => municipio.value?.por_ano ?? []);
const rotulosAnuais = computed(() => porAno.value.map((p) => String(p.ano)));
const seriesAnuais = computed(() => [{ nome: 'Focos', valores: porAno.value.map((p) => p.total), cor: 'serie-1' }]);
const maiorBioma = computed(() => municipio.value?.biomas[0]?.total || 1);
</script>

<template>
    <div class="space-y-5">
        <div>
            <RouterLink :to="{ name: 'painel', query: $route.query }" class="text-sm text-tinta-2 hover:underline">← Voltar ao painel</RouterLink>
            <h1 class="mt-1 text-2xl font-semibold">{{ municipio?.nome ?? 'Carregando…' }}</h1>
            <p class="text-sm text-tinta-2">
                Município de Mato Grosso · código IBGE {{ id }}
                <template v-if="municipio"> · {{ data(municipio.periodo.inicio) }} a {{ data(municipio.periodo.fim) }}</template>
            </p>
        </div>

        <BarraFiltros :metadados="metadados" :municipios="municipios ?? []" />

        <p v-if="erro" class="cartao p-4 text-sm text-status-alta">Não foi possível carregar o município: {{ erro }}</p>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" :class="{ 'opacity-60 transition-opacity': carregando }">
                <CartaoNumero titulo="Focos no período" :valor="municipio?.total ?? null" />
                <div class="cartao p-4">
                    <p class="rotulo">Posição no estado</p>
                    <p class="mt-1 text-3xl font-semibold">{{ municipio?.posicao_ranking ? `${municipio.posicao_ranking}º` : '–' }}</p>
                    <p class="mt-0.5 text-xs text-tinta-2">entre {{ numero(municipio?.municipios_com_focos ?? null) }} municípios com focos</p>
                </div>
                <div class="cartao p-4">
                    <p class="rotulo">Dias sem chuva</p>
                    <p class="mt-1 text-3xl font-semibold">{{ decimal(municipio?.medias.dias_sem_chuva ?? null) }}</p>
                    <p class="mt-0.5 text-xs text-tinta-2">média no local dos focos</p>
                </div>
                <div class="cartao p-4">
                    <p class="rotulo">Risco de fogo</p>
                    <p class="mt-1 text-3xl font-semibold">{{ decimal(municipio?.medias.risco_fogo ?? null) }}</p>
                    <p class="mt-0.5 text-xs text-tinta-2">média, de 0 (mínimo) a 1 (crítico)</p>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-3">
                <section class="cartao p-4 lg:col-span-2" aria-label="Mapa dos focos do município">
                    <h2 class="mb-1 text-sm font-semibold">Focos no período</h2>
                    <p class="mb-3 text-xs text-tinta-2">
                        {{ numero(focos?.total ?? null) }} focos
                        <template v-if="focos?.truncado"> · exibindo os {{ numero(focos.limite) }} mais recentes</template>
                    </p>
                    <MapaFocos :focos="focos?.focos ?? []" :destaque="contorno" altura="24rem" />
                </section>

                <section class="cartao p-4" aria-labelledby="titulo-biomas">
                    <h2 id="titulo-biomas" class="text-sm font-semibold">Focos por bioma</h2>
                    <p class="mb-3 text-xs text-tinta-2">No período selecionado</p>
                    <p v-if="municipio && !municipio.biomas.length" class="text-sm text-tinta-2">Nenhum foco no período.</p>
                    <ul class="space-y-3">
                        <li v-for="bioma in municipio?.biomas ?? []" :key="bioma.bioma ?? 'sem'">
                            <div class="flex justify-between text-sm">
                                <span>{{ bioma.bioma ?? 'Não informado' }}</span>
                                <span class="tabular-nums">{{ numero(bioma.total) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-superficie-2">
                                <div class="h-full rounded-full bg-serie-1" :style="{ width: `${(bioma.total / maiorBioma) * 100}%` }"></div>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="grid gap-5 lg:grid-cols-2">
                <PainelGrafico
                    :titulo="`Focos por mês em ${anoFim}`"
                    :subtitulo="`Comparado com ${anoFim - 1}`"
                    :rotulos="rotulosMensais"
                    :series="seriesMensais"
                    :formatar-eixo="periodoCurto"
                    :formatar-tooltip="periodoLongo"
                    :carregando="carregando"
                />
                <PainelGrafico
                    titulo="Histórico por ano"
                    subtitulo="Todos os anos importados, com os filtros de satélite e bioma"
                    tipo="bar"
                    :rotulos="rotulosAnuais"
                    :series="seriesAnuais"
                    :carregando="carregando"
                    :vazio="municipio && !porAno.length ? 'Sem focos registrados' : ''"
                />
            </div>
        </template>
    </div>
</template>
