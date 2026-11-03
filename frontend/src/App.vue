<script setup>
import { config } from './lib/config.js';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <!-- Banner: a foto é a mesma nos dois temas, então o texto é sempre branco sobre a camada escura -->
        <header class="relative overflow-hidden border-b border-borda bg-neutral-900 text-white">
            <img src="/imagens/panorama-chapada.webp" alt="" class="absolute inset-0 size-full object-cover" />
            <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/25 to-black/30"></div>

            <div class="relative mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-6 sm:py-10">
                <RouterLink :to="{ name: 'painel' }" class="flex items-center gap-3">
                    <img
                        src="/bandeiras/mato-grosso.svg"
                        alt="Bandeira de Mato Grosso"
                        class="h-10 w-auto rounded-sm shadow-md ring-1 ring-white/40 sm:h-12"
                    />
                    <span class="leading-tight">
                        <span class="block text-lg font-semibold sm:text-xl">Focos de Queimadas</span>
                        <span class="block text-sm text-white/85">Mato Grosso · dados abertos do INPE</span>
                    </span>
                </RouterLink>

                <nav class="flex items-center gap-1 text-sm">
                    <RouterLink
                        :to="{ name: 'painel' }"
                        class="rounded-md px-3 py-1.5 whitespace-nowrap text-white/85 hover:bg-white/15"
                        exact-active-class="bg-white/20 !text-white"
                    >
                        Painel
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'agora' }"
                        class="rounded-md px-3 py-1.5 whitespace-nowrap text-white/85 hover:bg-white/15"
                        active-class="bg-white/20 !text-white"
                    >
                        Agora
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'sobre' }"
                        class="rounded-md px-3 py-1.5 whitespace-nowrap text-white/85 hover:bg-white/15"
                        active-class="bg-white/20 !text-white"
                    >
                        Sobre os dados
                    </RouterLink>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-5">
            <RouterView />
        </main>

        <footer class="border-t border-borda">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-tinta-2">
                <p>
                    Fonte: <a class="underline" href="https://terrabrasilis.dpi.inpe.br/queimadas/portal/" target="_blank" rel="noopener">Programa Queimadas – INPE</a>.
                    Malhas e códigos de município: <a class="underline" href="https://servicodados.ibge.gov.br/api/docs" target="_blank" rel="noopener">IBGE</a>.
                    Clima: <a class="underline" href="https://open-meteo.com/" target="_blank" rel="noopener">Open-Meteo</a> (CC BY 4.0).
                </p>
                <a v-if="config.repositorio" class="underline" :href="config.repositorio" target="_blank" rel="noopener">Código-fonte no GitHub</a>
            </div>
        </footer>
    </div>
</template>
