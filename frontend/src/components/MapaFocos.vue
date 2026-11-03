<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { corDoTema, esquemaCor } from '../composables/useEsquemaCor.js';
import { corDoValor, faixas, INDICADORES, textoClima } from '../lib/clima.js';
import { dataHoraGmt, decimal, escaparHtml, numero } from '../lib/formato.js';
import { carregarLeaflet } from '../lib/leaflet.js';

const props = defineProps({
    focos: { type: Array, default: () => [] },
    // GeoJSON de um município para destacar e enquadrar (página do município)
    destaque: { type: Object, default: null },
    altura: { type: String, default: '30rem' },
    // clima por município (GET /api/clima) e indicador usado para colorir o mapa
    clima: { type: Array, default: () => [] },
    indicador: { type: String, default: '' },
});

const modo = defineModel('modo', { type: String, default: 'pontos' });

const CENTRO_MT = [-12.9, -55.9];
const LIMITES_MT = [[-18.2, -61.8], [-7.2, -50.0]];
const MAPA_BASE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATRIBUICAO = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · Focos: INPE · Limites: IBGE · Clima: <a href="https://open-meteo.com/">Open-Meteo</a>';

const elemento = ref(null);
const roteador = useRouter();
let L, mapa, contornoEstado, contornoDestaque, camadaClima, clusters, calor;
let malhaMunicipios = null;
let observadorTamanho = null;
let desmontado = false;

onMounted(async () => {
    L = await carregarLeaflet();
    if (desmontado) return;

    mapa = L.map(elemento.value, {
        center: CENTRO_MT,
        zoom: 6,
        minZoom: 5,
        maxBounds: L.latLngBounds(LIMITES_MT).pad(0.5),
    });
    mapa.fitBounds(LIMITES_MT);
    observadorTamanho = new ResizeObserver(() => mapa?.invalidateSize());
    observadorTamanho.observe(elemento.value);
    L.tileLayer(MAPA_BASE, { attribution: ATRIBUICAO, maxZoom: 18, className: 'mapa-base' }).addTo(mapa);

    clusters = L.markerClusterGroup({
        chunkedLoading: true,
        showCoverageOnHover: false,
        maxClusterRadius: 45,
        iconCreateFunction: iconeCluster,
    });
    // escala sequencial de um só tom (laranja claro -> escuro), em vez do arco-íris padrão
    calor = L.heatLayer([], {
        radius: 14,
        blur: 18,
        maxZoom: 10,
        gradient: { 0.25: '#fed7aa', 0.5: '#fb923c', 0.75: '#ea580c', 1: '#9a3412' },
    });

    desenharFocos();
    aplicarModo();
    desenharDestaque();

    // links "ver município" dentro do popup navegam pelo vue-router
    mapa.on('popupopen', ({ popup }) => {
        popup.getElement().querySelector('[data-municipio]')?.addEventListener('click', (evento) => {
            evento.preventDefault();
            roteador.push({ name: 'municipio', params: { id: evento.currentTarget.dataset.municipio } });
        });
    });

    const geojson = await fetch('/geo/mt-estado.json').then((r) => r.json());
    if (!mapa) return;
    contornoEstado = L.geoJSON(geojson, { interactive: false, style: estiloContorno }).addTo(mapa);
    desenharClima();
});

onBeforeUnmount(() => {
    desmontado = true;
    observadorTamanho?.disconnect();
    mapa?.remove();
    mapa = null;
});

watch(() => props.focos, desenharFocos);
watch(() => props.destaque, desenharDestaque);
watch(modo, aplicarModo);
watch(() => [props.clima, props.indicador], desenharClima);
watch(esquemaCor, () => {
    if (!mapa) return;
    contornoEstado?.setStyle(estiloContorno());
    desenharFocos();
});

function estiloContorno() {
    return { color: corDoTema('tinta-2'), weight: 1.5, fill: false };
}

function iconeCluster(cluster) {
    const quantidade = cluster.getChildCount();
    const tamanho = quantidade < 10 ? 26 : quantidade < 100 ? 32 : quantidade < 1000 ? 40 : 48;

    return L.divIcon({
        html: `<span>${numero(quantidade)}</span>`,
        className: 'cluster-foco',
        iconSize: [tamanho, tamanho],
    });
}

