<script setup>
import { computed, ref } from 'vue';
import BarraFiltros from '../components/BarraFiltros.vue';
import MapaFocos from '../components/MapaFocos.vue';
import PainelGrafico from '../components/PainelGrafico.vue';
import RankingMunicipios from '../components/RankingMunicipios.vue';
import ResumoAtual from '../components/ResumoAtual.vue';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { data, decimal, numero, periodoCurto, periodoLongo } from '../lib/formato.js';

const { filtros, parametros, usandoReferencia } = useFiltros();

const { dados: metadados } = useConsulta('metadados');
const { dados: municipios } = useConsulta('municipios');
const { dados: focos, carregando: carregandoFocos, erro: erroFocos } = useConsulta('focos', parametros);
const { dados: serie, carregando: carregandoSerie } = useConsulta('serie-temporal', parametros);

const modoMapa = ref('pontos');

const anoAnterior = computed(() => Number(filtros.value.fim.slice(0, 4)) - 1);
const pontos = computed(() => serie.value?.serie ?? []);
const rotulos = computed(() => pontos.value.map((p) => p.periodo));
const porMes = computed(() => serie.value?.agrupar === 'mes');

const seriesFocos = computed(() => [
    { nome: 'Período selecionado', rotulo: 'Atual', valores: pontos.value.map((p) => p.total), cor: 'serie-1', area: true },
    { nome: `Mesmo período de ${anoAnterior.value}`, rotulo: String(anoAnterior.value), valores: pontos.value.map((p) => p.total_ano_anterior), cor: 'tinta-suave' },
]);
const seriesDiasSemChuva = computed(() => [
    { nome: 'Dias sem chuva (média)', valores: pontos.value.map((p) => p.media_dias_sem_chuva), cor: 'serie-1' },
]);
const seriesRisco = computed(() => [
    { nome: 'Risco de fogo (média)', valores: pontos.value.map((p) => p.media_risco_fogo), cor: 'serie-1' },
]);
const semDadosMeteorologicos = computed(() => pontos.value.every((p) => p.media_risco_fogo === null && p.media_dias_sem_chuva === null));
</script>

<template>
    <div class="space-y-5">
        <BarraFiltros :metadados="metadados" :municipios="municipios ?? []" />

        <p v-if="!usandoReferencia" class="rounded-lg border border-borda bg-superficie-2 px-3 py-2 text-xs text-tinta-2">
            Você está vendo focos de {{ filtros.satelite === 'todos' ? 'todos os satélites' : `um satélite específico (${filtros.satelite})` }}.
            Um mesmo incêndio pode ser detectado por vários satélites, então esses números não servem para comparar anos.
            <RouterLink :to="{ name: 'sobre' }" class="underline">Entenda o satélite de referência</RouterLink>.
        </p>

        <ResumoAtual />

        <div class="grid gap-5 lg:grid-cols-3">
            <section class="cartao p-4 lg:col-span-2" aria-labelledby="titulo-mapa">
                <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 id="titulo-mapa" class="text-sm font-semibold">Focos no mapa</h2>
                        <p class="text-xs text-tinta-2">
                            <template v-if="focos">
                                {{ numero(focos.total) }} focos de {{ data(focos.periodo.inicio) }} a {{ data(focos.periodo.fim) }}
                                <template v-if="focos.truncado"> · exibindo os {{ numero(focos.limite) }} mais recentes</template>
                            </template>
                            <template v-else-if="erroFocos">{{ erroFocos }}</template>
                            <template v-else>Carregando focos…</template>
                        </p>
                    </div>
                    <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Visualização do mapa">
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'pontos'" @click="modoMapa = 'pontos'">Pontos</button>
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'calor'" @click="modoMapa = 'calor'">Mapa de calor</button>
                    </div>
                </div>
                <div :class="{ 'opacity-60 transition-opacity': carregandoFocos }">
                    <MapaFocos v-model:modo="modoMapa" :focos="focos?.focos ?? []" />
                </div>
            </section>

            <RankingMunicipios class="lg:max-h-[36rem]" />
        </div>

        <PainelGrafico
            titulo="Focos ao longo do tempo"
            :subtitulo="`Total por ${porMes ? 'mês' : 'dia'} comparado com as mesmas datas de ${anoAnterior}`"
            :rotulos="rotulos"
            :series="seriesFocos"
            :formatar-eixo="periodoCurto"
            :formatar-tooltip="periodoLongo"
            :carregando="carregandoSerie"
            altura="18rem"
        />

        <div>
            <h2 class="text-sm font-semibold">Condições do fogo</h2>
            <p class="mb-2 text-xs text-tinta-2">
                Médias dos focos detectados em cada {{ porMes ? 'mês' : 'dia' }}, segundo o modelo de risco do INPE.
                Gráficos separados porque as escalas são diferentes.
            </p>
            <div class="grid gap-5 md:grid-cols-2">
                <PainelGrafico
                    titulo="Dias sem chuva"
                    subtitulo="Média de dias consecutivos sem chuva no local do foco"
                    :rotulos="rotulos"
                    :series="seriesDiasSemChuva"
                    :formatar-eixo="periodoCurto"
                    :formatar-tooltip="periodoLongo"
                    :formatar-valor="decimal"
                    :carregando="carregandoSerie"
                    :vazio="semDadosMeteorologicos ? 'Sem dados meteorológicos para este período' : ''"
                    altura="12rem"
                />
                <PainelGrafico
                    titulo="Risco de fogo"
                    subtitulo="Média do risco de fogo (0 = mínimo, 1 = crítico)"
                    :rotulos="rotulos"
                    :series="seriesRisco"
                    :formatar-eixo="periodoCurto"
                    :formatar-tooltip="periodoLongo"
                    :formatar-valor="decimal"
                    :carregando="carregandoSerie"
                    :vazio="semDadosMeteorologicos ? 'Sem dados meteorológicos para este período' : ''"
                    altura="12rem"
                />
            </div>
        </div>
    </div>
</template>
