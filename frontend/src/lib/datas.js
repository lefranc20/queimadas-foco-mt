/** Datas em AAAA-MM-DD, em GMT, como na API e nos arquivos do INPE. */
export const hoje = () => new Date().toISOString().slice(0, 10);

export function diasAtras(dias, base = hoje()) {
    const instante = new Date(`${base}T00:00:00Z`);
    instante.setUTCDate(instante.getUTCDate() - dias);
    return instante.toISOString().slice(0, 10);
}

export const inicioDoAno = (base = hoje()) => `${base.slice(0, 4)}-01-01`;

export const PERIODOS = [
    { id: '7d', rotulo: '7 dias', inicio: () => diasAtras(6) },
    { id: '30d', rotulo: '30 dias', inicio: () => diasAtras(29) },
    { id: '90d', rotulo: '90 dias', inicio: () => diasAtras(89) },
    { id: 'ano', rotulo: 'Ano atual', inicio: () => inicioDoAno() },
    { id: '12m', rotulo: '12 meses', inicio: () => diasAtras(364) },
];

export const PERIODO_PADRAO = '30d';
