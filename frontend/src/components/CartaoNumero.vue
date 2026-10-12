<script setup>
import { computed } from 'vue';
import { numero, variacao as formatarVariacao } from '../lib/formato.js';

const props = defineProps({
    titulo: { type: String, required: true },
    valor: { type: Number, default: null },
    detalhe: { type: String, default: '' },
    // comparação com o ano anterior (opcional)
    anterior: { type: Number, default: null },
    variacao: { type: Number, default: null },
    rotuloAnterior: { type: String, default: '' },
});

// Mais focos é pior: alta em vermelho, queda em verde, sempre com seta e texto.
const tendencia = computed(() => {
    if (props.variacao === null) return null;
    if (props.variacao > 0) return { seta: '▲', texto: 'acima', classe: 'text-status-alta' };
    if (props.variacao < 0) return { seta: '▼', texto: 'abaixo', classe: 'text-status-baixa' };
    return { seta: '=', texto: 'igual', classe: 'text-tinta-2' };
});
</script>

<template>
    <div class="cartao p-4">
        <p class="rotulo">{{ titulo }}</p>
        <p class="mt-1 text-3xl font-semibold">{{ numero(valor) }}</p>
        <p v-if="detalhe" class="mt-0.5 text-xs text-tinta-2">{{ detalhe }}</p>

        <p v-if="anterior !== null" class="mt-2 text-sm text-tinta-2">
            <template v-if="tendencia">
                <span :class="['font-medium', tendencia.classe]">
                    <span aria-hidden="true">{{ tendencia.seta }}</span> {{ formatarVariacao(variacao) }}
                </span>
                {{ tendencia.texto }} · {{ rotuloAnterior }}: {{ numero(anterior) }}
            </template>
            <template v-else>Sem focos no {{ rotuloAnterior }} para comparar</template>
        </p>
    </div>
</template>
