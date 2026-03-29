# Etapa 1

## Configurar ambiente de desenvolvimento

Para iniciar a integração das soluções de pagamento do Mercado Pago, é necessário preparar seu ambiente de desenvolvimento com uma série de configurações que permitirão acessar as funcionalidades do Mercado Pago a partir do backend.

A seguir, veja como instalar e configurar o SDK oficial do Mercado Pago:

> SERVER_SIDE
>
> h2
>
> Instalar o SDK do Mercado Pago

O **SDK de backend** é projetado para gerenciar as operações do lado do servidor, permitindo criar e gerenciar :toolTipComponent[preferências de pagamento]{content="Uma preferência de pagamento é um objeto que reúne informações sobre o produto ou serviço pelo qual você deseja cobrar. No ecossistema do Mercado Pago, esse objeto é denominado `preference`."}, processar transações e realizar outras operações críticas de forma segura.

> NOTE
> 
> Se preferir, você pode baixar os SDKs do Mercado Pago em nossas [bibliotecas oficiais](/developers/pt/docs/sdks-library/server-side).

Instale o SDK do Mercado Pago na linguagem que melhor se adapta à sua integração, utilizando um gerenciador de dependências, conforme demonstrado a seguir.

[[[
```php
===
Para instalar o SDK, execute o seguinte comando no seu terminal utilizando o [Composer](https://getcomposer.org/download):
===
php composer.phar require "mercadopago/dx-php"
```
```node
===
Para instalar o SDK, execute o seguinte comando no seu terminal utilizando [npm](https://www.npmjs.com/get-npm):
===
npm install mercadopago
```
```java
===
Para instalar o SDK em seu projeto [Maven](http://maven.apache.org/install.html), adicione a seguinte dependência ao seu arquivo <code>pom.xml</code> e execute <code>maven install</code> na linha de comando do seu terminal:
===
<dependency>
  <groupId>com.mercadopago</groupId>
  <artifactId>sdk-java</artifactId>
  <version>2.1.7</version>
</dependency>
```
```ruby
===
Para instalar o SDK, execute o seguinte comando no seu terminal utilizando [Gem](https://rubygems.org/gems/mercadopago-sdk):
===
gem install mercadopago-sdk
```
```csharp
===

Para instalar o SDK, execute o seguinte comando no seu terminal utilizando [NuGet](https://docs.microsoft.com/pt-br/nuget/reference/nuget-exe-cli-reference):

===
nuget install mercadopago-sdk
```
```python
===
Para instalar o SDK, execute o seguinte comando no seu terminal utilizando [Pip](https://pypi.org/project/mercadopago/):
===
pip3 install mercadopago
```
```go
go get -u github.com/mercadopago/sdk-go
```
]]]

> SERVER_SIDE
>
> h2
>
> Inicializar biblioteca do Mercado Pago

A seguir, crie um arquivo principal (_main_) no _backend_ do seu projeto com a linguagem de programação que você está utilizando. Insira o seguinte código, substituindo o valor `TEST_ACCESS_TOKEN` pelo :toolTipComponent[Access Token de teste]{content="Chave privada de testes da aplicação criada no Mercado Pago e que é utilizada no _backend_. Você pode acessá-la através de *Suas integrações > Dados da integração*, indo até a seção *Credenciales* (localizada à direita da tela) e clicando em *Teste*. Alternativamente, você pode acessá-la também a partir de *Suas integrações > Dados da integração > Testes > Credenciais de teste*."}.

[[[
```php
<?php
// SDK do Mercado Pago
use MercadoPago\MercadoPagoConfig;
// Adicione credenciais
MercadoPagoConfig::setAccessToken("TEST_ACCESS_TOKEN");
?>
```
```node
// SDK do Mercado Pago
import { MercadoPagoConfig, Preference } from 'mercadopago';
// Adicione credenciais
const client = new MercadoPagoConfig({ accessToken: 'YOUR_ACCESS_TOKEN' });
```
```java
// SDK do Mercado Pago
import com.mercadopago.MercadoPagoConfig;
// Adicione credenciais
MercadoPagoConfig.setAccessToken("TEST_ACCESS_TOKEN");
```
```ruby
# SDK do Mercado Pago
require 'mercadopago'
# Adicione credenciais
sdk = Mercadopago::SDK.new('TEST_ACCESS_TOKEN')
```
```csharp
// SDK do Mercado Pago
 using MercadoPago.Config;
 // Adicione credenciais
MercadoPagoConfig.AccessToken = "TEST_ACCESS_TOKEN";
```
```python
# SDK do Mercado Pago
import mercadopago
# Adicione credenciais
sdk = mercadopago.SDK("TEST_ACCESS_TOKEN")
```
```go
import (
	"github.com/mercadopago/sdk-go/pkg/config"
)

cfg, err := config.New("{{ACCESS_TOKEN}}")
if err != nil {
	fmt.Println(err)
}
```
]]]

Depois dessas configurações, seu ambiente de desenvolvimento já está pronto para avançar com a configuração de uma preferência de pagamento.


## Etapa 2

> SERVER_SIDE
>
> h1
>
> Criar e configurar uma preferência de pagamento

Uma **preferência de pagamento** é um objeto que reúne informações sobre o produto ou serviço pelo qual você deseja cobrar. No ecossistema do Mercado Pago, esse objeto é denominado `preference`. Ao criar uma preferência de pagamento, é possível definir atributos essenciais, como preço, quantidade e métodos de pagamento, além de configurar outros aspectos do fluxo de pagamento.

Durante esta etapa, você também irá adicionar os **meios de pagamento** que deseja oferecer com o Checkout Pro, que por padrão inclui todos os meios de pagamento disponíveis no Mercado Pago.

Para configurar uma preferência de pagamento, utilize o método correspondente à `preference` no SDK de backend. É necessário **criar uma nova preferência de pagamento para cada pedido ou fluxo de pagamento** que você deseja iniciar.

Abaixo, você encontrará exemplos práticos de como implementar essa funcionalidade em seu backend utilizando o SDK, disponível em várias linguagens de programação. Certifique-se de preencher os atributos com informações precisas para detalhar cada transação e garantir um processo de pagamento eficiente.

> NOTE
>
> Esses atributos permitem ajustar parcelas, excluir determinados meios de pagamento, modificar a data de vencimento de um pagamento, entre outras opções. Para personalizar sua preferência de pagamento, acesse as documentações da seção de **Configurações adicionais**. 

[[[
```php
<?php
$client = new PreferenceClient();
$preference = $client->create([
  "items"=> array(
  array(
  "title" => "Meu produto",
  "quantity" => 1,
  "unit_price" => 2000
  )
  )
]);

echo $preference
?>
```
```node
const preference = new Preference(client);

preference.create({
  body: {
  items: [
  {
  title: 'Meu produto',
  quantity: 1,
  unit_price: 2000
  }
  ],
  }
})
.then(console.log)
.catch(console.log);
```
```java
PreferenceItemRequest itemRequest =
  PreferenceItemRequest.builder()
  .id("1234")
  .title("Games")
  .description("PS5")
  .pictureUrl("http://picture.com/PS5")
  .categoryId("games")
  .quantity(2)
  .currencyId("BRL")
  .unitPrice(new BigDecimal("4000"))
  .build();
  List<PreferenceItemRequest> items = new ArrayList<>();
  items.add(itemRequest);
PreferenceRequest preferenceRequest = PreferenceRequest.builder()
.items(items).build();
PreferenceClient client = new PreferenceClient();
Preference preference = client.create(preferenceRequest);
```
```ruby
# Cria um objeto de preferência
preference_data = {
  items: [
  {
  title: 'Meu produto',
  unit_price: 75.56,
  quantity: 1
  }
  ]
}
preference_response = sdk.preference.create(preference_data)
preference = preference_response[:response]

# Este valor substituirá a string "<%= @preference_id %>" no seu HTML
@preference_id = preference['id']
```
```csharp
// Cria o objeto de request da preference
var request = new PreferenceRequest
{
  Items = new List<PreferenceItemRequest>
  {
  new PreferenceItemRequest
  {
  Title = "Meu produto",
  Quantity = 1,
  CurrencyId = "ARS",
  UnitPrice = 75.56m,
  },
  },
};

// Cria a preferência usando o client
var client = new PreferenceClient();
Preference preference = await client.CreateAsync(request);
```
```python
# Cria um item na preferência
preference_data = {
  "items": [
  {
  "title": "Meu produto",
  "quantity": 1,
  "unit_price": 75.76,
  }
  ]
}

preference_response = sdk.preference().create(preference_data)
preference = preference_response["response"]
```
```go
import (
  "github.com/mercadopago/sdk-go/pkg/preference"
)

client := preference.NewClient(cfg)

request := preference.Request{
	Items: []preference.ItemRequest{
		{
			Title: "Meu produto",
			Quantity: 1,
			UnitPrice: 75.76,
		},
	},
}

resource, err := client.Create(context.Background(), request)
if err != nil {
	fmt.Println(err)
	return
}

fmt.Println(resource)
```
]]]

## Obter o identificador da preferência

O identificador da preferência é um código único que representa uma transação específica para uma solicitação de pagamento. Para obtê-lo, você deve executar sua aplicação.

Na resposta, o **identificador da preferência** estará localizado na **propriedade ID**. Guarde esse valor com atenção, pois ele será **necessário na próxima etapa para integrar o pagamento** ao seu site ou aplicativo móvel.

Veja abaixo um exemplo de como o atributo ID, contendo o identificador de preferência, é exibido em uma resposta:

```
"id": "787997534-6dad21a1-6145-4f0d-ac21-66bf7a5e7a58"
```

### Escolher o tipo de integração

Após obter o ID da preferência, você deve prosseguir para a configuração do frontend. Para isso, escolha o tipo de integração que melhor atenda às suas necessidades, seja para um **site** ou um **aplicativo móvel**.

Selecione o tipo de integração que deseja realizar e siga os passos detalhados para completar a integração do Checkout Pro.
Selecione a opção de integração desejada e siga as instruções detalhadas para completar a integração do Checkout Pro.

