import { decimal, numero } from './formato.js';

/** Escalas sequenciais de um só tom: cinco classes, da mais clara para a mais escura. */
const LARANJA = ['#fee6ce', '#fdae6b', '#f16913', '#d94801', '#8c2d04'];
const AZUL = ['#deebf7', '#9ecae1', '#6baed6', '#2171b5', '#08306b'];

export const INDICADORES = {
    temperatura: { rotulo: 'Temperatura média', unidade: '°C', valor: (m) => m.temperatura_media, cores: LARANJA },
    umidade: { rotulo: 'Umidade do ar', unidade: '%', valor: (m) => m.umidade_media, cores: AZUL },
    chuva: { rotulo: 'Chuva acumulada', unidade: 'mm', valor: (m) => m.chuva_mm, cores: AZUL },
    seca: { rotulo: 'Dias sem chuva', unidade: 'dias', valor: (m) => m.dias_sem_chuva, cores: LARANJA },
};

/**
 * Divide os valores em cinco faixas iguais entre o menor e o maior do período.
 * A escala é relativa ao período selecionado: a mesma cor pode significar valores
 * diferentes em outro período, por isso a legenda mostra sempre os números.
 */
export function faixas(indicador, municipios) {
    const { valor, cores } = INDICADORES[indicador];
    const valores = municipios.map(valor);
    const menor = Math.min(...valores);
    const maior = Math.max(...valores);
    const passo = (maior - menor) / cores.length || 1;

    return cores.map((cor, i) => ({
        cor,
        de: menor + passo * i,
        ate: menor + passo * (i + 1),
    }));
}

export function corDoValor(lista, valor) {
    return (lista.find((faixa) => valor < faixa.ate) ?? lista[lista.length - 1]).cor;
}

export function textoClima(m) {
    const dias = m.dias_sem_chuva_minimo ? `${m.dias_sem_chuva} ou mais` : numero(m.dias_sem_chuva);

    return [
        ['Temperatura média', `${decimal(m.temperatura_media)} °C`],
        ['Mínima e máxima', `${decimal(m.temperatura_min)} °C e ${decimal(m.temperatura_max)} °C`],
        ['Umidade do ar', `${decimal(m.umidade_media)}%`],
        ['Chuva acumulada', `${decimal(m.chuva_mm)} mm`],
        ['Dias sem chuva', dias],
    ];
}
