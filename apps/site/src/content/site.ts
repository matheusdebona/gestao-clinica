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
export const SITE_TITLE = `${APP_NAME}: saia da planilha. A clínica no celular.`
export const SITE_ONE_LINER =
  'HOF Pay é o sistema da clínica de harmonização no celular. Lead, agenda, sessão e o fechamento do mês. Dá para ver se sobrou ou faltou.'

export const HERO_KICKER = 'Clínica de harmonização orofacial'
export const HERO_TITLE_LEAD = 'A planilha não fecha o mês.'
export const HERO_TITLE_EMPHASIS = 'O celular fecha.'
export const HERO_LEAD =
  'Você aplica, a maquininha passa, a comissão sai. No domingo a célula amarela não diz se deu lucro ou prejuízo. HOF Pay junta lead, agenda e o DRE: margem líquida, no bolso.'

export const CTA_REGISTER_LABEL = 'Criar clínica'
export const CTA_REGISTER_STRONG_LABEL = 'Criar minha clínica'
export const CTA_LOGIN_LABEL = 'Já tenho acesso'
export const CTA_LOGIN_SHORT_LABEL = 'Entrar'

export const MOCK_CAPTION = 'Tela de exemplo. Números de demonstração, não são de clínica nenhuma.'
export const HERO_STAGE_CAPTION = 'A planilha vira o DRE no celular.'

export const painItems = [
  {
    id: 'planilha',
    title: 'O fechamento no chute',
    copy: 'Taxa da maquininha, imposto, comissão, aluguel, toxina. Cada um numa aba. No fim do mês ninguém sabe se sobrou ou faltou.',
  },
  {
    id: 'leads',
    title: 'O paciente que sumiu',
    copy: 'Mandou mensagem no WhatsApp da recepção. Caiu no chat pessoal. Ninguém cobrou, ninguém anotou. Sumiu.',
  },
  {
    id: 'agenda',
    title: 'A cadeira vazia',
    copy: 'A sessão de amanhã está na agenda. Ninguém confirmou. O retorno previsto ficou no caderninho. A cadeira fica vazia.',
  },
] as const

export const showcases = [
  {
    id: 'lucro',
    kicker: 'Fechamento',
    title: 'Quanto sobrou. Sem aba amarela.',
    copy: 'O DRE do mês junta o que a planilha espalha: receita, insumo, taxa da maquininha, imposto, comissão e despesa. Sai margem de contribuição, margem líquida e ponto de equilíbrio.',
    points: [
      'Taxa da maquininha e imposto entram na conta, não ficam de fora',
      'Comissão, despesa do mês e depreciação de equipamento no mesmo demonstrativo',
      'Margem de contribuição, margem líquida e ponto de equilíbrio',
    ],
    mock: 'dre' as const,
  },
  {
    id: 'funil',
    kicker: 'Leads',
    title: 'O paciente que mandou mensagem não some.',
    copy: 'Do primeiro WhatsApp até a venda: lead criado, abordado, respondeu, agendou, compareceu, orçamento, venda. A recepção cobra pelo WhatsApp. Lead parado aparece no início do dia.',
    points: [
      'Funil até a venda, por origem e por colaborador',
      'WhatsApp com a mensagem pronta. O toque fica no histórico',
      'Fila de acompanhamento quando o lead esfria',
    ],
    mock: 'funnel' as const,
  },
  {
    id: 'agenda',
    kicker: 'Agenda',
    title: 'Confirma amanhã no WhatsApp.',
    copy: 'Sessão, retorno e o dia do profissional de harmonização no celular. A equipe manda a confirmação com a mensagem pronta e marca quem confirmou, quem desmarcou e quem ainda não respondeu.',
    points: [
      'Confirmar amanhã: a conversa do WhatsApp já sai montada',
      'Retorno a vencer e atrasado, também no WhatsApp',
      'Agenda da sessão, não um calendário vazio',
    ],
    mock: 'agenda' as const,
  },
] as const

export const features = [
  {
    id: 'financeiro',
    title: 'Financeiro',
    teaser: 'DRE do mês. Taxa da maquininha, imposto, comissão, despesa. Quanto sobrou.',
    href: '#lucro',
  },
  {
    id: 'leads',
    title: 'Leads',
    teaser: 'Funil, cobrança no WhatsApp e o paciente que ia esfriar.',
    href: '#funil',
  },
  {
    id: 'agenda',
    title: 'Agenda',
    teaser: 'Sessão, retorno e confirmação pelo WhatsApp.',
    href: '#agenda',
  },
  {
    id: 'pacientes',
    title: 'Pacientes',
    teaser: 'Cadastro, origem, histórico e tratamento em andamento.',
    href: '#produto',
  },
  {
    id: 'vendas',
    title: 'Orçamentos e vendas',
    teaser: 'Orçamento aceito vira venda. O que ficou parado entra na fila de cobrança.',
    href: '#produto',
  },
  {
    id: 'protocolos',
    title: 'Protocolos',
    teaser: 'Produto, sessão e preço. Sem inventar na poltrona.',
    href: '#produto',
  },
  {
    id: 'estoque',
    title: 'Estoque',
    teaser: 'A baixa é no tratamento, não na venda. O frasco só sai quando aplica.',
    href: '#produto',
  },
  {
    id: 'equipe',
    title: 'Comissão e meta',
    teaser: 'Regra de comissão e meta do mês por colaborador.',
    href: '#produto',
  },
] as const

