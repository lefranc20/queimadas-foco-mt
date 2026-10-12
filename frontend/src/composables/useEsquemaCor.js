import { ref } from 'vue';

const consulta = window.matchMedia('(prefers-color-scheme: dark)');

/** "claro" ou "escuro", acompanhando a preferência do sistema. */
export const esquemaCor = ref(consulta.matches ? 'escuro' : 'claro');

consulta.addEventListener('change', (evento) => {
    esquemaCor.value = evento.matches ? 'escuro' : 'claro';
});

/** Lê um token de cor de app.css (Chart.js e Leaflet não entendem variáveis CSS). */
export function corDoTema(nome) {
    return getComputedStyle(document.documentElement).getPropertyValue(`--${nome}`).trim();
}

/** "#2a78d6" + 0.12 -> "rgba(42, 120, 214, 0.12)" */
export function comTransparencia(hex, alfa) {
    const [r, g, b] = hex.replace('#', '').match(/../g).map((par) => parseInt(par, 16));
    return `rgba(${r}, ${g}, ${b}, ${alfa})`;
}
