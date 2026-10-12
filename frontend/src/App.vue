<script setup>
import { config } from './lib/config.js';
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-borda bg-superficie">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3">
                <RouterLink :to="{ name: 'painel' }" class="flex items-center gap-2.5">
                    <img src="/favicon.svg" alt="" class="size-7" />
                    <span class="leading-tight">
                        <span class="block text-base font-semibold">Focos de Queimadas</span>
                        <span class="block text-xs text-tinta-2">Mato Grosso · dados abertos do INPE</span>
                    </span>
                </RouterLink>

                <nav class="flex items-center gap-1 text-sm">
                    <RouterLink
                        :to="{ name: 'painel' }"
                        class="rounded-md px-3 py-1.5 text-tinta-2 hover:bg-superficie-2"
                        exact-active-class="bg-superficie-2 !text-tinta"
                    >
                        Painel
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'sobre' }"
                        class="rounded-md px-3 py-1.5 text-tinta-2 hover:bg-superficie-2"
                        active-class="bg-superficie-2 !text-tinta"
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
                </p>
                <a v-if="config.repositorio" class="underline" :href="config.repositorio" target="_blank" rel="noopener">Código-fonte no GitHub</a>
            </div>
        </footer>
    </div>
</template>
