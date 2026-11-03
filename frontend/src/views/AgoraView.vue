<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import CartaoMapa from '../components/CartaoMapa.vue';
import MapaFocos from '../components/MapaFocos.vue';
import { useConsulta } from '../composables/useConsulta.js';
import { dataHora, numero } from '../lib/formato.js';
import { descricaoNivel, detalhesSituacao, NIVEIS, tempoDesde } from '../lib/situacao.js';

const JANELAS = [6, 12, 24, 48];
const ATUALIZACAO_MS = 5 * 60 * 1000;

const horas = ref(24);
const modoMapa = ref('pontos');
const agora = ref(Date.now());

const { dados, carregando, erro, recarregar } = useConsulta('focos/recentes', () => ({ horas: horas.value }));

// o INPE publica um arquivo novo a cada 10 minutos
const relogio = setInterval(() => {
    agora.value = Date.now();
    recarregar();
}, ATUALIZACAO_MS);
onBeforeUnmount(() => clearInterval(relogio));

const ha = (dataHoraGmt) => tempoDesde(dataHoraGmt, agora.value);

const municipios = computed(() => dados.value?.municipios ?? []);
const emAtencao = computed(() => municipios.value.filter((m) => m.nivel === 'atencao').length);
const relevantes = computed(() => municipios.value.filter((m) => m.nivel !== 'isolado'));
const isolados = computed(() => municipios.value.filter((m) => m.nivel === 'isolado'));
</script>

<template>
    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold">Focos agora</h1>
            <p class="text-sm text-tinta-2">
                Detecções recentes em Mato Grosso, por todos os satélites. A página se atualiza sozinha.
            </p>
        </div>

        <section class="cartao flex flex-wrap items-center justify-between gap-3 p-3" aria-label="Janela de tempo">
            <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Últimas horas">
                <button
                    v-for="janela in JANELAS"
                    :key="janela"
                    type="button"
                    class="botao-aba"
                    :aria-pressed="horas === janela"
                    @click="horas = janela"
                >
                    {{ janela }} h
                </button>
            </div>
            <p v-if="dados" class="text-xs text-tinta-2">Coletado em {{ dataHora(dados.atualizado_em) }}</p>
        </section>

        <p v-if="erro" class="cartao p-4 text-sm text-status-alta">Não foi possível carregar os focos recentes: {{ erro }}</p>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="cartao p-4">
                <p class="rotulo">Focos detectados</p>
                <p class="mt-1 text-3xl font-semibold">{{ numero(dados?.total ?? null) }}</p>
                <p class="mt-0.5 text-xs text-tinta-2">nas últimas {{ horas }} horas</p>
            </div>
            <div class="cartao p-4">
                <p class="rotulo">Municípios em atenção</p>
                <p class="mt-1 text-3xl font-semibold">{{ dados ? numero(emAtencao) : '–' }}</p>
                <p class="mt-0.5 text-xs text-tinta-2">de {{ numero(dados?.municipios.length ?? null) }} com focos</p>
            </div>
            <div class="cartao p-4">
                <p class="rotulo">Última detecção</p>
                <p class="mt-1 text-3xl font-semibold">{{ dados?.ultima_deteccao ? ha(dados.ultima_deteccao) : '–' }}</p>
                <p class="mt-0.5 text-xs text-tinta-2">pelo horário da passagem do satélite</p>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <CartaoMapa class="lg:col-span-2" rotulo="Focos no mapa">
                <template #titulo>
                    <h2 class="text-sm font-semibold">Focos no mapa</h2>
                </template>

                <template #controles>
                    <div class="flex rounded-lg border border-borda p-0.5" role="group" aria-label="Visualização do mapa">
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'pontos'" @click="modoMapa = 'pontos'">Pontos</button>
                        <button type="button" class="botao-aba" :aria-pressed="modoMapa === 'calor'" @click="modoMapa = 'calor'">Mapa de calor</button>
                    </div>
                </template>

                <template #default="{ expandido }">
                    <div class="h-full" :class="{ 'opacity-60 transition-opacity': carregando }">
                        <MapaFocos v-model:modo="modoMapa" :focos="dados?.focos ?? []" :altura="expandido ? '100%' : '30rem'" />
                    </div>
                </template>
            </CartaoMapa>

            <section class="cartao flex flex-col p-4 lg:max-h-[36rem]" aria-labelledby="titulo-agora-municipios">
                <h2 id="titulo-agora-municipios" class="text-sm font-semibold">O que merece atenção</h2>
                <p class="mb-3 text-xs text-tinta-2">Do mais grave para o menos grave, pela repetição do fogo no mesmo lugar</p>
                <p v-if="dados && !municipios.length" class="py-8 text-center text-sm text-tinta-2">Nenhum foco nesse intervalo.</p>

                <div class="space-y-3 overflow-y-auto pr-1">
                    <RouterLink
                        v-for="municipio in relevantes"
                        :key="municipio.id"
                        :to="{ name: 'municipio', params: { id: municipio.id } }"
                        class="block rounded-lg border border-borda p-2.5 hover:bg-superficie-2"
                    >
                        <span class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-sm font-semibold">{{ municipio.nome }}</span>
                            <span class="shrink-0 text-xs font-medium" :class="NIVEIS[municipio.nivel].classe">
                                <span aria-hidden="true">{{ NIVEIS[municipio.nivel].icone }}</span> {{ NIVEIS[municipio.nivel].rotulo }}
                            </span>
                        </span>
                        <span class="mt-0.5 block text-xs">{{ descricaoNivel(municipio.nivel, municipio.ultima_deteccao, agora) }}</span>
                        <span class="mt-0.5 block text-xs text-tinta-2">{{ detalhesSituacao(municipio) }}</span>
                        <span class="block text-xs text-tinta-2">
                            Última detecção {{ ha(municipio.ultima_deteccao) }} · foco em {{ municipio.dias_com_foco }} dos últimos 7 dias
                        </span>
                    </RouterLink>

                    <details v-if="isolados.length" class="text-sm">
                        <summary class="cursor-pointer text-xs font-medium text-tinta-2">
                            {{ isolados.length }} {{ isolados.length === 1 ? 'município' : 'municípios' }} com ponto isolado
                        </summary>
                        <ul class="mt-2 space-y-1">
                            <li v-for="municipio in isolados" :key="municipio.id" class="flex items-baseline justify-between gap-2">
                                <RouterLink :to="{ name: 'municipio', params: { id: municipio.id } }" class="truncate hover:underline">{{ municipio.nome }}</RouterLink>
                                <span class="shrink-0 text-xs text-tinta-2">{{ ha(municipio.ultima_deteccao) }}</span>
                            </li>
                        </ul>
                    </details>
                </div>
            </section>
        </div>

        <aside class="rounded-lg border border-borda bg-superficie-2 px-3 py-2 text-xs text-tinta-2">
            "Agora" não é ao vivo: o GOES-19 chega em cerca de 20 minutos e os demais satélites, entre 2 e 3,5 horas depois da passagem.
            Estes dados ainda não trazem a intensidade do fogo, que vem no arquivo diário. A ausência de focos pode ser apenas nuvens ou
            falta de passagem do satélite. Os níveis são regras deste painel, não um alerta oficial: em caso de fogo, ligue 193.
        </aside>
    </div>
</template>
