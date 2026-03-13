<script setup>
const niches = [
    'Lojas de roupas',
    'Bazares',
    'Mercados locais',
    'Autoelétricas',
    'Papelarias',
    'Perfumarias',
    'Casa e construção',
    'Pet shops',
];
</script>

<template>
    <section id="nichos" class="mx-auto w-full max-w-6xl px-4 pb-6 pt-14 sm:px-6 lg:px-8 lg:pt-16">
        <div class="veshop-card p-6 sm:p-7">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500">Nichos atendidos</p>
            <h2 class="mt-3 font-display text-2xl font-extrabold text-slate-900 sm:text-3xl">
                Feito para o varejo real, não para um segmento único.
            </h2>
            <div class="mt-6 flex flex-wrap gap-3">
                <span
                    v-for="niche in niches"
                    :key="niche"
                    class="veshop-chip rounded-full px-4 py-2 text-sm font-semibold"
                >
                    {{ niche }}
                </span>
            </div>
        </div>
    </section>
</template>