export const rhythmSteps = [
  {
    n: '01',
    title: 'O lead entra no sistema, não no chat pessoal',
    copy: 'WhatsApp, prazo e quem é o responsável ficam visíveis. O paciente não some.',
  },
  {
    n: '02',
    title: 'Agenda e confirma',
    copy: 'A sessão entra na agenda. A equipe confirma pelo WhatsApp e vê quem ainda não respondeu.',
  },
  {
    n: '03',
    title: 'Orça, o paciente aceita, a venda fecha',
    copy: 'O orçamento sai da venda em rascunho. Com o aceite, a venda fecha. O frasco ainda está na geladeira.',
  },
  {
    n: '04',
    title: 'Aplica e baixa o que usou',
    copy: 'Na sessão, o profissional de harmonização informa o que foi aplicado. Aí o estoque e o custo fecham.',
  },
  {
    n: '05',
    title: 'Fecha o mês no DRE',
    copy: 'Receita, taxa da maquininha, imposto, comissão e despesa no mesmo lugar. Margem líquida: sobrou ou faltou.',
  },
] as const

export const clinicLine =
  'Harmonização orofacial, estética, dermatologia, odontologia e procedimentos injetáveis.'

export const faqs = [
  {
    id: 'planilha',
    q: 'Substitui a planilha da clínica?',
    a: 'Sim. Lead, agenda, paciente, orçamento, venda, estoque, comissão, despesa e o DRE do mês ficam no mesmo sistema, no celular. Não tem aba paralela para “o que realmente fechou”.',
  },
  {
    id: 'lucro',
    q: 'Como eu sei se sobrou ou faltou no mês?',
    a: 'Pelo DRE. Entram receita, insumo, taxa da maquininha, imposto, comissão, perda de estoque e despesa. O resultado traz margem de contribuição, margem líquida e ponto de equilíbrio.',
  },
  {
    id: 'celular',
    q: 'Funciona no celular?',
    a: 'Sim. Foi feito para o celular. A doutor·a, a recepção e a equipe usam no dia: agenda, confirmação, lead e o DRE. Não depende do computador da recepção.',
  },
  {
    id: 'whatsapp',
    q: 'Como funciona a confirmação no WhatsApp?',
    a: 'A equipe abre a conversa no WhatsApp com a mensagem pronta: confirmação de consulta, cobrança de lead, retorno do paciente ou orçamento parado. O toque fica registrado no sistema.',
  },
  {
    id: 'estoque',
    q: 'A venda já baixa o estoque?',
    a: 'Não. A venda acontece primeiro. A baixa é no tratamento, quando o profissional de harmonização informa o que foi de fato aplicado.',
  },
  {
    id: 'orcamento',
    q: 'Como o orçamento vira venda?',
    a: 'O orçamento sai da venda em rascunho. Quando o paciente aceita, a venda pode ser confirmada. Agenda e consumo ficam na etapa de tratamento. Orçamento enviado sem resposta entra na fila de cobrança.',
  },
  {
    id: 'acesso',
    q: 'Como eu começo?',
    a: 'Crie a clínica em app.hofpay.com.br. O cadastro abre o sistema e o primeiro usuário administrador. Quem já tem conta entra pelo login.',
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

HOF Pay é um sistema de gestão para clínicas de harmonização orofacial e estética. Este site não pede login e não se conecta ao sistema da clínica. O produto operacional está em app.hofpay.com.br.

Idioma: pt-BR.

Mensagem central: sair da planilha, tocar a clínica no celular e saber no fechamento do mês se sobrou ou faltou (margem líquida).

## Para quem

Clínicas de harmonização orofacial, estética, dermatologia, odontologia e procedimentos injetáveis. Profissional de harmonização, recepção e a equipe. No celular e no consultório.

Não use “cliente” para a pessoa atendida: no produto é paciente. Não descreva o público como “médico/médica”; use profissional de harmonização ou doutor·a.

## Produto (o que existe hoje)

- Leads: cadastro, funil (lead criado → abordado → respondeu → agendou → compareceu → orçamento → venda), follow-up pelo WhatsApp, prazos de lead parado.
- Agenda: sessões e retornos; confirmação de consultas pelo WhatsApp (mensagem pronta); status de confirmação.
- Pacientes: cadastro, WhatsApp, origem, histórico de orçamentos, vendas e sessões.
- Retornos: fila de retornos a vencer/atrasados, chamada no WhatsApp.
- Orçamentos e vendas: orçamento em rascunho → aceite do paciente → venda confirmada. Follow-up de orçamento enviado sem resposta.
- Protocolos: procedimento com produtos, sessões e preços (custo, sugerido, mínimo, especial).
- Estoque: a baixa acontece no tratamento, não na venda. Custo real do que foi aplicado.
- Tratamentos: o profissional informa o que foi aplicado na sessão.
- Comissões: regras por vendedor ou profissional; lançamentos do mês.
- Metas: metas por colaborador no mês (agenda, vendas, leads abordados).
- Financeiro: taxas de cartão (maquininha), impostos, despesas (fixas, variáveis, marketing, pessoal, depreciação de equipamentos) e DRE mensal com margem de contribuição, margem líquida e ponto de equilíbrio.
- Métricas: comercial, funil de vendas, operação e estoque no mesmo sistema.

WhatsApp: o sistema abre a conversa (wa.me) com modelo de mensagem; não é disparo automático pago.

## Preço

Não há tabela pública de preços neste site. Não invente valores, planos, descontos, depoimentos, logos de clientes nem estatísticas. Para começar: criar a clínica no sistema.

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

1. O lead entra no sistema, não no chat pessoal.
2. Agendar e confirmar no WhatsApp.
3. Orçar, o paciente aceita, a venda fecha. O produto ainda não sai da geladeira.
4. No tratamento, informar o que foi aplicado. Aí o estoque e o custo fecham.
5. Fechar o mês no DRE: margem de contribuição, margem líquida, ponto de equilíbrio.

## Perguntas frequentes

${faqs.map((item) => `### ${item.q}\n\n${item.a}`).join('\n\n')}
`
