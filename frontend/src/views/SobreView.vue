<script setup>
import { config } from '../lib/config.js';
</script>

<template>
    <article class="mx-auto max-w-3xl space-y-6 text-[15px] leading-relaxed">
        <header>
            <h1 class="text-2xl font-semibold">Sobre os dados</h1>
            <p class="mt-1 text-tinta-2">De onde vêm os números do painel e como interpretá-los.</p>
        </header>

        <section class="cartao space-y-3 p-5">
            <h2 class="text-lg font-semibold">Fonte</h2>
            <p>
                Os focos vêm do <a class="underline" href="https://terrabrasilis.dpi.inpe.br/queimadas/portal/" target="_blank" rel="noopener">Programa Queimadas do INPE</a>,
                que publica os dados abertamente, sem necessidade de cadastro ou chave de API, em
                <code class="rounded bg-superficie-2 px-1 text-sm">dataserver-coids.inpe.br/queimadas/queimadas/focos/csv/</code>.
                O painel importa esses arquivos para um banco próprio; o navegador nunca consulta o INPE diretamente.
            </p>
            <ul class="list-disc space-y-1 pl-5">
                <li><strong>Diário:</strong> um CSV por dia com todos os satélites. O arquivo do dia corrente é atualizado ao longo do dia; o painel o reimporta a cada hora.</li>
                <li><strong>Anual (satélite de referência):</strong> usado para carregar o histórico desde 2003.</li>
                <li><strong>Mensal:</strong> todos os satélites, usado para anos que ainda não têm arquivo anual.</li>
            </ul>
            <p class="text-sm text-tinta-2">
                Datas e horas estão em GMT (UTC), como nos arquivos do INPE. Em Cuiabá são 4 horas a menos.
            </p>
        </section>

        <section class="cartao space-y-3 p-5">
            <h2 class="text-lg font-semibold">O que é um foco</h2>
            <p>
                Um foco é um pixel de imagem de satélite com temperatura anormalmente alta. Ele indica fogo ativo naquele ponto,
                mas não equivale a uma queimada: um mesmo incêndio grande gera vários focos, e um fogo pequeno ou encoberto por
                nuvens pode não ser detectado. A resolução vai de 375 m a alguns quilômetros, conforme o satélite.
            </p>
        </section>

        <section class="cartao space-y-3 p-5">
            <h2 class="text-lg font-semibold">Por que filtrar pelo satélite de referência</h2>
            <p>
                O INPE recebe imagens de cerca de dez satélites (GOES, NOAA-20/21, NPP, METOP, TERRA, AQUA...), cada um com
                resolução, horário de passagem e sensibilidade diferentes. Somar todos eles infla os números, porque o mesmo fogo é
                contado várias vezes, e torna a contagem dependente de quantos satélites estavam em operação em cada ano.
            </p>
            <p>
                Para que as séries históricas sejam comparáveis, o INPE adota um <strong>satélite de referência</strong>: hoje é o
                sensor MODIS a bordo do AQUA, na passagem do início da tarde (<code class="rounded bg-superficie-2 px-1 text-sm">{{ config.sateliteReferencia }}</code>).
                Ele usa sempre o mesmo método e horário, então a variação de um ano para outro reflete o fogo, e não a mudança de satélites.
            </p>
            <p>
                Por isso o painel abre com o satélite de referência selecionado. A opção <em>Todos os satélites</em> é útil para ver a
                distribuição espacial mais completa de um dia ou semana, mas não deve ser usada para comparar períodos.
            </p>
        </section>

        <section class="cartao space-y-3 p-5">
            <h2 class="text-lg font-semibold">Campos de cada foco</h2>
            <dl class="grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_1fr]">
                <dt class="font-medium">FRP</dt>
                <dd class="text-tinta-2">Potência radiativa do fogo, em megawatts. Indica a intensidade.</dd>
                <dt class="font-medium">Risco de fogo</dt>
                <dd class="text-tinta-2">Índice de 0 a 1 do modelo do INPE, calculado com chuva, temperatura, umidade e vegetação.</dd>
                <dt class="font-medium">Dias sem chuva</dt>
                <dd class="text-tinta-2">Dias consecutivos sem precipitação no local do foco.</dd>
                <dt class="font-medium">Bioma</dt>
                <dd class="text-tinta-2">Mato Grosso tem áreas de Amazônia, Cerrado e Pantanal.</dd>
            </dl>
            <p class="text-sm text-tinta-2">
                Os arquivos anuais do satélite de referência não trazem FRP, risco de fogo nem dias sem chuva. Por isso esses campos
                aparecem vazios em períodos antigos.
            </p>
        </section>
    </article>
</template>