---
future_product_avaible: 
 - card_avaible: true
 - card_icon: Laptop
 - card_title: Continuar a integração para sites
 - card_description: Oferece cobranças com redirecionamento para o Mercado Pago no seu site ou loja online.
 - card_button: /developers/pt/docs/checkout-pro/configure-back-urls
 - card_buttonDescription: Integração web
 - card_pillText: DISPONÍVEL
 - card_linkAvailable: false
 - card_linkProof:
 - card_linkProofDescription:
 - card_avaible: true
 - card_icon: Smartphone
 - card_title: Continuar a integração para aplicações móveis
 - card_description: Oferece cobranças com redirecionamento para o Mercado Pago no seu aplicativo para dispositivos móveis.
 - card_button: /developers/pt/docs/checkout-pro/mobile-integration
 - card_buttonDescription: Integração mobile
 - card_pillText: DISPONÍVEL
 - card_linkAvailable: false
 - card_linkProof:
 - card_linkProofDescription:
---


## Etapa 3

> SERVER_SIDE
>
> h1
>
> Criar e configurar uma preferência de pagamento

Uma **preferência de pagamento** é um objeto que reúne informações sobre o produto ou serviço pelo qual você deseja cobrar. No ecossistema do Mercado Pago, esse objeto é denominado `preference`. Ao criar uma preferência de pagamento, é possível definir atributos essenciais, como preço, quantidade e métodos de pagamento, além de configurar outros aspectos do fluxo de pagamento.

Durante esta etapa, você também irá adicionar os **meios de pagamento** que deseja oferecer com o Checkout Pro, que por padrão inclui todos os meios de pagamento disponíveis no Mercado Pago.

Para configurar uma preferência de pagamento, utilize o método correspondente à `preference` no SDK de backend. É necessário **criar uma nova preferência de pagamento para cada pedido ou fluxo de pagamento** que você deseja iniciar.

Abaixo, você encontrará exemplos práticos de como implementar essa funcionalidade em seu backend utilizando o SDK, disponível em várias linguagens de programação. Certifique-se de preencher os atributos com informações precisas para detalhar cada transação e garantir um processo de pagamento eficiente.

> NOTE
>
> Esses atributos permitem ajustar parcelas, excluir determinados meios de pagamento, modificar a data de vencimento de um pagamento, entre outras opções. Para personalizar sua preferência de pagamento, acesse as documentações da seção de **Configurações adicionais**. 

[[[
```php
<?php
$client = new PreferenceClient();
$preference = $client->create([
  "items"=> array(
  array(
  "title" => "Meu produto",
  "quantity" => 1,
  "unit_price" => 2000
  )
  )
]);

echo $preference
?>
```
```node
const preference = new Preference(client);

preference.create({
  body: {
  items: [
  {
  title: 'Meu produto',
  quantity: 1,
  unit_price: 2000
  }
  ],
  }
})
.then(console.log)
.catch(console.log);
```
```java
PreferenceItemRequest itemRequest =
  PreferenceItemRequest.builder()
  .id("1234")
  .title("Games")
  .description("PS5")
  .pictureUrl("http://picture.com/PS5")
  .categoryId("games")
  .quantity(2)
  .currencyId("BRL")
  .unitPrice(new BigDecimal("4000"))
  .build();
  List<PreferenceItemRequest> items = new ArrayList<>();
  items.add(itemRequest);
PreferenceRequest preferenceRequest = PreferenceRequest.builder()
.items(items).build();
PreferenceClient client = new PreferenceClient();
Preference preference = client.create(preferenceRequest);
```
```ruby
# Cria um objeto de preferência
preference_data = {
  items: [
  {
  title: 'Meu produto',
  unit_price: 75.56,
  quantity: 1
  }
  ]
}
preference_response = sdk.preference.create(preference_data)
preference = preference_response[:response]

# Este valor substituirá a string "<%= @preference_id %>" no seu HTML
@preference_id = preference['id']
```
```csharp
// Cria o objeto de request da preference
var request = new PreferenceRequest
{
  Items = new List<PreferenceItemRequest>
  {
  new PreferenceItemRequest
  {
  Title = "Meu produto",
  Quantity = 1,
  CurrencyId = "ARS",
  UnitPrice = 75.56m,
  },
  },
};

// Cria a preferência usando o client
var client = new PreferenceClient();
Preference preference = await client.CreateAsync(request);
```
```python
# Cria um item na preferência
preference_data = {
  "items": [
  {
  "title": "Meu produto",
  "quantity": 1,
  "unit_price": 75.76,
  }
  ]
}

preference_response = sdk.preference().create(preference_data)
preference = preference_response["response"]
```
```go
import (
  "github.com/mercadopago/sdk-go/pkg/preference"
)

client := preference.NewClient(cfg)

request := preference.Request{
	Items: []preference.ItemRequest{
		{
			Title: "Meu produto",
			Quantity: 1,
			UnitPrice: 75.76,
		},
	},
}

resource, err := client.Create(context.Background(), request)
if err != nil {
	fmt.Println(err)
	return
}

fmt.Println(resource)
```
]]]

## Obter o identificador da preferência

O identificador da preferência é um código único que representa uma transação específica para uma solicitação de pagamento. Para obtê-lo, você deve executar sua aplicação.

Na resposta, o **identificador da preferência** estará localizado na **propriedade ID**. Guarde esse valor com atenção, pois ele será **necessário na próxima etapa para integrar o pagamento** ao seu site ou aplicativo móvel.

Veja abaixo um exemplo de como o atributo ID, contendo o identificador de preferência, é exibido em uma resposta:

```
"id": "787997534-6dad21a1-6145-4f0d-ac21-66bf7a5e7a58"
```

### Escolher o tipo de integração

Após obter o ID da preferência, você deve prosseguir para a configuração do frontend. Para isso, escolha o tipo de integração que melhor atenda às suas necessidades, seja para um **site** ou um **aplicativo móvel**.

Selecione o tipo de integração que deseja realizar e siga os passos detalhados para completar a integração do Checkout Pro.
Selecione a opção de integração desejada e siga as instruções detalhadas para completar a integração do Checkout Pro.

---
future_product_avaible: 
 - card_avaible: true
 - card_icon: Laptop
 - card_title: Continuar a integração para sites
 - card_description: Oferece cobranças com redirecionamento para o Mercado Pago no seu site ou loja online.
 - card_button: /developers/pt/docs/checkout-pro/configure-back-urls
 - card_buttonDescription: Integração web
 - card_pillText: DISPONÍVEL
 - card_linkAvailable: false
 - card_linkProof:
 - card_linkProofDescription:
 - card_avaible: true
 - card_icon: Smartphone
 - card_title: Continuar a integração para aplicações móveis
 - card_description: Oferece cobranças com redirecionamento para o Mercado Pago no seu aplicativo para dispositivos móveis.
 - card_button: /developers/pt/docs/checkout-pro/mobile-integration
 - card_buttonDescription: Integração mobile
 - card_pillText: DISPONÍVEL
 - card_linkAvailable: false
 - card_linkProof:
 - card_linkProofDescription:
---

## Etapa 4

# Configurar URLs de retorno

A URL de retorno é o endereço para o qual o usuário é redirecionado após completar o pagamento, seja ele bem-sucedido, falho ou pendente. Esta URL deve ser uma página web controlável, como um servidor com domínio nomeado (DNS).

Esse processo é configurado através do atributo `back_urls` no backend, na preferência de pagamento associada à sua integração. Com este atributo, você pode definir para qual site o comprador será redirecionado, seja automaticamente ou através do botão "Voltar ao site", de acordo com o estado do pagamento.

Você pode configurar até três URLs de retorno diferentes, correspondendo aos cenários de pagamento **pendente**, **sucesso** ou **erro**.

> NOTE
>
> Em integrações _mobile_, recomendamos que as URLs de retorno sejam _deep links_. Para saber mais, acesse a [documentação Integração para aplicações móveis](/developers/pt/docs/checkout-pro/mobile-integration).

## Definir URLs de retorno

No seu código backend, configure a URL para a qual deseja que o Mercado Pago redirecione o usuário após a conclusão do processo de pagamento.

> NEUTRAL_MESSAGE
>
> Se preferir, você também pode configurar as URLs de retorno enviando um POST para a API [Criar preferência](/developers/pt/reference/online-payments/checkout-pro/preferences/create-preference/post) com o atributo `back_urls`, especificando as URLs para as quais o comprador deve ser redirecionado após finalizar o pagamento.

A seguir, compartilhamos exemplos de como incluir o atributo `back_urls` de acordo com a linguagem de programação que você está utilizando, além do detalhamento de cada um dos possíveis parâmetros.

[[[
```php
<?php
$preference = new MercadoPago\Preference();
//...
$preference->back_urls = array(
  "success" => "https://www.seu-site/success",
  "failure" => "https://www.seu-site/failure",
  "pending" => "https://www.seu-site/pending"
);
$preference->auto_return = "approved";
// ...
?>
```
```node
const preference = new Preference(client);
  preference.create({
  body: {
  // ...
  back_urls: {
  success: "https://www.seu-site/success",
  failure: "https://www.seu-site/failure",
  pending: "https://www.seu-site/pending"
  },
  auto_return: "approved",
  }
  })
  // ...
```
```java
PreferenceBackUrlsRequest backUrls =
// ...
  PreferenceBackUrlsRequest.builder()
  .success("https://www.seu-site/success")
  .pending("https://www.seu-site/pending")
  .failure("https://www.seu-site/failure")
  .build();

PreferenceRequest request = PreferenceRequest.builder().backUrls(backUrls).build();
// ...
```
```ruby
# ...
preference_data = {
  # ...
  back_urls: {
  success: 'https://www.seu-site/success',
  failure: 'https://www.seu-site/failure',
  pending: 'https://www.seu-site/pendings'
  },
  auto_return: 'approved'
  # ...
}
# ...
```
```csharp
var request = new PreferenceRequest
{
  // ...
  BackUrls = new PreferenceBackUrlsRequest
  {
  Success = "https://www.seu-site/success",
  Failure = "https://www.seu-site/failure",
  Pending = "https://www.seu-site/pendings",
  },
  AutoReturn = "approved",
};
```
```python
preference_data = {
  "back_urls": {
  "success": "https://www.seu-site/success",
  "failure": "https://www.seu-site/failure",
  "pending": "https://www.seu-site/pendings"
  },
  "auto_return": "approved"
}
```
]]]

| Atributo | Descrição |
|--------------|-----|
| `auto_return`| Os compradores são redirecionados automaticamente ao site quando o pagamento é aprovado. O valor padrão é `approved`. **O tempo de redirecionamento será de até 40 segundos e não poderá ser personalizado**. Por padrão, também será exibido um botão de "Voltar ao site".|
| `back_urls` | URL de retorno ao site. Os cenários possíveis são: <br>`success`: URL de retorno quando o pagamento é aprovado.<br>`pending`: URL de retorno quando o pagamento está pendente.<br>`failure`: URL de retorno quando o pagamento é rejeitado. |

