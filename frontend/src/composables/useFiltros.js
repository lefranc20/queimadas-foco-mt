import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { config } from '../lib/config.js';
import { hoje, PERIODO_PADRAO, PERIODOS } from '../lib/datas.js';

export const TODOS_SATELITES = 'todos';

/**
 * Filtros do painel guardados na query string, para que qualquer visão possa ser
 * compartilhada por link. Sem satélite na URL, vale o satélite de referência.
 */
export function useFiltros() {
    const rota = useRoute();
    const roteador = useRouter();

    const periodo = computed(() => {
        if (rota.query.inicio || rota.query.fim) return 'personalizado';
        return PERIODOS.some((p) => p.id === rota.query.periodo) ? rota.query.periodo : PERIODO_PADRAO;
    });

    const filtros = computed(() => {
        const preset = PERIODOS.find((p) => p.id === periodo.value);

        return {
            periodo: periodo.value,
            inicio: preset ? preset.inicio() : rota.query.inicio || rota.query.fim,
            fim: preset ? hoje() : rota.query.fim || hoje(),
            satelite: rota.query.satelite || config.sateliteReferencia,
            bioma: rota.query.bioma || '',
        };
    });

    /** Parâmetros no formato da API. */
    const parametros = computed(() => ({
        inicio: filtros.value.inicio,
        fim: filtros.value.fim,
        bioma: filtros.value.bioma,
        satelite: filtros.value.satelite === TODOS_SATELITES ? '' : filtros.value.satelite,
    }));

    const usandoReferencia = computed(() => filtros.value.satelite === config.sateliteReferencia);

    function atualizar(alteracoes) {
        const query = { ...rota.query, ...alteracoes };

        if ('periodo' in alteracoes) {
            delete query.inicio;
            delete query.fim;
        }
        if ('inicio' in alteracoes || 'fim' in alteracoes) delete query.periodo;
        if (query.periodo === PERIODO_PADRAO) delete query.periodo;
        if (query.satelite === config.sateliteReferencia) delete query.satelite;

        for (const chave of Object.keys(query)) {
            if (query[chave] === '' || query[chave] === null || query[chave] === undefined) delete query[chave];
        }

        roteador.replace({ query });
    }

    return { filtros, parametros, usandoReferencia, atualizar };
}
