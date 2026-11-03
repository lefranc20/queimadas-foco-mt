import { numero } from './formato.js';

/** Até esse tempo sem nova detecção, o fogo ainda é tratado como provavelmente ativo. */
const HORAS_ATIVO = 6;

export const NIVEIS = {
    atencao: { rotulo: 'Atenção', icone: '▲', classe: 'text-status-alta' },
    acompanhar: { rotulo: 'Acompanhar', icone: '●', classe: 'text-status-aviso' },
    isolado: { rotulo: 'Ponto isolado', icone: '○', classe: 'text-tinta-2' },
    sem_deteccao: { rotulo: 'Sem detecção recente', icone: '○', classe: 'text-tinta-2' },
};

export function horasDesde(dataHoraGmt, agora = Date.now()) {
    return (agora - new Date(`${dataHoraGmt.replace(' ', 'T')}Z`)) / 3600000;
}

/** Frase que resume o que o nível significa. */
export function descricaoNivel(nivel, ultimaDeteccao, agora) {
    if (nivel === 'atencao') {
        return horasDesde(ultimaDeteccao, agora) <= HORAS_ATIVO ? 'Provável incêndio ativo' : 'Provável incêndio recente';
    }
    if (nivel === 'acompanhar') return 'Focos agrupados ou repetidos';
    if (nivel === 'isolado') return 'Detecção única, pode ser um fogo pequeno';
    return 'Nenhum foco nas últimas 24 horas';
}

export const focos = (total) => `${numero(total)} ${total === 1 ? 'foco' : 'focos'}`;

/** "49 focos em 4 h · 2 satélites · 24 × 14 km" */
export function detalhesSituacao(situacao) {
    const partes = [focos(situacao.total)];

    if (situacao.duracao_horas >= 1) partes[0] += ` em ${Math.round(situacao.duracao_horas)} h`;
    partes.push(`${situacao.satelites} ${situacao.satelites === 1 ? 'satélite' : 'satélites'}`);

    const [altura, largura] = situacao.extensao_km;
    if (altura >= 1 || largura >= 1) partes.push(`${Math.round(altura)} × ${Math.round(largura)} km`);

    return partes.join(' · ');
}

/** "há 35 min", "há 2 h 14 min", "há 3 d 2 h" */
export function tempoDesde(dataHoraGmt, agora) {
    const minutos = Math.max(Math.round(horasDesde(dataHoraGmt, agora) * 60), 0);

    if (minutos < 60) return `há ${minutos} min`;
    const horas = Math.floor(minutos / 60);
    return horas < 24 ? `há ${horas} h ${minutos % 60} min` : `há ${Math.floor(horas / 24)} d ${horas % 24} h`;
}
