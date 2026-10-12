import { onScopeDispose, ref, shallowRef, toValue, watch } from 'vue';
import { buscar } from '../lib/api.js';

/**
 * Busca um endpoint da API sempre que o caminho ou os parâmetros mudam.
 * Mantém os dados anteriores enquanto carrega (sem "piscar" a tela) e cancela
 * a requisição anterior se os filtros mudarem antes da resposta.
 */
export function useConsulta(caminho, parametros = () => ({})) {
    const dados = shallowRef(null);
    const carregando = ref(false);
    const erro = ref(null);
    let controle = null;

    async function carregar() {
        controle?.abort();
        controle = new AbortController();
        carregando.value = true;
        erro.value = null;

        try {
            dados.value = await buscar(toValue(caminho), toValue(parametros), { signal: controle.signal });
        } catch (e) {
            if (e.name !== 'AbortError') erro.value = e.message;
        } finally {
            if (!controle.signal.aborted) carregando.value = false;
        }
    }

    watch(() => [toValue(caminho), JSON.stringify(toValue(parametros))], carregar, { immediate: true });
    onScopeDispose(() => controle?.abort());

    return { dados, carregando, erro, recarregar: carregar };
}