function desenharFocos() {
    if (!mapa) return;

    const estilo = {
        radius: 5,
        weight: 1.5,
        color: corDoTema('superficie'), // anel da cor do fundo separa pontos sobrepostos
        fillColor: corDoTema('fogo'),
        fillOpacity: 0.9,
    };

    clusters.clearLayers();
    clusters.addLayers(
        props.focos.map((foco) => L.circleMarker([foco.lat, foco.lon], estilo).bindPopup(() => popup(foco))),
    );

    calor.setLatLngs(props.focos.map((foco) => [foco.lat, foco.lon, 0.6]));
}

function aplicarModo() {
    if (!mapa) return;

    const [mostrar, esconder] = modo.value === 'calor' ? [calor, clusters] : [clusters, calor];
    mapa.removeLayer(esconder);
    mapa.addLayer(mostrar);
}

function desenharDestaque() {
    if (!mapa) return;

    contornoDestaque?.remove();
    if (!props.destaque) return;

    contornoDestaque = L.geoJSON(props.destaque, {
        interactive: false,
        style: { color: corDoTema('serie-1'), weight: 2, fillColor: corDoTema('serie-1'), fillOpacity: 0.06 },
    }).addTo(mapa);
    mapa.fitBounds(contornoDestaque.getBounds(), { padding: [20, 20] });
}

/** Pinta os municípios pelo indicador escolhido; passar o mouse mostra todos os dados de clima. */
async function desenharClima() {
    if (!mapa) return;

    camadaClima?.remove();
    camadaClima = null;
    if (!props.indicador || !props.clima.length) return;

    malhaMunicipios ??= await fetch('/geo/mt-municipios.json').then((r) => r.json());
    if (!mapa) return;

    const porId = new Map(props.clima.map((m) => [String(m.id), m]));
    const { valor } = INDICADORES[props.indicador];
    const escala = faixas(props.indicador, props.clima);

    camadaClima?.remove();
    camadaClima = L.geoJSON(malhaMunicipios, {
        style: ({ properties }) => {
            const municipio = porId.get(properties.codarea);
            return {
                color: corDoTema('superficie'),
                weight: 0.6,
                fillColor: municipio ? corDoValor(escala, valor(municipio)) : 'transparent',
                fillOpacity: 0.65,
            };
        },
        onEachFeature: ({ properties }, camada) => {
            const municipio = porId.get(properties.codarea);
            if (municipio) camada.bindTooltip(textoTooltip(municipio), { sticky: true });
        },
    }).addTo(mapa);
    camadaClima.bringToBack();
}

function textoTooltip(municipio) {
    const linhas = textoClima(municipio).map(([rotulo, texto]) => `<div>${escaparHtml(rotulo)}: ${escaparHtml(texto)}</div>`);

    return `<strong>${escaparHtml(municipio.nome)}</strong>${linhas.join('')}`;
}

function popup(foco) {
    const linha = (rotulo, valor) =>
        valor === null || valor === undefined ? '' : `<div><span style="color:var(--tinta-2)">${rotulo}:</span> ${escaparHtml(valor)}</div>`;

    const municipio = foco.municipio_id
        ? `<a href="/municipios/${foco.municipio_id}" data-municipio="${foco.municipio_id}" style="color:var(--serie-1);font-weight:600">${escaparHtml(foco.municipio)}</a>`
        : escaparHtml(foco.municipio ?? 'Município não identificado');

    return `
        <div style="font-size:14px;margin-bottom:4px">${municipio}</div>
        ${linha('Detectado em', dataHoraGmt(foco.data_hora_gmt))}
        ${linha('Satélite', foco.satelite)}
        ${linha('Bioma', foco.bioma)}
        ${linha('Intensidade', foco.frp === null || foco.frp === undefined ? null : `${foco.intensidade ?? 'sem referência'} (${decimal(foco.frp)} MW)`)}
        ${linha('Risco de fogo', foco.risco_fogo === null ? null : decimal(foco.risco_fogo))}
        ${linha('Dias sem chuva', foco.numero_dias_sem_chuva)}
    `;
}
</script>

<template>
    <div ref="elemento" class="w-full rounded-lg" :style="{ height: altura }" role="region" aria-label="Mapa dos focos de queimada"></div>
</template>
