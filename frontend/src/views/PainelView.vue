<script setup>
import { computed, ref } from 'vue';
import BarraFiltros from '../components/BarraFiltros.vue';
import CartaoMapa from '../components/CartaoMapa.vue';
import MapaFocos from '../components/MapaFocos.vue';
import PainelGrafico from '../components/PainelGrafico.vue';
import RankingMunicipios from '../components/RankingMunicipios.vue';
import ResumoAtual from '../components/ResumoAtual.vue';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { faixas, INDICADORES } from '../lib/clima.js';
import { data, decimal, numero, periodoCurto, periodoLongo } from '../lib/formato.js';

const { filtros, parametros, usandoReferencia } = useFiltros();

const { dados: metadados } = useConsulta('metadados');
const { dados: municipios } = useConsulta('municipios');
const { dados: focos, carregando: carregandoFocos, erro: erroFocos } = useConsulta('focos', parametros);
const { dados: serie, carregando: carregandoSerie } = useConsulta('serie-temporal', parametros);

const { dados: clima } = useConsulta('clima', () => ({ inicio: parametros.value.inicio, fim: parametros.value.fim }));

const modoMapa = ref('pontos');
const indicadorClima = ref('');
const municipiosClima = computed(() => clima.value?.municipios ?? []);
const umaCasa = (valor) => decimal(Math.round(valor * 10) / 10);
const legendaClima = computed(() =>
    indicadorClima.value && municipiosClima.value.length ? faixas(indicadorClima.value, municipiosClima.value) : [],
);

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
            <CartaoMapa class="lg:col-span-2" rotulo="Focos no mapa">
                <template #titulo>
                    <h2 class="text-sm font-semibold">Focos no mapa</h2>
                    <p class="text-xs text-tinta-2">
                        <template v-if="focos">
                            {{ numero(focos.total) }} focos de {{ data(focos.periodo.inicio) }} a {{ data(focos.periodo.fim) }}
                            <template v-if="focos.truncado"> · exibindo os {{ numero(focos.limite) }} mais recentes</template>
                        </template>
                        <template v-else-if="erroFocos">{{ erroFocos }}</template>
                        <template v-else>Carregando focos…</template>
                    </p>
                </template>

                <template #controles>
                    <select v-model="indicadorClima" class="campo h-8 text-xs" aria-label="Clima no mapa">
                        <option value="">Sem clima no mapa</option>
                        <option v-for="(item, chave) in INDICADORES" :key="chave" :value="chave">{{ item.rotulo }}</option>
                    </select>
                    <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Visualização do mapa">
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'pontos'" @click="modoMapa = 'pontos'">Pontos</button>
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'calor'" @click="modoMapa = 'calor'">Mapa de calor</button>
                    </div>
                </template>

                <template #default="{ expandido }">
                    <div class="h-full" :class="{ 'opacity-60 transition-opacity': carregandoFocos }">
                        <MapaFocos
                            v-model:modo="modoMapa"
                            :focos="focos?.focos ?? []"
                            :clima="municipiosClima"
                            :indicador="indicadorClima"
                            :altura="expandido ? '100%' : '30rem'"
                        />
                    </div>
                </template>

                <template #rodape>
                    <div v-if="indicadorClima" class="mt-2 text-xs text-tinta-2">
                        <template v-if="legendaClima.length">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span class="font-medium">{{ INDICADORES[indicadorClima].rotulo }}</span>
                                <span v-for="faixa in legendaClima" :key="faixa.cor" class="flex items-center gap-1">
                                    <span class="size-3 rounded-sm" :style="{ background: faixa.cor }"></span>
                                    {{ umaCasa(faixa.de) }} a {{ umaCasa(faixa.ate) }} {{ INDICADORES[indicadorClima].unidade }}
                                </span>
                            </div>
                            <p v-if="clima.cobertura.parcial" class="mt-1 font-medium text-status-alta">
                                Atenção: há clima só de {{ data(clima.cobertura.inicio) }} a {{ data(clima.cobertura.fim) }}, não de todo o período selecionado.
                            </p>
                            <p class="mt-1">Passe o mouse ou toque em um município para ver todos os dados. Valores estimados por modelo meteorológico (Open-Meteo), não por estações locais.</p>
                        </template>
                        <template v-else>Sem dados de clima para este período.</template>
                    </div>
                </template>
            </CartaoMapa>

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
