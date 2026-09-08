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
export const SITE_TITLE = `${APP_NAME} — estoque, pacientes e agenda da clínica`
export const SITE_ONE_LINER =
  'HOF Pay reúne estoque, protocolos, pacientes, agenda e números da clínica — no celular e no consultório.'

export const CTA_REGISTER_LABEL = 'Criar clínica'
export const CTA_REGISTER_STRONG_LABEL = 'Criar minha clínica'
export const CTA_LOGIN_LABEL = 'Já tenho acesso'
export const CTA_LOGIN_SHORT_LABEL = 'Entrar'

export const features = [
  {
    id: 'estoque',
    title: 'Estoque',
    teaser: 'A baixa só acontece no tratamento, não na venda.',
    headline: 'A baixa só acontece no tratamento',
    copy: 'Lotes, validade e reposição acompanham a sessão. O frasco só sai do registro quando o profissional de harmonização informa o que foi aplicado.',
    points: [
      'Lote, validade e custo real do que entra na clínica',
      'Reposição no ritmo do consumo, não do caixa',
      'Venda e contrato primeiro; baixa só na sessão',
    ],
  },
  {
    id: 'protocolos',
    title: 'Protocolos',
    teaser: 'Procedimento com produtos, sessões e preço — sem improviso na poltrona.',
    headline: 'Procedimento com composição e preço',
    copy: 'O serviço deixa de ser “a gosto”. Cada protocolo junta produtos, sessões e quatro preços: custo, sugerido, mínimo e especial.',
    points: [
      'Composição com o que a clínica realmente usa',
      'Custo, sugerido, mínimo e especial no mesmo lugar',
      'Sessões do tratamento já entram no protocolo',
    ],
  },
  {
    id: 'pacientes',
    title: 'Pacientes',
    teaser: 'Cadastro, origem e o tratamento em andamento.',
    headline: 'A recepção encontra a pessoa certa',
    copy: 'Cadastro, WhatsApp, origem e histórico de orçamentos, vendas e sessões. A equipe não perde o fio da conversa; o profissional de harmonização vê o que já foi aplicado.',
    points: [
      'Cadastro, WhatsApp e origem da indicação',
      'Histórico de orçamentos, vendas e sessões',
      'Tratamento em andamento visível para a equipe',
    ],
  },
  {
    id: 'agenda',
    title: 'Agenda',
    teaser: 'Sessões, retornos e o dia do profissional de harmonização.',
    headline: 'Sessões e retornos no celular',
    copy: 'O dia da clínica em um olhar: sessões do tratamento, retornos e o que falta aplicar. Feita para o celular, também no consultório.',
    points: [
      'Sessões do tratamento, não só um calendário vazio',
      'Retornos visíveis no dia do profissional de harmonização',
      'Uso no celular da equipe, sem depender só da recepção',
    ],
  },
  {
    id: 'metricas',
    title: 'Métricas',
    teaser: 'Margem, comercial e operação no mesmo sistema.',
    headline: 'Margem verdadeira, sem planilha paralela',
    copy: 'Números da clínica depois da baixa no tratamento: margem, funil de orçamento até a venda, estoque e operação. Sem aba à parte para “o que realmente foi usado”.',
    points: [
      'Margem depois da baixa na sessão',
      'Orçamento aceito até a venda confirmada',
      'Estoque e operação no mesmo olhar',
    ],
  },
] as const

export const proofPoints = [
  {
    title: 'Orçamento → aceitar → venda',
    copy: 'A venda só se confirma quando o paciente aceita o orçamento.',
  },
  {
    title: 'Estoque na sessão',
    copy: 'A baixa acontece no tratamento, não no caixa.',
  },
  {
    title: 'Agenda no celular',
    copy: 'Sessões e retornos no dia do profissional de harmonização.',
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
    copy: 'O procedimento vira um serviço com composição, sessões e preço.',
  },
  {
    n: '03',
    title: 'Orçar, aceitar e vender',
    copy: 'O orçamento sai da venda em rascunho. Com o aceite do paciente, a venda fecha — o frasco ainda não saiu da geladeira.',
  },
  {
    n: '04',
    title: 'Tratar e baixar o que foi usado',
    copy: 'Na sessão, o profissional de harmonização informa o que foi aplicado. Aí sim o estoque e a margem fecham.',
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
    a: 'Sim. Estoque, protocolos, vendas, tratamentos e margem ficam no mesmo ritmo — sem aba paralela para “o que realmente foi usado”.',
  },
  {
    id: 'celular',
    q: 'Funciona no celular?',
    a: 'Sim. O sistema da clínica foi feito para o celular. Profissionais de harmonização e a equipe usam no dia a dia, não só no computador da recepção.',
  },
  {
    id: 'estoque',
    q: 'A venda já baixa o estoque?',
    a: 'Não. A venda e o contrato acontecem primeiro. A baixa é no tratamento, quando o profissional de harmonização informa o que foi de fato aplicado.',
  },
  {
    id: 'orcamento',
    q: 'Como o orçamento vira venda?',
    a: 'O orçamento sai da venda em rascunho. Quando o paciente aceita, a venda pode ser confirmada. Agenda e consumo ficam na etapa de tratamento.',
  },
  {
    id: 'preco',
    q: 'Quanto custa?',
    a: 'Planos em breve. Ainda não há tabela pública — e não publicamos valores inventados. Crie a clínica para começar; a oferta comercial entra quando estiver pronta.',
  },
  {
    id: 'acesso',
    q: 'Como entro no sistema?',
    a: 'Pelo sistema em app.hofpay.com.br. O cadastro cria a clínica e o primeiro usuário administrador. Quem já tem conta entra pelo login.',
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

HOF Pay é um sistema de gestão para clínicas (estoque, protocolos, pacientes, agenda e números). Este site não pede login e não se conecta ao sistema da clínica. O produto operacional está em app.hofpay.com.br.

Idioma: pt-BR.

## Para quem

Clínicas (estética, dermatologia, harmonização, odontologia, procedimentos injetáveis, medicina integrativa, multiunidade). Profissionais de harmonização e a equipe, no celular e no consultório.

## Produto

- Estoque: lotes, validade, reposição. A baixa acontece no tratamento, não na venda.
- Protocolos: procedimentos com produtos, sessões e preços (custo, sugerido, mínimo, especial).
- Pacientes: cadastro, WhatsApp, origem e histórico de orçamentos, vendas e sessões.
- Agenda: sessões e retornos no dia do profissional de harmonização.
- Métricas: margem, comercial e operação — sem planilha paralela.

## Preço

Planos em breve. Não há tabela pública. Não inventamos valores. Para começar: criar a clínica no sistema.

## URLs

- Site: ${SITE_URL}
- Criar clínica: ${APP_REGISTER_URL}
- Entrar: ${APP_LOGIN_URL}
- Sistema da clínica: https://${APP_HOST}
- Integração interna (não é documentação pública): https://${API_HOST}

## Mais

- [llms-full.txt](${SITE_URL}/llms-full.txt): perguntas frequentes e o ritmo da clínica
- [Página inicial](${SITE_URL}/)
`

export const llmsFullTxt = `${llmsTxt}

## O ritmo da clínica

Vender não baixa estoque. Ordem:

1. Cadastrar produtos (lote, validade, custo real).
2. Montar protocolos (serviço com composição, sessões e preço).
3. Orçar, o paciente aceita, a venda fecha — o produto ainda não sai da geladeira.
4. No tratamento, informar o que foi aplicado. Aí o estoque e a margem fecham.

## Perguntas frequentes

${faqs.map((item) => `### ${item.q}\n\n${item.a}`).join('\n\n')}
`
