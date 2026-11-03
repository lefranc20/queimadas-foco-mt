<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Cartão do mapa com opção de ampliar. O cartão todo é ampliado para os controles
 * e a legenda continuarem visíveis, sempre abaixo do banner, que segue à mostra.
 */
defineProps({
    rotulo: { type: String, required: true },
});

const MARGEM = 12;

const expandido = ref(false);
const topo = ref(0);
let rolagemAnterior = 0;

const estilo = computed(() => (expandido.value ? { top: `${topo.value}px`, left: `${MARGEM}px`, right: `${MARGEM}px`, bottom: `${MARGEM}px` } : {}));

function medirTopo() {
    topo.value = document.querySelector('header').getBoundingClientRect().bottom + MARGEM;
}

function aoPressionarTecla(evento) {
    if (evento.key === 'Escape') expandido.value = false;
}

function liberarPagina() {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', aoPressionarTecla);
    window.removeEventListener('resize', medirTopo);
}

watch(expandido, (valor) => {
    if (!valor) {
        liberarPagina();
        window.scrollTo(0, rolagemAnterior);
        return;
    }

    // volta ao topo para o banner aparecer inteiro
    rolagemAnterior = window.scrollY;
    window.scrollTo(0, 0);
    medirTopo();
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', aoPressionarTecla);
    window.addEventListener('resize', medirTopo);
});

onBeforeUnmount(liberarPagina);
</script>

<template>
    <section class="cartao flex flex-col p-4" :class="{ 'fixed z-[2000] shadow-2xl': expandido }" :style="estilo" :aria-label="rotulo">
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div><slot name="titulo" /></div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="controles" />
                <button
                    type="button"
                    class="grid size-8 place-items-center rounded-lg border border-borda text-tinta-2 hover:bg-superficie-2 hover:text-tinta"
                    :aria-label="expandido ? 'Reduzir o mapa' : 'Expandir o mapa'"
                    :title="expandido ? 'Reduzir (Esc)' : 'Expandir'"
                    :aria-pressed="expandido"
                    @click="expandido = !expandido"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <!-- setas para dentro (reduzir) ou para fora (expandir) -->
                        <path v-if="expandido" d="M9 3v6H3M15 3v6h6M9 21v-6H3M15 21v-6h6" />
                        <path v-else d="M3 9V3h6M21 9V3h-6M3 15v6h6M21 15v6h-6" />
                    </svg>
                </button>
            </div>
        </div>

        <div :class="{ 'min-h-0 flex-1': expandido }">
            <slot :expandido="expandido" />
        </div>

        <slot name="rodape" />
    </section>
</template>
