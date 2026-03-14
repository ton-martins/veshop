<script setup>
import { ref } from 'vue';

const faqs = [
    {
        id: 'faq-implantacao',
        question: 'Em quanto tempo consigo implantar o Veshop?',
        answer:
            'A configuração inicial é guiada pelo nosso time. Em geral, os primeiros processos operacionais já podem ser usados em poucos dias.',
    },
    {
        id: 'faq-multinicho',
        question: 'O Veshop atende mais de um tipo de negócio?',
        answer:
            'Sim. O sistema permite parametrização por segmento e regras comerciais por empresa, filial e perfil de operação.',
    },
    {
        id: 'faq-fiscal',
        question: 'O sistema possui recursos fiscais e relatórios?',
        answer:
            'Sim. O Veshop apoia emissão fiscal, rotinas de apuração e relatórios gerenciais para acompanhamento de vendas, estoque e financeiro.',
    },
    {
        id: 'faq-suporte',
        question: 'Como funciona o suporte e o treinamento?',
        answer:
            'Você conta com onboarding consultivo, base de apoio e atendimento especializado para a evolução contínua da equipe.',
    },
];

const openedFaqId = ref('faq-multinicho');

const toggleFaq = (faqId) => {
    openedFaqId.value = openedFaqId.value === faqId ? '' : faqId;
};
</script>

<template>
    <section class="section faq-section">
        <div class="container">
            <div class="row align-items-center justify-content-center text-center">
                <div class="col-lg-7">
                    <div class="title-sm">
                        <span>FAQ</span>
                    </div>
                    <div class="section-title-border mt-3"></div>
                    <div class="faq-title mt-4">
                        <h2 class="fw-semibold text-primary">Perguntas frequentes</h2>
                    </div>
                </div>
            </div>

            <div class="accordion" id="accordionExample1">
                <div class="row align-items-center justify-content-between mt-5">
                    <div class="col-lg-5">
                        <h6 class="text-primary ms-3">Dúvidas comuns:</h6>
                        <p class="ms-3 mb-4">
                            Reunimos as principais perguntas sobre implantação, suporte e recursos do Veshop para facilitar sua avaliação.
                        </p>

                        <div
                            v-for="faq in faqs"
                            :key="faq.id"
                            class="accordion-item border-bottom"
                            :class="{
                                'rounded-0': faq.id === 'faq-implantacao' || faq.id === 'faq-suporte',
                                'is-open': openedFaqId === faq.id,
                            }"
                        >
                            <h6 class="accordion-header">
                                <button
                                    type="button"
                                    class="accordion-button py-3"
                                    :class="{ collapsed: openedFaqId !== faq.id }"
                                    :aria-expanded="openedFaqId === faq.id"
                                    @click.prevent="toggleFaq(faq.id)"
                                >
                                    {{ faq.question }}
                                </button>
                            </h6>

                            <div v-show="openedFaqId === faq.id" class="faq-answer shadow-sm">
                                <div class="accordion-body">
                                    <span class="text-muted">{{ faq.answer }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="contact-box shadow-none rounded-2">
                            <div class="row align-items-center justify-content-between">
                                <div class="col-lg-12">
                                    <div class="custom-form p-3">
                                        <h5 class="pb-5 text-primary">Envie sua dúvida:</h5>
                                        <form @submit.prevent>
                                            <div class="row">
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input id="name" type="text" class="form-control" placeholder="Nome" required />
                                                    </div>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="mb-3">
                                                        <input id="email" type="email" class="form-control" placeholder="E-mail" required />
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <input id="subject" type="text" class="form-control" placeholder="Assunto" required />
                                                    </div>
                                                </div>
                                                <div class="col-lg-12">
                                                    <div class="mb-3">
                                                        <textarea
                                                            id="comments"
                                                            rows="5"
                                                            class="form-control"
                                                            required
                                                            placeholder="Mensagem"
                                                        ></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-12">
                                                    <button type="submit" class="btn btn-primary fs-6">
                                                        Enviar mensagem
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.faq-section .accordion-button {
    text-transform: none;
}

.faq-answer {
    display: block;
}
</style>