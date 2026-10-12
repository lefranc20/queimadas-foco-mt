/** Configuração do build (variáveis VITE_* do .env). */
export const config = {
    // Endereço do backend. Vazio = mesma origem (ou o proxy do Vite em desenvolvimento).
    urlApi: (import.meta.env.VITE_API_URL || '').replace(/\/$/, ''),
    sateliteReferencia: import.meta.env.VITE_SATELITE_REFERENCIA || 'AQUA_M-T',
    repositorio: import.meta.env.VITE_REPOSITORIO || '',
};