## Resposta das URLs de retorno

As `back_urls` fornecem vários parâmetros úteis por meio de uma solicitação GET. A seguir, apresentamos um exemplo de resposta, acompanhado de uma explicação detalhada dos parâmetros incluídos nela.

```curl
GET /test?collection_id=106400160592&collection_status=rejected&payment_id=106400160592&status=rejected&external_reference=qweqweqwe&payment_type=credit_card&merchant_order_id=29900492508&preference_id=724484980-ecb2c41d-ee0e-4cf4-9950-8ef2f07d3d82&site_id=MLC&processing_mode=aggregator&merchant_account_id=null HTTP/1.1
Host: yourwebsite.com
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7
Accept-Encoding: gzip, deflate, br, zstd
Accept-Language: es-419,es;q=0.9
Connection: keep-alive
Referer: https://www.mercadopago.com/checkout/v1/payment/redirect/505f641c-cf04-4407-a7ad-8ca471419ee5/congrats/rejected/?preference-id=724484980-ecb2c41d-ee0e-4cf4-9950-8ef2f07d3d82&router-request-id=0edb64e3-d853-447a-bb95-4f810cbed7f7&p=f2e3a023dd16ac953e65c4ace82bb3ab
Sec-Ch-Ua: "Chromium";v="134", "Not:A-Brand";v="24", "Google Chrome";v="134"
Sec-Ch-Ua-Mobile: ?0
Sec-Ch-Ua-Platform: "macOS"
Sec-Fetch-Dest: document
Sec-Fetch-Mode: navigate
Sec-Fetch-Site: cross-site
Sec-Fetch-User: ?1
Upgrade-Insecure-Requests: 1
```

| Parâmetro | Descrição |
|-----------------------|------------------------------------------------------------------------------------------------|
| `payment_id` | ID (identificador) do pagamento do Mercado Pago. |
| `status` | Status do pagamento. Por exemplo: `approved` para um pagamento aprovado ou `pending` para um pagamento pendente. |
| `external_reference` | Referência para sincronização com seu sistema de pagamentos. |
| `merchant_order_id` | Identificador (ID) único da ordem de pagamento criada no Mercado Pago. |

### Resposta para meios de pagamento offline

Os meios de pagamento offline permitem que o comprador selecione um método que exija a utilização de um ponto de pagamento físico para concluir a transação. Nesse fluxo, o Mercado Pago gera um comprovante que o comprador deve apresentar no estabelecimento para realizar o pagamento. Após essa etapa, o comprador será redirecionado para a URL definida no atributo `back_urls` como `pending`.

Nesse momento, o pagamento estará em estado pendente, já que o comprador ainda precisa efetuar o pagamento presencialmente no estabelecimento indicado.

> Para pagamentos com o estado `pending`, sugerimos redirecionar o comprador para o seu site e fornecer orientações claras sobre como concluir o pagamento.

Assim que o pagamento for realizado no ponto físico com o comprovante gerado, o Mercado Pago será notificado, e o estado do pagamento será atualizado. Recomendamos que você [configure as notificações de pagamento](/developers/pt/docs/checkout-pro/payment-notifications) para que seu servidor receba essas atualizações e atualize o estado do pedido na sua base de dados.

> CLIENT_SIDE
>
> h1
>
> Adicionar o SDK ao frontend e inicializar o checkout

Uma vez configurado o backend, é necessário configurar o frontend para completar a experiência de pagamento do lado do cliente. Para isso, utilize o SDK MercadoPago.js, que permite capturar pagamentos diretamente no frontend de maneira segura.

Nesta seção, você aprenderá como incluir e inicializar corretamente o SDK, e como renderizar o botão de pagamento do Mercado Pago.

> Caso prefira, você pode baixar o SDK MercadoPago.js em nossas [bibliotecas oficiais](/developers/pt/docs/sdks-library/client-side/mp-js-v2).

:::::TabsComponent

::::TabComponent{title="Incluir o SDK com HTML/js"}
## Incluir o SDK com HTML/js

Para incluir o SDK MercadoPago.js na sua página HTML a partir de um **CDN (Content Delivery Network)**, adicione a tag `<script>` antes da tag `</body>` no seu arquivo HTML principal, conforme mostrado no exemplo abaixo:

```html
<!DOCTYPE html>
<html>
<head>
  <title>Minha Integração com Checkout Pro</title>
</head>
<body>

  <!-- Conteúdo da sua página -->

  <script src="https://sdk.mercadopago.com/js/v2"></script>

  <script>
  // Seu código JavaScript irá aqui
  </script>

</body>
</html>
```

## Inicializar o checkout a partir da preferência de pagamento

Após incluir o SDK no seu frontend, é necessário inicializá-lo e, em seguida, iniciar o checkout.

Para continuar, utilize sua credencial :toolTipComponent[Public Key de teste]{content="Chave pública de testes e que é utilizada no _frontend_ para acessar informações e criptografar dados, seja na fase de desenvolvimento ou na fase de testes. Você pode acessá-la através de **Suas integrações > Dados da integração > Testes > Credenciais de teste**."}.

