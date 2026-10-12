/**
 * Os plugins markercluster e heat esperam o Leaflet no objeto global "L".
 * Por isso eles são importados dinamicamente, só depois de o L estar exposto.
 */
import L from 'leaflet';

let plugins = null;

export function carregarLeaflet() {
    window.L = L;
    plugins ??= Promise.all([import('leaflet.markercluster'), import('leaflet.heat')]);

    return plugins.then(() => L);
}
