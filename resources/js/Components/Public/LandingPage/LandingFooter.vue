<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <footer class="veshop-footer py-8">
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 text-sm sm:px-6 lg:px-8 md:flex-row md:items-center md:justify-between">
            <p>&copy; {{ year }} Veshop. Todos os direitos reservados.</p>
            <p>Gestão completa para varejos e comércios.</p>
        </div>
    </footer>
</template>
