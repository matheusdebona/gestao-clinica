import {
  API_HOST,
  APP_HOST,
  APP_LOGIN_URL,
  APP_NAME,
  APP_REGISTER_URL,
  MARKETING_HOST,
  SITE_DESCRIPTION,
} from '../lib/urls'

export const SITE_URL = `https://${MARKETING_HOST}`
export const SITE_TITLE = `${APP_NAME} — gestão da clínica, com calma.`
export const SITE_ONE_LINER =
  'HOF Pay é a plataforma de gestão para clínicas: estoque, protocolos, clientes, agenda e métricas.'

export const features = [
  {
    title: 'Estoque',
    copy: 'Lotes, validade e reposição no ritmo da clínica. A baixa só acontece quando o tratamento é de fato aplicado.',
  },
  {
    title: 'Protocolos',
    copy: 'Procedimentos montados com produtos, sessões e preços — custo, sugerido, mínimo e especial, sem improviso.',
  },
  {
    title: 'Clientes',
    copy: 'Cadastro, origem e campanhas. A recepção encontra a pessoa certa sem perder o fio da conversa.',
  },
  {
    title: 'Agenda',
    copy: 'Sessões, retornos e o dia do médico em um olhar. Pensada para o celular, não só para o desktop.',
  },
  {
    title: 'Métricas',
    copy: 'Margem, comercial e operação. Números da clínica, sem planilha paralela e sem teatro de dashboard.',
  },
] as const

export const rhythmSteps = [
  {
    n: '01',
    title: 'Cadastrar produtos',
    copy: 'O que entra na clínica fica registrado: lote, validade, custo real.',
  },
  {
    n: '02',
    title: 'Montar protocolos',
    copy: 'O procedimento deixa de ser “a gosto”. Vira um serviço com composição e preço.',
  },
  {
    n: '03',
    title: 'Vender sem baixar estoque',
    copy: 'A venda e o contrato acontecem. O frasco ainda não saiu da geladeira.',
  },
  {
    n: '04',
    title: 'Tratar e baixar o que foi usado',
    copy: 'Na sessão, o médico informa o que foi aplicado. Aí sim o estoque e a margem fecham.',
  },
] as const

export const clinicLabels = [
  'Estética',
  'Dermatologia',
  'Harmonização',
  'Odontologia',
  'Procedimentos injetáveis',
  'Medicina integrativa',
  'Clínicas boutique',
  'Multiunidade',
] as const

export const faqs = [
  {
    id: 'planilha',
    q: 'O HOF Pay substitui a planilha da clínica?',
    a: 'Sim, esse é o ponto. Estoque, protocolos, vendas, tratamentos e margem ficam no mesmo ritmo — sem aba paralela para “o que realmente foi usado”.',
  },
  {
    id: 'celular',
    q: 'Funciona no celular?',
    a: 'O app da clínica é mobile-first. Médico e secretária usam no dia a dia, não só no computador da recepção.',
  },
  {
    id: 'dados',
    q: 'Os dados da minha clínica se misturam com os de outra?',
    a: 'Não. Os dados de cada clínica ficam separados: produtos, clientes, agenda e números não se misturam.',
  },
  {
    id: 'estoque',
    q: 'A venda já baixa o estoque?',
    a: 'Não. A venda e o contrato acontecem primeiro. A baixa é no tratamento, quando o médico informa o que foi de fato aplicado.',
  },
  {
    id: 'preco',
    q: 'Quanto custa?',
    a: 'Ainda não há tabela pública. Não publicamos valores inventados. Crie a clínica para começar; a oferta comercial entra quando estiver pronta.',
  },
  {
    id: 'acesso',
    q: 'Como entro no sistema?',
    a: 'Pelo app em app.hofpay.com.br. Cadastro cria a clínica e o primeiro usuário admin. Quem já tem conta entra pelo login.',
  },
] as const

export function jsonLdGraph() {
  return {
    '@context': 'https://schema.org',
    '@graph': [
      {
        '@type': 'Organization',
        '@id': `${SITE_URL}/#organization`,
        name: APP_NAME,
        url: SITE_URL,
        logo: `${SITE_URL}/favicon.svg`,
        description: SITE_DESCRIPTION,
      },
      {
        '@type': 'WebSite',
        '@id': `${SITE_URL}/#website`,
        url: SITE_URL,
        name: APP_NAME,
        description: SITE_DESCRIPTION,
        inLanguage: 'pt-BR',
        publisher: { '@id': `${SITE_URL}/#organization` },
      },
      {
        '@type': 'SoftwareApplication',
        '@id': `${SITE_URL}/#app`,
        name: APP_NAME,
        url: SITE_URL,
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        inLanguage: 'pt-BR',
        description: SITE_DESCRIPTION,
        featureList: features.map((item) => item.title),
        publisher: { '@id': `${SITE_URL}/#organization` },
      },
      {
        '@type': 'FAQPage',
        '@id': `${SITE_URL}/#faq`,
        inLanguage: 'pt-BR',
        mainEntity: faqs.map((item) => ({
          '@type': 'Question',
          name: item.q,
          acceptedAnswer: {
            '@type': 'Answer',
            text: item.a,
          },
        })),
      },
    ],
  }
}

export const llmsTxt = `# ${APP_NAME}

> ${SITE_ONE_LINER}

HOF Pay é um software de gestão para clínicas (estoque, protocolos, clientes, agenda e métricas). O site de marketing não pede login e não fala com a API. O produto operacional está no app.

Idioma: pt-BR.

## Para quem

Clínicas (estética, dermatologia, harmonização, odontologia, procedimentos injetáveis, medicina integrativa, multiunidade). Médico e secretária, no celular e no consultório.

## Produto

- Estoque: lotes, validade, reposição. A baixa acontece no tratamento, não na venda.
- Protocolos: procedimentos com produtos, sessões e preços (custo, sugerido, mínimo, especial).
- Clientes: cadastro, origem e campanhas.
- Agenda: sessões e retornos.
- Métricas: margem, comercial e operação.

Os dados de cada clínica ficam separados.

## Preço

Não há tabela pública. Não inventamos valores. Para começar: criar a clínica no app. Planos comerciais, em breve.

## URLs

- Site: ${SITE_URL}
- Criar clínica: ${APP_REGISTER_URL}
- Entrar: ${APP_LOGIN_URL}
- App: https://${APP_HOST}
- API (produto, não é documentação pública): https://${API_HOST}

## Mais

- [llms-full.txt](${SITE_URL}/llms-full.txt): FAQ e o ritmo da clínica
- [Página inicial](${SITE_URL}/)
`

export const llmsFullTxt = `${llmsTxt}

## O ritmo da clínica

Vender não baixa estoque. Ordem:

1. Cadastrar produtos (lote, validade, custo real).
2. Montar protocolos (serviço com composição e preço).
3. Vender e gerar contrato — o produto ainda não sai da geladeira.
4. No tratamento, informar o que foi aplicado. Aí o estoque e a margem fecham.

## FAQ

${faqs.map((item) => `### ${item.q}\n\n${item.a}`).join('\n\n')}
`
