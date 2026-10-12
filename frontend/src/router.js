import { createRouter, createWebHistory } from 'vue-router';
import PainelView from './views/PainelView.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'painel', component: PainelView },
        {
            path: '/municipios/:id(\\d+)',
            name: 'municipio',
            component: () => import('./views/MunicipioView.vue'),
            props: (rota) => ({ id: Number(rota.params.id) }),
        },
        { path: '/sobre', name: 'sobre', component: () => import('./views/SobreView.vue') },
        { path: '/:caminho(.*)*', redirect: { name: 'painel' } },
    ],
    scrollBehavior: (para, de) => (para.name === de.name ? false : { top: 0 }),
});
