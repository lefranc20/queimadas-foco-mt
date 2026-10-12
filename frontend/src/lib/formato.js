const numeroFormatado = new Intl.NumberFormat('pt-BR');
const decimalFormatado = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
const horaCuiaba = new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Cuiaba', hour: '2-digit', minute: '2-digit' });
const dataHoraLocal = new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
const MESES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

export const numero = (valor) => (valor === null || valor === undefined ? '–' : numeroFormatado.format(valor));

export const decimal = (valor) => (valor === null || valor === undefined ? '–' : decimalFormatado.format(valor));

/** "2026-09-15" -> "15/09/2026", sem passar por Date (evita deslocamento de fuso). */
export function data(iso) {
    if (!iso) return '–';
    const [ano, mes, dia] = iso.slice(0, 10).split('-');
    return `${dia}/${mes}/${ano}`;
}

/** Rótulo curto para eixos: "2026-09-15" -> "15 set", "2026-09" -> "set/26". */
export function periodoCurto(periodo) {
    const [ano, mes, dia] = periodo.split('-');
    return dia ? `${Number(dia)} ${MESES[mes - 1]}` : `${MESES[mes - 1]}/${ano.slice(2)}`;
}

/** Rótulo longo para tooltips e tabelas. */
export function periodoLongo(periodo) {
    const [ano, mes, dia] = periodo.split('-');
    return dia ? `${dia}/${mes}/${ano}` : `${MESES[mes - 1]} de ${ano}`;
}

/** "2026-09-15 18:00:00" (GMT) -> "15/09/2026 18:00 GMT (14:00 em Cuiabá)" */
export function dataHoraGmt(valor) {
    const instante = new Date(`${valor.replace(' ', 'T')}Z`);
    return `${data(valor)} ${valor.slice(11, 16)} GMT (${horaCuiaba.format(instante)} em Cuiabá)`;
}

export const dataHora = (iso) => (iso ? dataHoraLocal.format(new Date(iso)) : '–');

export function variacao(valor) {
    if (valor === null || valor === undefined) return null;
    const sinal = valor > 0 ? '+' : '';
    return `${sinal}${decimalFormatado.format(valor)}%`;
}

export function escaparHtml(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
