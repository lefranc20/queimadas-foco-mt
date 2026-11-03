<script setup>
import { computed, shallowRef, watch } from 'vue';
import BarraFiltros from '../components/BarraFiltros.vue';
import CartaoMapa from '../components/CartaoMapa.vue';
import CartaoNumero from '../components/CartaoNumero.vue';
import MapaFocos from '../components/MapaFocos.vue';
import PainelGrafico from '../components/PainelGrafico.vue';
import { useConsulta } from '../composables/useConsulta.js';
import { useFiltros } from '../composables/useFiltros.js';
import { textoClima } from '../lib/clima.js';
import { data, decimal, numero, periodoCurto, periodoLongo } from '../lib/formato.js';
import { descricaoNivel, detalhesSituacao, focos as rotuloFocos, NIVEIS, tempoDesde } from '../lib/situacao.js';

const props = defineProps({
    id: { type: Number, required: true },
});

const { filtros, parametros, usandoReferencia } = useFiltros();
const parametrosMunicipio = computed(() => ({ ...parametros.value, municipio: props.id }));

const { dados: metadados } = useConsulta('metadados');
const { dados: municipios } = useConsulta('municipios');
const { dados: municipio, carregando, erro } = useConsulta(() => `municipios/${props.id}`, parametros);
const { dados: focos } = useConsulta('focos', parametrosMunicipio);
const { dados: clima } = useConsulta('clima', () => ({ inicio: parametros.value.inicio, fim: parametros.value.fim }));
const climaMunicipio = computed(() => clima.value?.municipios.find((m) => m.id === props.id) ?? null);
const situacao = computed(() => municipio.value?.situacao ?? null);
const nivel = computed(() => NIVEIS[situacao.value?.nivel] ?? NIVEIS.sem_deteccao);
const primeiraMaiuscula = (texto) => texto.charAt(0).toUpperCase() + texto.slice(1);

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
            <div class="mt-1 flex items-center gap-3">
                <!-- nem todo município tem bandeira com licença livre: sem o arquivo, a imagem some -->
                <img
                    :key="id"
                    :src="`/bandeiras/municipios/${id}.webp`"
                    :alt="`Bandeira de ${municipio?.nome ?? 'município'}`"
                    class="h-12 w-auto rounded-sm border border-borda"
                    @error="$event.target.hidden = true"
                />
                <div>
                    <h1 class="text-2xl font-semibold">{{ municipio?.nome ?? 'Carregando…' }}</h1>
                    <p class="text-sm text-tinta-2">
                        Município de Mato Grosso · código IBGE {{ id }}
                        <template v-if="municipio"> · {{ data(municipio.periodo.inicio) }} a {{ data(municipio.periodo.fim) }}</template>
                    </p>
                </div>
            </div>
        </div>

        <BarraFiltros :metadados="metadados" :municipios="municipios ?? []" />

        <p v-if="erro" class="cartao p-4 text-sm text-status-alta">Não foi possível carregar o município: {{ erro }}</p>

        <template v-else>
            <section v-if="situacao" class="cartao p-4" aria-labelledby="titulo-situacao">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="titulo-situacao" class="text-lg font-semibold" :class="nivel.classe">
                        <span aria-hidden="true">{{ nivel.icone }}</span> {{ nivel.rotulo }}
                    </h2>
                    <p class="text-sm">{{ descricaoNivel(situacao.nivel, situacao.ultimo_foco) }}</p>
                </div>
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-tinta-2">Último foco</dt>
                        <dd class="text-sm font-medium">{{ situacao.ultimo_foco ? `${tempoDesde(situacao.ultimo_foco)}, em ${data(situacao.ultimo_foco)}` : 'Nenhum registrado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-tinta-2">Mais forte em 7 dias</dt>
                        <dd class="text-sm font-medium">
                            <template v-if="situacao.intensidade">
                                {{ primeiraMaiuscula(situacao.intensidade.nivel) }} ({{ decimal(situacao.intensidade.frp) }} MW, {{ situacao.intensidade.satelite }})
                            </template>
                            <template v-else>Sem dado de intensidade</template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-tinta-2">Últimos 7 dias</dt>
                        <dd class="text-sm font-medium">{{ rotuloFocos(situacao.focos_7_dias) }}, com fogo em {{ situacao.dias_com_foco }} {{ situacao.dias_com_foco === 1 ? 'dia' : 'dias' }}</dd>
                    </div>
                </dl>
                <p v-if="situacao.ultimas_24h" class="mt-2 text-xs text-tinta-2">Últimas 24 horas: {{ detalhesSituacao(situacao.ultimas_24h) }}</p>
                <p class="mt-2 text-xs text-tinta-2">
                    Conta todos os satélites e não muda com os filtros. Intensidade comparada com o histórico de cada satélite. Não é um alerta oficial.
                </p>
            </section>

            <p v-if="!usandoReferencia" class="rounded-lg border border-borda bg-superficie-2 px-3 py-2 text-xs text-tinta-2">
                Com todos os satélites, os gráficos abaixo não servem para comparar anos: 2024 e 2025 podem ter só o satélite de referência no banco.
            </p>

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

            <section v-if="climaMunicipio" class="cartao p-4" aria-labelledby="titulo-clima">
                <h2 id="titulo-clima" class="text-sm font-semibold">Clima no município</h2>
                <p class="text-xs text-tinta-2">
                    De {{ data(clima.cobertura.inicio) }} a {{ data(clima.cobertura.fim) }}, estimado por modelo meteorológico (Open-Meteo)
                </p>
                <p v-if="clima.cobertura.parcial" class="mt-1 text-xs font-medium text-status-alta">
                    Atenção: esse clima cobre só parte do período selecionado.
                </p>
                <dl class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <div v-for="[rotulo, valor] in textoClima(climaMunicipio)" :key="rotulo">
                        <dt class="text-xs text-tinta-2">{{ rotulo }}</dt>
                        <dd class="text-lg font-semibold">{{ valor }}</dd>
                    </div>
                </dl>
            </section>

            <div class="grid gap-5 lg:grid-cols-3">
                <CartaoMapa class="lg:col-span-2" rotulo="Mapa dos focos do município">
                    <template #titulo>
                        <h2 class="text-sm font-semibold">Focos no período</h2>
                        <p class="text-xs text-tinta-2">
                            {{ focos ? rotuloFocos(focos.total) : '–' }}
                            <template v-if="focos?.truncado"> · exibindo os {{ numero(focos.limite) }} mais recentes</template>
                        </p>
                    </template>

                    <template #default="{ expandido }">
                        <MapaFocos :focos="focos?.focos ?? []" :destaque="contorno" :altura="expandido ? '100%' : '24rem'" />
                    </template>
                </CartaoMapa>

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