> NOTE
>
> Se estiver desenvolvendo para outra pessoa, você poderá acessar as credenciais das aplicações que não administra. Para mais informações, consulte a seção [Compartilhar credenciais](/developers/pt/docs/checkout-pro/resources/credentials#bookmark_compartilhar_credenciais).

Você também precisará utilizar o identificador da preferência de pagamento que obteve como resposta em [Criar e configurar uma preferência de pagamento](/developers/pt/docs/checkout-pro/create-payment-preference).

Para inicializar o SDK via CDN, insira o código a seguir dentro da tag `<script>`. Substitua `YOUR_PUBLIC_KEY` pela `public_key` de produção da sua aplicação e `YOUR_PREFERENCE_ID` pelo **identificador da preferência de pagamento**.

```Javascript
<script src="https://sdk.mercadopago.com/js/v2"></script>
<script>
  // Configure sua chave pública do Mercado Pago
  const publicKey = "YOUR_PUBLIC_KEY";
  // Configure o ID de preferência que você deve receber do seu backend
  const preferenceId = "YOUR_PREFERENCE_ID";

  // Inicializa o SDK do Mercado Pago
  const mp = new MercadoPago(publicKey);

  // Cria o botão de pagamento
  const bricksBuilder = mp.bricks();
  const renderWalletBrick = async (bricksBuilder) => {
  await bricksBuilder.create("wallet", "walletBrick_container", {
  initialization: {
  preferenceId: "<PREFERENCE_ID>",
  }
});
  };

  renderWalletBrick(bricksBuilder);
</script>
```

> CLIENT_SIDE
>
> h2
>
> Criar um container HTML para o botão de pagamento

Por fim, adicione um _container_ ao código HTML para definir a localização onde o botão de pagamento do Mercado Pago será exibido. Para criar esse _container_, insira o seguinte elemento no HTML da página onde o componente será renderizado:

```html
<!-- Container para o botão de pagamento -->
<div id="walletBrick_container"></div>
```

## Renderizar o botão de pagamento

O SDK do Mercado Pago é responsável por renderizar automaticamente o botão de pagamento dentro do elemento definido, permitindo que o comprador seja redirecionado para um formulário de compra no ambiente do Mercado Pago. Veja um exemplo na imagem abaixo:

![Button](/images/cow/wallet-render-pt-v1.png)
::::

::::TabComponent{title="Instalar o SDK utilizando React"}
## Instalar o SDK utilizando React

Para integrar o SDK MercadoPago.js ao frontend do seu projeto React, siga os passos abaixo, certifique-se de que o **Node.js** e o **npm** estão instalados no sistema. Caso não estejam, faça o download através do [site oficial do Node.js](http://Node.js).

No seu terminal, execute o seguinte comando para criar uma nova aplicação React:

```
npx create-react-app my-mercadopago-app
```

Isso criará um novo diretório chamado `my-mercadopago-app` com uma estrutura básica de aplicação React.

### Instalar SDK MercadoPago.js

Instale a biblioteca SDK MercadoPago.js no diretório `my-mercadopago-app`. Você pode fazer isso executando o seguinte comando:

```
npm install @mercadopago/sdk-react
```

## Criar um componente para o botão de pagamento

Abra o arquivo `src/App.js` da sua aplicação React e atualize o conteúdo para integrar o componente `wallet` do Mercado Pago, que é o responsável por mostrar o botão de pagamento do Mercado Pago.

Para continuar, utilize sua credencial :toolTipComponent[Public Key de teste]{content="Chave pública de testes e que é utilizada no _frontend_ para acessar informações e criptografar dados, seja na fase de desenvolvimento ou na fase de testes. Você pode acessá-la através de **Suas integrações > Dados da integração > Testes > Credenciais de teste**."}.

> NOTE
>
> Se estiver desenvolvendo para outra pessoa, você poderá acessar as credenciais das aplicações que não administra. Para mais informações, consulte a seção [Compartilhar credenciais](/developers/pt/docs/checkout-pro/resources/credentials#bookmark_compartilhar_credenciais).

Você também precisará utilizar o identificador da preferência de pagamento que foi obtido como resposta em [Criar e configurar uma preferência de pagamento](/developers/pt/docs/checkout-pro/create-payment-preference).

A seguir, substitua o valor `YOUR_PUBLIC_KEY` pela sua chave e `YOUR_PREFERENCE_ID` pelo **identificador da preferência de pagamento** no arquivo `src/App.js`. Veja o exemplo abaixo.

```JavaScript
import React from 'react';
import { initMercadoPago, Wallet } from '@mercadopago/sdk-react';

// Inicialize o Mercado Pago com seu Public Key
initMercadoPago('YOUR_PUBLIC_KEY');

const App = () => {
  return (
  <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', marginTop: '50px' }}>
  <h1>Botão de Pagamento</h1>
  <p>Clique no botão para realizar o pagamento.</p>
  {/* Renderize o botão de pagamento */}
  <div style={{ width: '300px' }}>
  <Wallet initialization={{ preferenceId: 'YOUR_PREFERENCE_ID' }} />
  </div>
  </div>
  );
};

export default App;
```

## Renderizar o botão de pagamento

Ao executar a aplicação, o SDK do Mercado Pago irá renderizar o botão de pagamento, permitindo que o comprador seja redirecionado para o ambiente de compra, como mostrado na imagem abaixo:

![Button](/images/cow/wallet-render-pt-v1.png)
::::

:::::

<br>

Uma vez que você tenha finalizado a configuração no frontend, configure as [Notificações](/developers/pt/docs/checkout-pro/payment-notifications) para que seu servidor receba atualizações em tempo real sobre os eventos ocorridos na sua integração.

## Etapa 5

# Configurar notificações de pagamento

As notificações **Webhooks**, também conhecidas como **retornos de chamada web**, são um método eficaz que permite aos servidores do Mercado Pago enviar informações em **tempo real** quando ocorre um evento específico relacionado à sua integração.

Com os Webhooks, o seu sistema não precisa realizar consultas contínuas para buscar atualizações. Esse mecanismo transmite dados de maneira **passiva e automática**, utilizando solicitações **HTTP POST**. Assim, otimiza a comunicação e reduz a carga nos servidores.

Consulte o fluxo geral de uma notificação no diagrama abaixo.

![Diagram](/images/cow/notifications-diagrama-pt-v1.jpg)

A seguir, apresentamos um passo a passo para configurar as notificações de criação e atualização de pagamentos. Depois de configuradas, as notificações Webhook serão enviadas sempre que um pagamento for criado ou seu estado for modificado (Pendente, Rejeitado ou Aprovado).

> NOTE
>
> Esta documentação trata exclusivamente da configuração de notificações de pagamento, incluindo criações e atualizações, por meio do evento **Pagamentos**. Para obter informações sobre outros eventos de notificações disponíveis para configuração, consulte a [documentação de Notificações](/developers/pt/docs/checkout-pro/additional-content/notifications) geral.

 No processo de integração com o Mercado Pago, as notificações podem ser configuradas de duas maneiras:

| Tipo de Configuração | Descrição | Vantagens | Quando Usar |
|---|---|---|---|
| Configuração através de Suas integrações | Este método permite configurar notificações diretamente do seu Painel de Desenvolvedor. Você pode configurar notificações para cada uma de suas aplicações, identificar contas distintas, se necessário, e validar a origem da notificação através de uma assinatura secreta. | - Identificação simples de contas distintas, garantindo uma gestão adequada em ambientes diversos. <br> - Alta segurança ao validar a origem das notificações através de uma assinatura secreta, que garante a integridade da informação recebida. <br> - Mais versátil e eficaz para manter um controle centralizado e gerenciar a comunicação com as aplicações de maneira eficiente. | Recomendado para a maioria das integrações. |
| Configuração durante a criação de preferências | As notificações são configuradas para cada transação individualmente durante a criação da preferência. | - Ajustes específicos para cada transação. <br> - Flexibilidade em casos de necessidade de parâmetros dinâmicos obrigatórios. <br> - Ideal para integrações como plataformas de pagamento para múltiplos vendedores. | Conveniente em casos em que seja necessário enviar um *query parameter* dinâmico de forma obrigatória, além de ser adequado para integrações que funcionam como uma plataforma de pagamento para múltiplos vendedores. |

> RED_MESSAGE
>
> Importante
>
> As URLs configuradas durante a criação de um pagamento terão prioridade sobre aquelas configuradas através de Suas integrações.

:::::TabsComponent

::::TabComponent{title="Configuração através de Suas integrações"}
## Configuração através de Suas integrações
Você pode configurar notificações para cada uma de suas aplicações diretamente em [Suas integrações](/developers/panel/app) de maneira eficiente e segura. Nesta seção, explicaremos como:

1. Indicar as URLs de notificação e configurar eventos
2. Validar a origem de uma notificação
3. Simular o recebimento de uma notificação

### 1. Indicar URLs de notificação e configurar o evento

Para configurar notificações Webhooks, é necessário indicar as URLs para as quais as notificações serão enviadas.
Para fazer isso, siga o passo a passo abaixo:
1. Acesse [Suas integrações](/developers/panel/app) e selecione a aplicação integrada com o Checkout Pro para a qual você deseja ativar as notificações. 

![Application](/images/cow/not1-select-app-pt-v1.png)

2. No menu à esquerda, selecione **Webhooks > Configurar notificações** e configure a URL que será utilizada para recebê-las.

![Webhooks](/images/cow/not2-webhooks-pt-v1.png) 

3. Selecione a aba **Modo produtivo** e forneça uma `URL HTTPS` para receber notificações com sua integração produtiva. 

![URL](/images/cow/not3-url-pt-v1.png) 

4. Selecione o evento **Pagamentos** para receber notificações, que serão enviadas no formato `JSON` através de um `HTTPS POST` para a URL especificada anteriormente.

![Payment](/images/cow/not4-payment-pt-v1.png)

5.Por fim, clique em **Salvar configuração**. Isso gerará uma **chave secreta** exclusiva para a aplicação, utilizada para validar a autenticidade das notificações recebidas, assegurando que elas sejam provenientes do Mercado Pago. Vale ressaltar que essa chave não possui prazo de validade, mas recomenda-se sua renovação periódica como medida de segurança. Para renovar a chave, basta clicar no botão **Restabelecer**.

### 2. Simular o recebimento da notificação

Para garantir que as notificações sejam configuradas corretamente, é necessário simular o recebimento delas. Para isso, siga o passo a passo abaixo:

1. Após configurar as URLs e os eventos, clique em **Salvar configuração**.
2. Em seguida, clique em **Simular** para testar se a URL indicada está recebendo as notificações corretamente.
3. Na tela de simulação, selecione a URL que será testada, que pode ser **a URL de teste ou a de produção**.
4. Depois, escolha o **tipo de evento** e insira a **identificação** que será enviada no corpo da notificação (Data ID).
5. Por fim, clique em **Enviar teste** para verificar a solicitação, a resposta fornecida pelo servidor e a descrição do evento. Você receberá uma resposta semelhante ao exemplo abaixo, que representa o `body` da notificação recebida em seu servidor.

```
{
  "action": "payment.updated",
  "api_version": "v1",
  "data": {
  "id": "123456"
  },
  "date_created": "2021-11-01T02:02:02Z",
  "id": "123456",
  "live_mode": false,
  "type": "payment",
  "user_id": 724484980
}
```

### 3. Validar a origem da notificação

A validação da origem de uma notificação é fundamental para assegurar a segurança e a autenticidade das informações recebidas. Este processo ajuda a prevenir fraudes e garante que apenas notificações legítimas sejam processadas.

O Mercado Pago enviará ao seu servidor uma notificação semelhante ao exemplo abaixo para um alerta do tópico `payment`. Neste exemplo, está incluída a notificação completa, que contém os `query params`, o `body` e o `header` da notificação.

- **_Query params_**: São parâmetros de consulta que acompanham a URL. No exemplo, temos `data.id=123456` e `type=payment`. 
- **_Body_**: O corpo da notificação contém informações detalhadas sobre o evento, como `action`, `api_version`, `data`, `date_created`, `id`, `live_mode`, `type` e `user_id`. 
- **_Header_**: O cabeçalho contém metadados importantes, incluindo a assinatura secreta da notificação `x-signature`.

```
POST /test?data.id=123456&type=payment HTTP/1.1
Host: prueba.requestcatcher.com
Accept: */*
Accept-Encoding: *
Connection: keep-alive
Content-Length: 177
Content-Type: application/json
Newrelic: eyJ2IjpbMCwxXSwiZCI6eyJ0eSI6IkFwcCIsImFjIjoiOTg5NTg2IiwiYXAiOiI5NjA2MzYwOTQiLCJ0eCI6IjU3ZjI4YzNjOWE2ODNlZDYiLCJ0ciI6IjY0NjA0OTM3OWI1ZjA3MzMyZDdhZmQxMjEyM2I5YWE4IiwicHIiOjAuNzk3ODc0LCJzYSI6ZmFsc2UsInRpIjoxNzQyNTA1NjM4Njg0LCJ0ayI6IjE3MDk3MDcifX0=
Traceparent: 00-646049379b5f07332d7afd12123b9aa8-e7f77a41f687aecd-00
Tracestate: 1709707@nr=0-0-989586-960636094-e7f77a41f687aecd-57f28c3c9a683ed6-0-0.797874-1742505638684
User-Agent: restclient-node/4.15.3
X-Request-Id: bb56a2f1-6aae-46ac-982e-9dcd3581d08e
X-Rest-Pool-Name: /services/webhooks.js
X-Retry: 0
X-Signature: ts=1742505638683,v1=ced36ab6d33566bb1e16c125819b8d840d6b8ef136b0b9127c76064466f5229b
X-Socket-Timeout: 22000
{"action":"payment.updated","api_version":"v1","data":{"id":"123456"},"date_created":"2021-11-01T02:02:02Z","id":"123456","live_mode":false,"type":"payment","user_id":724484980}
```

A partir da notificação Webhook recebida, você poderá validar a autenticidade de sua origem. O Mercado Pago sempre incluirá a chave secreta nas notificações Webhooks que serão recebidas, o que permitirá validar sua autenticidade. Essa chave será enviada no _header_ `x-signature`, que será semelhante ao exemplo abaixo.

```
`ts=1742505638683,v1=ced36ab6d33566bb1e16c125819b8d840d6b8ef136b0b9127c76064466f5229b`
```

Para confirmar a validação, é necessário extrair a chave contida no _header_ e compará-la com a chave fornecida para sua aplicação em Suas integrações. Para isso, siga o passo a passo abaixo. Ao final, disponibilizamos nossos SDKs com exemplos de códigos completos para facilitar o processo.

1. Para extrair o timestamp (`ts`) e a chave (`v1`) do header `x-signature`, divida o conteúdo do _header_ pelo caractere “,", o que resultará em uma lista de elementos. O valor para o prefixo `ts` é o _timestamp_ (em milissegundos) da notificação e _v1_ é a chave encriptada. Seguindo o exemplo apresentado anteriormente, `ts=1742505638683` e `v1=ced36ab6d33566bb1e16c125819b8d840d6b8ef136b0b9127c76064466f5229b`.
2. Utilizando o _template_ abaixo, substitua os parâmetros com os dados recebidos na sua notificação.

```
id:[data.id_url];request-id:[x-request-id_header];ts:[ts_header];
```

- Os parâmetros com o sufixo `_url` vêm de _query params_. Exemplo: [data.id_url] será substituído pelo valor correspondente ao ID do evento (`data.id`). Este _query param_ pode ser encontrado na notificação recebida. No exemplo de notificação mencionado anteriormente, o `data.id_url` é `123456`.
- [x-request-id_header] deverá ser substituído pelo valor recebido no _header_ `x-request-id`. No exemplo de notificação mencionado anteriormente, o `x-request-id` é `bb56a2f1-6aae-46ac-982e-9dcd3581d08e`.
- [ts_header] será o valor `ts` extraído do _header_ `x-signature`. No exemplo de notificação mencionado anteriormente, o `ts` é `1742505638683`.
- Após aplicar os dados ao **template**, o resultado seria o seguinte
`id:123456;request-id:bb56a2f1-6aae-46ac-982e-9dcd3581d08e;ts:1742505638683;`

> RED_MESSAGE
>
> Importante
>
> Se algum dos valores apresentados no modelo anterior não estiver presente na notificação recebida, você deve removê-lo.

3. Em [Suas integrações](/developers/panel/app), selecione a aplicação integrada, clique em **Webhooks > Configurar notificação** e revele a chave secreta gerada.

![Signature](/images/cow/not6-signature-pt-v1.png) 

4. Gere a contrachave para a validação. Para fazer isso, calcule um [HMAC](https://pt.wikipedia.org/wiki/HMAC) com a função de `hash SHA256` em base hexadecimal, utilizando a **chave secreta** como chave e o template com os valores como mensagem.

[[[
```php
$cyphedSignature = hash_hmac('sha256', $data, $key);
```
```node
const crypto = require('crypto');
const cyphedSignature = crypto
  .createHmac('sha256', secret)
  .update(signatureTemplateParsed)
  .digest('hex'); 
```
```java
String cyphedSignature = new HmacUtils("HmacSHA256", secret).hmacHex(signedTemplate);
```
```python
import hashlib, hmac, binascii

cyphedSignature = binascii.hexlify(hmac_sha256(secret.encode(), signedTemplate.encode()))
```
]]]

5. Finalmente, compare a chave gerada com a chave extraída do _header_, certificando-se de que correspondam exatamente. Além disso, você pode usar o _timestamp_ extraído do header para compará-lo com um timestamp gerado no momento da recepção da notificação, a fim de estabelecer uma tolerância de atraso na recepção da mensagem.

A seguir, você pode ver exemplos de código completo:

[[[
```php
<?php
// Obtain the x-signature value from the header
$xSignature = $_SERVER['HTTP_X_SIGNATURE'];
$xRequestId = $_SERVER['HTTP_X_REQUEST_ID'];

// Obtain Query params related to the request URL
$queryParams = $_GET;

// Extract the "data.id" from the query params
$dataID = isset($queryParams['data.id']) ? $queryParams['data.id'] : '';

// Separating the x-signature into parts
$parts = explode(',', $xSignature);

// Initializing variables to store ts and hash
$ts = null;
$hash = null;

// Iterate over the values to obtain ts and v1
foreach ($parts as $part) {
  // Split each part into key and value
  $keyValue = explode('=', $part, 2);
  if (count($keyValue) == 2) {
  $key = trim($keyValue[0]);
  $value = trim($keyValue[1]);
  if ($key === "ts") {
  $ts = $value;
  } elseif ($key === "v1") {
  $hash = $value;
  }
  }
}

// Obtain the secret key for the user/application from Mercadopago developers site
$secret = "your_secret_key_here";

// Generate the manifest string
$manifest = "id:$dataID;request-id:$xRequestId;ts:$ts;";

// Create an HMAC signature defining the hash type and the key as a byte array
$sha = hash_hmac('sha256', $manifest, $secret);
if ($sha === $hash) {
  // HMAC verification passed
  echo "HMAC verification passed";
} else {
  // HMAC verification failed
  echo "HMAC verification failed";
}
?>
```
```javascript
// Obtain the x-signature value from the header
const xSignature = headers['x-signature']; // Assuming headers is an object containing request headers
const xRequestId = headers['x-request-id']; // Assuming headers is an object containing request headers

// Obtain Query params related to the request URL
const urlParams = new URLSearchParams(window.location.search);
const dataID = urlParams.get('data.id');

// Separating the x-signature into parts
const parts = xSignature.split(',');

// Initializing variables to store ts and hash
let ts;
let hash;

// Iterate over the values to obtain ts and v1
parts.forEach(part => {
  // Split each part into key and value
  const [key, value] = part.split('=');
  if (key && value) {
  const trimmedKey = key.trim();
  const trimmedValue = value.trim();
  if (trimmedKey === 'ts') {
  ts = trimmedValue;
  } else if (trimmedKey === 'v1') {
  hash = trimmedValue;
  }
  }
});

// Obtain the secret key for the user/application from Mercadopago developers site
const secret = 'your_secret_key_here';

// Generate the manifest string
const manifest = `id:${dataID};request-id:${xRequestId};ts:${ts};`;

// Create an HMAC signature
const hmac = crypto.createHmac('sha256', secret);
hmac.update(manifest);

// Obtain the hash result as a hexadecimal string
const sha = hmac.digest('hex');

if (sha === hash) {
  // HMAC verification passed
  console.log("HMAC verification passed");
} else {
  // HMAC verification failed
  console.log("HMAC verification failed");
}
```
```python
import hashlib
import hmac
import urllib.parse

# Obtain the x-signature value from the header
xSignature = request.headers.get("x-signature")
xRequestId = request.headers.get("x-request-id")

# Obtain Query params related to the request URL
queryParams = urllib.parse.parse_qs(request.url.query)

# Extract the "data.id" from the query params
dataID = queryParams.get("data.id", [""])[0]

# Separating the x-signature into parts
parts = xSignature.split(",")

# Initializing variables to store ts and hash
ts = None
hash = None

# Iterate over the values to obtain ts and v1
for part in parts:
  # Split each part into key and value
  keyValue = part.split("=", 1)
  if len(keyValue) == 2:
  key = keyValue[0].strip()
  value = keyValue[1].strip()
  if key == "ts":
  ts = value
  elif key == "v1":
  hash = value

# Obtain the secret key for the user/application from Mercadopago developers site
secret = "your_secret_key_here"

# Generate the manifest string
manifest = f"id:{dataID};request-id:{xRequestId};ts:{ts};"

# Create an HMAC signature defining the hash type and the key as a byte array
hmac_obj = hmac.new(secret.encode(), msg=manifest.encode(), digestmod=hashlib.sha256)

# Obtain the hash result as a hexadecimal string
sha = hmac_obj.hexdigest()
if sha == hash:
  # HMAC verification passed
  print("HMAC verification passed")
else:
  # HMAC verification failed
  print("HMAC verification failed")
```
```go
import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net/http"
	"strings"
)

func main() {
	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		// Obtain the x-signature value from the header
		xSignature := r.Header.Get("x-signature")
		xRequestId := r.Header.Get("x-request-id")

		// Obtain Query params related to the request URL
		queryParams := r.URL.Query()

		// Extract the "data.id" from the query params
		dataID := queryParams.Get("data.id")

		// Separating the x-signature into parts
		parts := strings.Split(xSignature, ",")

		// Initializing variables to store ts and hash
		var ts, hash string

		// Iterate over the values to obtain ts and v1
		for _, part := range parts {
			// Split each part into key and value
			keyValue := strings.SplitN(part, "=", 2)
			if len(keyValue) == 2 {
				key := strings.TrimSpace(keyValue[0])
				value := strings.TrimSpace(keyValue[1])
				if key == "ts" {
					ts = value
				} else if key == "v1" {
					hash = value
				}
			}
		}

		// Get secret key/token for specific user/application from Mercadopago developers site
		secret := "your_secret_key_here"

		// Generate the manifest string
		manifest := fmt.Sprintf("id:%v;request-id:%v;ts:%v;", dataID, xRequestId, ts)

		// Create an HMAC signature defining the hash type and the key as a byte array
		hmac := hmac.New(sha256.New, []byte(secret))
		hmac.Write([]byte(manifest))

		// Obtain the hash result as a hexadecimal string
		sha := hex.EncodeToString(hmac.Sum(nil))

if sha == hash {
  // HMAC verification passed
  fmt.Println("HMAC verification passed")
} else {
  // HMAC verification failed
  fmt.Println("HMAC verification failed")
}

	})
}
```
]]]
::::

::::TabComponent{title="Configuração ao criar preferências"}
## Configuração ao criar preferências
Durante o processo de criação de [preferências](/developers/pt/reference/online-payments/checkout-pro/preferences/create-preference/post), é possível configurar a URL de notificação de forma mais específica para cada pagamento utilizando o campo `notification_url`. 

> RED_MESSAGE
>
> A `notification_url` deve ser uma URL com protocolo HTTPS. Isso garante que as notificações sejam transmitidas de forma segura e que os dados trocados estejam criptografados, protegendo a integridade e a confidencialidade das informações. Além disso, HTTPS autentica que a comunicação está sendo realizada com o servidor legítimo, evitando possíveis interceptações maliciosas.

A seguir, explicamos como configurar notificações ao criar um pagamento utilizando nossos SDKs.

1. No campo `notification_url`, indique a URL de onde as notificações serão recebidas, como mostrado abaixo.

[[[
```php
<?php
$client = new PreferenceClient();
$preference = $client->create([
  "notification_url" => "https://www.your_url_to_notification.com/",
  "items"=> array(
  array(
  "title" => "Mi producto",
  "quantity" => 1,
  "unit_price" => 2000
  )
  )
]);

echo $preference
?>
```
```node
const preference = new Preference(client);

preference.create({
  body: {
  notification_url: 'https://www.your_url_to_notification.com/',
  items: [
  {
  title: 'Mi producto',
  quantity: 1,
  unit_price: 2000
  }
  ],
  }
})
.then(console.log)
.catch(console.log);
```
```java
PreferenceItemRequest itemRequest =
  PreferenceItemRequest.builder()
  .id("1234")
  .title("Games")
  .description("PS5")
  .pictureUrl("http://picture.com/PS5")
  .categoryId("games")
  .quantity(2)
  .currencyId("BRL")
  .unitPrice(new BigDecimal("4000"))
  .build();
  List<PreferenceItemRequest> items = new ArrayList<>();
  items.add(itemRequest);
PreferenceRequest preferenceRequest = PreferenceRequest.builder()
.items(items).build();
PreferenceClient client = new PreferenceClient();
Preference preference = client.create(request);
```
```ruby
# Cria um objeto de preferência
preference_data = {
  notification_url: 'https://www.your_url_to_notification.com/',
  items: [
  {
  title: 'Mi producto',
  unit_price: 75.56,
  quantity: 1
  }
  ]
}
preference_response = sdk.preference.create(preference_data)
preference = preference_response[:response]

# Este valor substituirá a string "<%= @preference_id %>" no seu HTML
@preference_id = preference['id']
```
```csharp
// Crea el objeto de request de la preference
var request = new PreferenceRequest
{
  Items = new List<PreferenceItemRequest>
  {
  new PreferenceItemRequest
  {
  Title = "Mi producto",
  Quantity = 1,
  CurrencyId = "ARS",
  UnitPrice = 75.56m,
  },
  },
};

// Cria a preferência usando o client
var client = new PreferenceClient();
Preference preference = await client.CreateAsync(request);

```
```python
# Cria um item na preferência
preference_data = {
  "notification_url" : "https://www.your_url_to_notification.com/",
  "items": [
  {
  "title": "Mi producto",
  "quantity": 1,
  "unit_price": 75.76,
  }
  ]
}

preference_response = sdk.preference().create(preference_data)
preference = preference_response["response"]
```
```go
client := preference.NewClient(cfg)

request := preference.Request{
	Items: []preference.ItemRequest{
		{
			Title: "My product",
			Quantity: 1,
			UnitPrice: 75.76,
		},
	},
}

resource, err := client.Create(context.Background(), request)
if err != nil {
	fmt.Println(err)
	return
}

fmt.Println(resource)

```
]]]

2. Implemente o receptor de notificações usando o código a seguir como exemplo:

```php
<?php
 MercadoPago\SDK::setAccessToken("ENV_ACCESS_TOKEN");
 switch($_POST["type"]) {
  case "payment":
  $payment = MercadoPago\Payment::find_by_id($_POST["data"]["id"]);
  break;
  case "plan":
  $plan = MercadoPago\Plan::find_by_id($_POST["data"]["id"]);
  break;
  case "subscription":
  $plan = MercadoPago\Subscription::find_by_id($_POST["data"]["id"]);
  break;
  case "invoice":
  $plan = MercadoPago\Invoice::find_by_id($_POST["data"]["id"]);
  break;
  case "point_integration_wh":
  // $_POST contiene la informaciòn relacionada a la notificaciòn.
  break;
 }
?>
```

Depois de realizar a configuração necessária, a notificação Webhook será enviada no formato `JSON`. Veja abaixo um exemplo de notificação do tópico `payment` e as descrições das informações enviadas na tabela abaixo.

> RED_MESSAGE
>
> Importante
>
> Os pagamentos de teste, criados com credenciais de teste, não enviarão notificações. A única maneira de testar a recepção de notificações é através da [Configuração através de Suas integrações](/developers/pt/docs/your-integrations/notifications/webhooks#configuracaoatravesdesuasintegracoes).

```json
{
 "id": 12345,
 "live_mode": true,
 "type": "payment",
 "date_created": "2015-03-25T10:04:58.396-04:00",
 "user_id": 44444,
 "api_version": "v1",
 "action": "payment.created",
 "data": {
  "id": "999999999"
 }
}
```

| Atributo | Descrição | Exemplo no JSON |
| --- | --- | --- |
| **id** | ID da notificação | `12345` |
| **live_mode** | Indica se a URL inserida é válida.| `true` |
| **type** | Tipo de notificação recebida de acordo com o tópico previamente selecionado (payments, mp-connect, subscription, claim, automatic-payments, etc) | `payment` |
| **date_created** | Data de criação do recurso notificado | `2015-03-25T10:04:58.396-04:00` |
| **user_id** | Identificador do vendedor | `44444` |
| **api_version** | Valor que indica a versão da API que envia a notificação | `v1` |
| **action** | Evento notificado, que indica se é uma atualização de um recurso ou a criação de um novo | `payment.created` |
| **data.id** | ID do pagamento, da ordem comercial ou da reclamação. | `999999999` |
::::

:::::

Após configurar as notificações, acesse a seção **Ações necessárias após receber uma notificação** para confirmar que elas foram devidamente recebidas.

## Ações necessárias após receber a notificação

Quando você recebe uma notificação na sua plataforma, o Mercado Pago espera uma resposta para validar que essa recepção foi correta. Para isso, você deve devolver um `HTTP STATUS 200 (OK)` ou `201 (CREATED)`.

O tempo de espera para essa confirmação será de 22 segundos. Se não for enviada essa resposta, o sistema entenderá que a notificação não foi recebida e realizará uma nova tentativa de envio a cada 15 minutos, até que receba a resposta. Após a terceira tentativa, o prazo será prorrogado, mas os envios continuarão acontecendo.

<pre class="mermaid">
sequenceDiagram
  participant MercadoPago as Mercado Pago
  participant Integrador as Integrador

  MercadoPago->>Integrador: tentativa: 1. Atraso: 0 minutos
  MercadoPago->>Integrador: tentativa: 2. Atraso: 15 minutos
  MercadoPago->>Integrador: tentativa: 3. Atraso: 30 minutos
  MercadoPago->>Integrador: tentativa: 4. Atraso: 6 horas
  MercadoPago->>Integrador: tentativa: 5. Atraso: 48 horas
  MercadoPago->>Integrador: tentativa: 6. Atraso: 96 horas
  MercadoPago->>Integrador: tentativa: 7. Atraso: 96 horas
  MercadoPago->>Integrador: tentativa: 8. Atraso: 96 horas
</pre>

Após responder a notificação, confirmando seu recebimento, você pode obter todas as informações sobre o evento do tópico `payments` notificado fazendo um GET ao endpoint [v1/payments/{id}](/developers/pt/reference/online-payments/checkout-pro/get-payment/get). 

Com essas informações, você poderá realizar as atualizações necessárias na sua plataforma, como por exemplo, atualizar um pagamento aprovado.

Além disso, para consultar o status do evento após a notificação, você pode utilizar os diferentes métodos dos nossos SDKs para realizar a consulta com o ID que foi enviado na notificação.

[[[
```java
MercadoPago.SDK.setAccessToken("ENV_ACCESS_TOKEN");
switch (type) {
  case "payment":
  Payment payment = Payment.findById(data.id);
  break;
  case "plan":
  Plan plan = Plan.findById(data.id);
  break;
  case "subscription":
  Subscription subscription = Subscription.findById(data.id);
  break;
  case "invoice":
  Invoice invoice = Invoice.findById(data.id);
  break;
  case "point_integration_wh":
  // POST contiene la informaciòn relacionada a la notificaciòn.
  break;
}
```
```node
mercadopago.configurations.setAccessToken('ENV_ACCESS_TOKEN');
switch (type) {
  case 'payment':
  const payment = await mercadopago.payment.findById(data.id);
  break;
  case 'plan':
  const plan = await mercadopago.plans.get(data.id);
  break;
  case 'subscription':
  const subscription = await mercadopago.subscriptions.get(data.id);
  break;
  case 'invoice':
  const invoice = await mercadopago.invoices.get(data.id);
  break;
  case 'point_integration_wh':
  // Contiene la informaciòn relacionada a la notificaciòn.
  break;
}
```
```ruby
sdk = Mercadopago::SDK.new('PROD_ACCESS_TOKEN')

case payload['type']
when 'payment'
  payment = sdk.payment.search(filters: { id: payload['data']['id'] })
when 'plan'
  plan = sdk.preapproval_plan.search(filters: { id: data['data']['id'] })
end
```
```csharp
MercadoPagoConfig.AccessToken = "ENV_ACCESS_TOKEN";
switch (type)
{
  case "payment":
  Payment payment = await Payment.FindByIdAsync(payload["data"]["id"].ToString());
  break;
  case "plan":
  Plan plan = await Plan.FindByIdAsync(payload["data"]["id"].ToString());
  break;
  case "subscription":
  Subscription subscription = await Subscription.FindByIdAsync(payload["data"]["id"].ToString());
  break;
  case "invoice":
  Invoice invoice = await Invoice.FindByIdAsync(payload["data"]["id"].ToString());
  break;
  case "point_integration_wh":
  // Contiene la informaciòn relacionada a la notificaciòn.
  break;
}
```
```python
sdk = mercadopago.SDK("ENV_ACCESS_TOKEN")
notification_type = data["type"]
if notification_type == "payment":
  payment = sdk.payment().get(payload["data"]["id"])
elif notification_type == "plan":
  plan = sdk.preapproval().get(payload["data"]["id"]) 
elif notification_type == "subscription":
  subscription = sdk.preapproval().get(payload["data"]["id"])
elif notification_type == "invoice":
  invoice = sdk.invoice().get(payload["data"]["id"])
elif notification_type == "point_integration_wh":
  # Contiene la informaciòn relacionada a la notificaciòn.
else:
  return
```
```golang
cfg, err := config.New("ENV_ACCESS_TOKEN")
if err != nil {
  fmt.Println(err)
}

switch req.Body.Type {
case "payment":
  client := payment.NewClient(cfg)
  resource, err = client.Get(context.Background(), req.Body.data.id)
  if err != nil {
  fmt.Println(err)
  return
  }
case "plan":
  client := preapprovalplan.NewClient(cfg)
  resource, err := client.Get(context.Background(), req.Body.data.id)
  if err != nil {
  fmt.Println(err)
  return
  }
}
```
]]]

## Etapa 6

# Teste de integração

Os testes são uma etapa essencial para garantir que a integração está funcionando corretamente e que os pagamentos serão processados sem erros. Isso evita falhas quando o checkout estiver disponível para os compradores. 

Para isso, utilize a conta de teste comprador criada automaticamente com sua aplicação. Com ela, é possível simular pagamentos e validar seu funcionamento.

A seguir, apresentamos o passo a passo:

## Obter uma conta de teste comprador

Para testar a integração, realize uma compra de teste utilizando a conta de teste comprador que foi criada automaticamente com sua aplicação. Para encontrá-la, siga os passos abaixo.

1. No [Mercado Pago Developers](/developers/pt/docs), navegue até [Suas integrações](/developers/panel/app) na parte superior direita da tela e clique no cartão correspondente à aplicação com a qual você está desenvolvendo.
2. Após acessar "Dados da integração", vá até a seção **Contas de teste** no menu lateral esquerdo.
3. No menu seletor, clique em **Comprador**. Uma vez lá, você verá o **país de operação** da conta, o **User ID**, o **usuário** e a **senha** da conta de teste.

![testuser](/images/snippets/test-cross/test-accounts-buyer-pt-v1.png)

> NOTE
>
> Se precisar realizar testes para outro país, crie uma [conta de teste](/developers/pt/docs/checkout-pro/additional-content/your-integrations/test/accounts) do tipo **Vendedor** e outra do tipo **Comprador**, certificando-se de selecionar o país correspondente ao qual deseja integrar.

# Realizar compras de teste

Depois de configurar seu ambiente de testes, você poderá realizar compras de teste para validar a integração com o Checkout Pro e verificar se os meios de pagamento configurados funcionam corretamente. A seguir, mostraremos como realizar diferentes verificações em sua integração.

> RED_MESSAGE
>
> Realize as compras de teste em uma **janela anônima** do seu navegador para evitar erros por duplicidade de credenciais no processo.

## Testar uma compra com cartão

Para testar uma compra com cartão de crédito ou débito, siga o passo a passo:

1. Acesse [Mercado Pago Developers](/developers/pt/docs) e faça login como o **usuário de teste comprador** criado previamente. Use o nome de usuário e senha associados à conta de teste. Para mais informações, consulte a seção [Obter uma conta de teste comprador](/developers/pt/docs/checkout-pro/integration-test).

> NOTE
>
> Se for solicitado um código por e-mail ao fazer login, insira o **código de 6 dígitos** associado à conta de teste que pode encontrar em **[Suas integrações](/developers/panel/app) > *Sua aplicação* > Contas de teste**.

2. Inicie o Checkout utilizando a preferência de pagamento configurada anteriormente. As instruções detalhadas sobre como proceder estão disponíveis na documentação [Adicionar o SDK ao frontend e inicializar o checkout](/developers/pt/docs/checkout-pro/web-integration/add-frontend-sdk).
3. **Em uma janela anônima do navegador**, acesse a loja onde você integrou o Checkout Pro, selecione um produto ou serviço e, na instância de pagamento, clique no botão de compra do Mercado Pago.
4. Por fim, realize uma compra de teste com os **cartões de teste** fornecidos abaixo. Para simular diferentes resultados de compra, utilize nomes variados para os titulares dos cartões de teste.

### Cartões de teste
O Mercado Pago fornece **cartões de teste** que permitirão que você teste pagamentos sem usar um cartão real.

Seus dados, como número, código de segurança e data de validade, podem ser combinados com os **dados relativos ao titular do cartão**, que permitirão que você teste diferentes cenários de pagamento. Ou seja, **você pode usar as informações de qualquer cartão de teste e testar resultados de pagamento diferentes a partir dos dados do titular**.

A seguir, você pode ver os **dados dos cartões de débito e crédito de teste**. Selecione aquele que você quer usar para testar sua integração.

| Tipo de cartão | Bandeira | Número | Código de segurança | Data de vencimento |
| :--- | :---: | :---: | :---: | :---: |
| Cartão de crédito | Mastercard | 5031 4332 1540 6351 | 123 | 11/30 |
| Cartão de crédito | Visa | 4235 6477 2802 5682 | 123 | 11/30 |
| Cartão de crédito | American Express | 3753 651535 56885 | 1234 | 11/30 |
| Cartão de débito | Elo | 5067 7667 8388 8311 | 123 | 11/30 |

Em seguida, escolha qual cenário de pagamento testar e preencha os campos do **titular do cartão** (Nome e sobrenome, Tipo e número de documento) conforme indicado na tabela abaixo.

| Status de pagamento | Nome e sobrenome do titular | Documento de identidade |
| --- | --- | --- |
| Pagamento aprovado | `APRO` | (CPF) 12345678909 |
| Recusado por erro geral | `OTHE` | (CPF) 12345678909 |
| Pagamento pendente | `CONT` | - |
| Recusado com validação para autorizar | `CALL` | - |
| Recusado por quantia insuficiente | `FUND` | - |
| Recusado por código de segurança inválido | `SECU` | - |
| Recusado por problema com a data de vencimento | `EXPI` | - |
| Recusado por erro no formulário | `FORM` | - |
| Rejeitado por falta de card_number | `CARD` | - |
| Rejeitado por parcelas inválidas | `INST` | - |
| Rejeitado por pagamento duplicado | `DUPL` | - |
| Rejeitado por cartão desabilitado | `LOCK` | - |
| Rejeitado por tipo de cartão não permitido | `CTNA` | - |
| Rejeitado devido a tentativas excedidas de pin do cartão | `ATTE` | - |
| Rejeitado por estar na lista negra | `BLAC` | - |
| Não suportado | `UNSU` | - |
| Usado para aplicar regra de valores | `TEST` | - |

Assim que você tiver preenchido todos os campos corretamente, clique no botão para processar o pagamento e aguarde o resultado. Se o teste foi bem-sucedido, a tela de sucesso da compra de teste será exibida.

Certifique-se de que está recebendo as notificações relacionadas à transação de teste, caso já tenha configurado as [notificações](/developers/pt/docs/checkout-pro/payment-notifications).

## Testar uma compra com um meio de pagamento offline

Confirme se sua integração está processando corretamente os meios de pagamento offline, como Pix ou Boleto. Lembre-se de que um teste bem-sucedido será aquele em que o estado do pagamento permanece como "pendente", já que as compras realizadas com meios de pagamento offline só são concluídas quando o cliente efetua o pagamento por outros canais.

Para realizar um teste, siga o passo a passo abaixo.

1. Acesse [Mercado Pago Developers](/developers/pt/docs) e faça login como o **usuário de teste comprador** criado previamente. Use o nome de usuário e senha associados à conta de teste. Para mais informações, consulte a seção [Obter uma conta de teste comprador](/developers/pt/docs/checkout-pro/integration-test).

> NOTE
>
> Se for solicitado um código por e-mail ao iniciar sessão, insira os **últimos 6 dígitos do User ID da conta de teste**, que você pode encontrar em **[Suas integrações](/developers/panel/app) > *Sua aplicação* > Contas de teste**.

2. Inicie o Checkout utilizando a preferência de pagamento configurada anteriormente. As instruções detalhadas sobre como proceder estão disponíveis na documentação [Adicionar o SDK ao frontend e inicializar o checkout](/developers/pt/docs/checkout-pro/web-integration/add-frontend-sdk).
3. **Em uma janela anônima do navegador**, acesse a loja onde você integrou o Checkout Pro, selecione um produto ou serviço e, na instância de pagamento, clique no botão de compra do Mercado Pago.
4. Selecione um meio de pagamento offline e complete o pagamento.

Caso o teste seja bem-sucedido, uma tela será exibida orientando sobre como concluir o pagamento.

## Etapa 7

# Subir em produção

Depois que o processo de configuração e testes for concluído, sua integração estará pronta para receber pagamentos reais em produção.

A seguir, veja as recomendações necessárias para realizar essa transição de maneira eficaz e segura, garantindo que sua integração esteja preparada para receber transações reais.

:::AccordionComponent{title="Ativar credenciais de produção" pill="1"}
Depois de realizar os devidos [testes da sua integração](/developers/pt/docs/checkout-api-payments/integration-test), **lembre-se de substituir as :toolTipComponent[credenciais]{link="/developers/pt/docs/checkout-api-payments/resources/credentials" linkText="Credenciais" content="Chaves de acesso únicas com as quais identificamos uma integração na sua conta, vinculadas à sua aplicação. Para mais informações, acesse o link abaixo."} que você utilizou na etapa de desenvolvimento pelas de produção** para que possa começar a operar no ambiente produtivo da sua loja e começar a receber pagamentos reais. Para isso, siga os passos abaixo para saber como **ativá-las**.

1. Acesse [Suas integrações](https://www.mercadopago[FAKER][URL][DOMAIN]/developers/panel/app) e selecione uma aplicação.
2. Em **Dados de integração**, vá para a seção **Credenciais**, localizada no lado direito da tela, e clique em **Produção**. Em seguida, clique em **Ativar credenciais**. Alternativamente, você poderá acessá-las também a partir da seção **Credenciais de produção** no menu lateral esquerdo.
3. No campo **Indústria**, selecione no menu suspenso a indústria ou setor ao qual pertence o negócio que você está integrando.
4. No campo **Site (obrigatório)**, complete com a URL do site do seu negócio.
5. Aceite a [Declaração de Privacidade](https://www.mercadopago.com.br/privacidade) e os [Termos e condições](/developers/pt/docs/resources/legal/terms-and-conditions). Complete o reCAPTCHA e clique em **Ativar credenciais de produção**.
:::

:::AccordionComponent{title="Usar credenciais de produção" pill="2"}
Para subir em produção, você deve **colocar as credenciais de produção da sua aplicação do Mercado Pago** na sua integração.

Para fazer isso, acesse [Suas integrações](/developers/panel/app), dirija-se à seção **Credenciais**, localizada à direita da tela, e clique em **Produção**. Alternativamente, você poderá acessá-las a partir de **Produção > Credenciais de produção**.

Lá você encontrará sua **Public Key** e **Access Token** produtivos, que deverá utilizar no lugar das credenciais da conta de teste.

![Como acessar as credenciais através de Suas Integrações](/images/snippets/credentials/application-data-production-credentials-pt-v1.png)

Para mais informações, consulte nossa documentação de [Credenciais](/developers/pt/docs/checkout-pro/additional-content/credentials).
:::

:::AccordionComponent{title="Implementar certificado SSL" pill="3"}
Para garantir uma integração segura que proteja os dados de cada transação, é imprescindível a implementação do certificado SSL (Secure Sockets Layer). Este certificado, associado ao uso do protocolo HTTPS na disponibilização dos meios de pagamento, assegura uma conexão criptografada entre o cliente e o servidor.

Adotar essas medidas não apenas reforça a segurança dos dados dos usuários, mas também assegura o cumprimento das normas e leis específicas de cada país relacionadas à proteção de dados e à segurança da informação. Além disso, contribui significativamente para proporcionar uma experiência de compra mais segura e confiável.

Embora o **uso do certificado SSL não seja obrigatório durante o período de testes**, sua implementação é obrigatória para a entrada em produção.

Para mais informações, consulte os [Termos e Condições do Mercado Pago](/developers/pt/docs/resources/legal/terms-and-conditions).
:::

:::AccordionComponent{title="Medir a qualidade da sua integração" pill="Opcional"}
Depois de concluir a configuração da sua integração, recomendamos que você realize uma **medição de qualidade**, que é um processo de certificação da sua integração, com o qual você poderá garantir que seu desenvolvimento atenda aos requisitos de qualidade necessários para assegurar uma melhor experiência, assim como uma maior taxa de aprovação de pagamentos.

Para saber mais, acesse a documentação [Como medir a qualidade da sua integração](/developers/pt/docs/checkout-pro/how-tos/integration-quality).
:::

## Detalhes adicionais


# Credenciais

As credenciais são chaves de acesso únicas com as quais identificamos uma integração em sua conta. Elas estão diretamente vinculadas à :toolTipComponent[aplicação]{link="/developers/pt/docs/your-integrations/application-details" linkText="Detalhes da aplicação" content="Entidade registrada no Mercado Pago que atua como um identificador para gerenciar suas integrações. Para mais informações, acesse o link abaixo."} que você criou para essa integração e permitirão que você desenvolva seu projeto contando com as melhores medidas de segurança do Mercado Pago.

## Tipos de credenciais 

As credenciais são divididas em dois tipos: **credenciais de teste** e **credenciais de produção**. A seguir, detalhamos cada uma delas.

:::::TabsComponent

::::TabComponent{title="Credenciais de teste"}
### Credenciais de teste

As credenciais de teste são um conjunto de chaves que são utilizadas tanto na etapa de desenvolvimento, para garantir configurações seguras, quanto na etapa de testes, para testar a integração.

Durante o processo de integração, utilize **credenciais de teste** para realizar todas as configurações e validações necessárias, garantindo que não sejam efetuados pagamentos reais em produção. Essas credenciais simulam as informações de uma conta produtiva, mas em um ambiente seguro de testes. Mantenha o uso das credenciais de teste durante toda a fase de desenvolvimento. Apenas substitua-as pelas credenciais produtivas quando o sistema estiver completamente validado e pronto para ser publicado.

> NOTE
>
> Se estiver desenvolvendo para outra pessoa, ela deverá solicitar acesso às credenciais das aplicações que não administra. Para mais informações, consulte [Compartilhar credenciais](/developers/pt/docs/checkout-pro/resources/credentials#bookmark_compartilhar_credenciais).

Ao criar uma aplicação as credenciais de teste serão geradas automaticamente. Caso isso não aconteça, basta clicar em **Ativar credenciais** nos dados da integração em questão ou conforme indicamos a seguir.

1. Em [Suas integrações](/developers/panel/app), selecione sua aplicação. Em seguida, vá até a seção **Testes** e clique em **Credenciais de teste** no menu à esquerda da tela.
2. Aceite a [Declaração de Privacidade](https://www.mercadopago.com.br/privacidade) e os [Termos e condições](/developers/pt/docs/resources/legal/terms-and-conditions). Preencha o reCAPTCHA e clique em **Ativar credenciais**.

![ativar credenciais de teste](/images/snippets/credentials/activate-credentials-tests-PT-v1.png)

Após ativar suas credenciais de teste, você poderá utilizar o seus **Public Key e o Access Token** de teste.

### Public Key e Access Token

As credenciais **Public Key** e **Access Token** de teste são utilizadas da mesma forma que as credenciais produtivas, mas não permitirão realizar nenhuma transação real.

| Tipo | Descrição |
|---|---|
| Public Key | A chave pública da aplicação é geralmente utilizada no frontend. Permite, por exemplo, acessar informações sobre os meios de pagamento e criptografar os dados do cartão. |
| Access Token | Chave privada da aplicação que sempre deve ser utilizada no backend para gerar pagamentos. É essencial manter essa informação segura em seus servidores. |

::::
::::TabComponent{title="Credenciais de produção"}
### Credenciais de produção

As **credenciais de produção** são um conjunto de chaves que permitem receber pagamentos reais em lojas e em outras aplicações. Para obter as credenciais de produção, você deverá **ativá-las** preenchendo alguns dados sobre o seu negócio. Siga os passos abaixo:

1. Acesse [Suas integrações](https://www.mercadopago[FAKER][URL][DOMAIN]/developers/panel/app) e selecione uma aplicação.
2. Vá até a seção **Produção** no menu lateral esquerdo e clique em **Credenciais de produção** no menu à esquerda da tela.
3. Aceite a [Declaração de Privacidade](https://www.mercadopago.com.br/privacidade) e os [Termos e condições](/developers/pt/docs/resources/legal/terms-and-conditions). Preencha o reCAPTCHA e clique em **Ativar credenciais**.

Ao acessar as credenciais de produção, serão exibidos os seguintes pares de credenciais: **Public Key e Access Token**, além de **Client ID e Client Secret**.

![Como acessar as credenciais através das Suas Integrações](/images/snippets/credentials-prod-panel-pt-v1.jpg)

### Public Key e Access Token

As credenciais **Public Key** e **Access Token** são utilizadas, não necessariamente juntas, nas integrações realizadas com as soluções de pagamento do Mercado Pago. Estão diretamente vinculadas à :toolTipComponent[aplicação]{link="/developers/pt/docs/your-integrations/application-details" linkText="Detalhes da aplicação" content="Entidade registrada no Mercado Pago que atua como um identificador para gerenciar suas integrações. Para mais informações, acesse o link abaixo."} que você criou, por isso cada par de credenciais é único para cada integração.

| Tipo | Descrição |
|---|---|
| Public Key | A chave pública da aplicação é geralmente utilizada no frontend. Permite, por exemplo, acessar informações sobre os meios de pagamento e criptografar os dados do cartão. |
| Access Token | Chave privada da aplicação que sempre deve ser utilizada no backend para gerar pagamentos. É essencial manter esta informação segura em seus servidores. |

Para obter mais informações sobre quais credenciais serão necessárias para a sua integração, consulte a [documentação](https://www.mercadopago[FAKER][URL][DOMAIN]/developers/pt/docs) da solução que está sendo integrada.

### Client ID e Client Secret

As credenciais **Client ID** e **Client Secret** são utilizadas, principalmente, nas integrações que possuem [OAuth](/developers/pt/docs/security/oauth) como protocolo para obtenção de informação privada de contas do Mercado Pago. Em particular, são utilizadas durante o fluxo (_grant type_) de **Client Credentials**, que permite acessar um recurso em nome próprio e obter um Access Token sem interação do usuário.

Também podem ser requeridas em algumas integrações mais antigas com plataformas de e-commerce.

| Tipo | Descrição |
|---|---|
| Client ID | Identificador único que representa sua integração. |
| Client Secret | Chave privada utilizada em alguns complementos para gerar pagamentos. É extremamente importante manter esta informação segura em seus servidores e não permitir o acesso a nenhum usuário do sistema ou intruso. |

::::

:::::

## Compartilhar credenciais

Se você estiver desenvolvendo para outra pessoa ou estiver recebendo ajuda durante a integração ou configuração de suas lojas, poderá compartilhar as credenciais de forma segura com outra conta do Mercado Pago.

Você pode compartilhar as credenciais **até 10 vezes**. Se você atingir este limite, deverá eliminar permissões antigas, sem impacto nas integrações já configuradas.

Além disso, se por questões de segurança você não desejar mais compartilhar suas credenciais, você pode cancelar o acesso.

A seguir, mostramos como compartilhar credenciais.

1. No canto superior direito do [Mercado Pago Developers](https://www.mercadopago[FAKER][URL][DOMAIN]/developers/panel/app), clique em **Entrar** e insira os dados solicitados com as informações correspondentes à sua conta do Mercado Pago. Em seguida, clique em **Suas integrações** localizado no canto superior direito.
2. Acesse a aplicação da integração para a qual você precisa compartilhar as credenciais.
3. Vá para a seção **Testes** ou **Produção**, dependendo do tipo de credencial que você deseja compartilhar. Lembre-se de que para acessar as credenciais de produção, você deverá ativá-las. Se não sabe como ativá-las, vá para [Ativar credenciais de produção](/developers/pt/docs/credentials#bookmark_ativar_credenciais_de_produção).
4. Uma vez que você selecionar as credenciais, vá até a seção **Compartilhe as credenciais com um desenvolvedor** e clique em **Compartilhar credenciais**.
5. Informe o endereço de e-mail da pessoa para quem você deseja conceder acesso. **Importante**: o endereço de e-mail deve, obrigatoriamente, estar vinculado a uma conta do Mercado Pago.

![Compartilhar credenciais em Suas Integrações](/images/snippets/share-credentials-panel-pt-v1.jpg)

## Renovar credenciais

Você pode renovar suas **credenciais de produção** por motivos de segurança ou qualquer outra razão relevante.

> WARNING
>
> Renovar credenciais já configuradas numa integração afetará o seu funcionamento. É necessário que **você substitua as credenciais antigas pelas novas** após o processo de renovação para continuar operando.

Para renovar um par de credenciais, siga os passos abaixo.

1. Acesse suas credenciais de produção através de [Suas integrações](https://www.mercadopago[FAKER][URL][DOMAIN]/developers/panel/app).
2. Selecione o par de credenciais que você deseja renovar, podendo ser **Public Key** e **Access Token** ou **Client ID** e **Client Secret**. Tenha em mente que ambas as credenciais do par que você escolher serão renovadas.
3. Clique nos três pontos localizados à direita da credencial que você deseja renovar e selecione **Renovar**. Clique em **Renovar agora** para confirmar a alteração.

![Como renovar suas credenciais](/images/snippets/renew-credentials-pt-v1.jpg)

Pronto, suas credenciais já foram renovadas.

## Recomendações de segurança

Ao integrar as soluções do Mercado Pago, você estará lidando com dados sensíveis que precisam ser protegidos de possíveis perdas ou vulnerabilidades, como suas credenciais de acesso ao Mercado Pago, as chaves utilizadas nas suas integrações e as informações dos seus clientes.

Mostraremos como você pode otimizar a segurança de suas integrações de forma simples e rápida.

### Envie o Access Token no header

Sempre que fizer chamadas à API, envie o **Access Token** no _header_ ao invés de enviá-lo como _query param_. Essa prática aumenta a segurança, evitando que o token seja exposto a terceiros fora da sua integração.

Por exemplo, para enviar uma requisição **GET** ao recurso `/users/me`, utilize o seguinte formato:

```curl
curl -H 'Authorization: Bearer {{YOUR_ACCESS_TOKEN}}' \
https://api.mercadolibre.com/users/me
```

### Use o OAuth para gerenciar credenciais de terceiros

OAuth é um protocolo de autorização que permite o acesso seguro de aplicações a contas de usuário em serviços HTTP, sem que esse usuário precise compartilhar suas credenciais diretamente. Funciona como um intermediário que facilita o acesso controlado aos dados do usuário por aplicações de terceiros.

Para mais informações, acesse a [documentação](/developers/pt/docs/security/oauth).