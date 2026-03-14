<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <footer class="section footer-part-3 py-5 footer-part">
        <div class="container">
            <div class="row my-5 justify-content-between">
                <div class="col-lg-3 align-self-start">
                    <div class="logo">
                        <a class="fs-4 text-light ls-1 fw-bold" href="#home">
                            <span class="veshop-system-logo-wrap me-2">
                                <img
                                    src="/brand/icone-veshop.png"
                                    alt="Ícone Veshop"
                                    class="veshop-system-logo"
                                />
                            </span>
                            VESHOP
                        </a>
                    </div>
                    <div class="d-flex mt-4">
                        <i class="ri-phone-fill text-light"></i>
                        <a href="#!" class="text-white-50 ms-3 fs-xl">- 123-4567-890</a>
                    </div>
                    <div class="d-flex mt-3">
                        <i class="ri-mail-send-fill text-light"></i>
                        <a href="#!" class="text-white-50 ms-3 fs-xl">- suporte@veshop.app</a>
                    </div>
                    <div class="footer-icon-3 ms-lg-auto me-lg-5">
                        <ul class="d-flex mt-4">
                            <li>
                                <a href="#!" class="text-light me-2">
                                    <i class="ri-facebook-fill p-2 border rounded-circle text-white-50"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2">
                                    <i class="ri-instagram-fill p-2 border rounded-circle text-white-50"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2">
                                    <i class="ri-twitter-fill p-2 border rounded-circle text-white-50"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2">
                                    <i class="ri-github-fill p-2 border rounded-circle text-white-50"></i>
                                </a>
                            </li>
                            <li>
                                <a href="#!" class="text-light mx-2">
                                    <i class="ri-google-fill p-2 border rounded-circle text-white-50"></i>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-2 col-md-4 col-12">
                    <h5 class="text-light">Navegação:</h5>
                    <ul class="list-unstyled mt-4">
                        <li><a href="#about" class="text-white-50">Sobre</a></li>
                        <li><a href="#services" class="text-white-50">Módulos</a></li>
                        <li><a href="#price" class="text-white-50">Planos</a></li>
                        <li><a href="#testimonial" class="text-white-50">Clientes</a></li>
                    </ul>
                </div>

                <div class="col-lg-2 col-md-4 col-12">
                    <h5 class="text-light fs-5">Institucional:</h5>
                    <ul class="list-unstyled mt-4">
                        <li><a href="#!" class="text-white-50">Termos de uso</a></li>
                        <li><a href="#!" class="text-white-50">Política de privacidade</a></li>
                        <li><a href="#!" class="text-white-50">Base de conhecimento</a></li>
                        <li><a href="#!" class="text-white-50">Atualizações</a></li>
                        <li><a href="#!" class="text-white-50">Central de ajuda</a></li>
                    </ul>
                </div>

                <div class="col-lg-3">
                    <h5 class="text-light">Receba novidades do Veshop:</h5>
                    <div class="form-button mt-4">
                        <form action="" class="d-flex align-items-center">
                            <input type="email" class="form-control" placeholder="Digite seu e-mail" />
                            <a href="#!" class="me-2"><i class="ri-send-plane-2-line"></i></a>
                        </form>
                    </div>
                    <div class="d-flex mt-4">
                        <i class="ri-twitter-fill fs-5 me-3 text-success"></i>
                        <p class="text-white-50">
                            Conteúdos práticos sobre ERP, gestão comercial e desempenho operacional.
                        </p>
                    </div>
                    <a href="#!" class="text-light">Conhecer mais</a>
                </div>
            </div>
        </div>
    </footer>
    <div class="footer-copyright py-3 bg-dark">
        <div class="copy-info text-center text-white-50">
            © {{ year }} Veshop -
            <a href="#" class="text-white-50 text-decoration-underline">ERP para comércio e varejo</a>
        </div>
    </div>
</template>

<style scoped>
.veshop-system-logo {
    width: 22px;
    height: 22px;
    object-fit: contain;
    vertical-align: middle;
}

.veshop-system-logo-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #073341;
    border: 1px solid rgba(7, 51, 65, 0.72);
    box-shadow: 0 14px 26px -20px rgba(2, 17, 22, 0.82);
    vertical-align: middle;
}
</style>
