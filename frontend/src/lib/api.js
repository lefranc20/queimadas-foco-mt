import { config } from './config.js';

/**
 * Cliente da API própria. O frontend nunca consulta o INPE diretamente.
 */
export async function buscar(caminho, parametros = {}, { signal } = {}) {
    const query = new URLSearchParams(
        Object.entries(parametros).filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined),
    );
    const url = `${config.urlApi}/api/${caminho}${query.size ? `?${query}` : ''}`;

    const resposta = await fetch(url, { headers: { Accept: 'application/json' }, signal });

    if (!resposta.ok) {
        const corpo = await resposta.json().catch(() => ({}));
        throw new Error(corpo.message || `Erro ${resposta.status} ao consultar ${caminho}`);
    }

    return resposta.json();
}
