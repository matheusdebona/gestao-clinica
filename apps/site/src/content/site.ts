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
export const SITE_TITLE = `${APP_NAME}: faturou, mas não sobrou nada no bolso?`
export const SITE_ONE_LINER =
  'HOF Pay é o sistema da clínica de harmonização no celular: leads, agenda, orçamentos, estoque e financeiro, para você saber quanto sobra de verdade no fim do mês.'

export const HERO_KICKER = 'Clínica de harmonização e estética avançada'
export const HERO_TITLE_LEAD = 'Faturou, mas não sobrou nada no bolso?'
export const HERO_TITLE_EMPHASIS = 'O HOF Pay mostra onde está seu lucro.'
export const HERO_LEAD =
  'Com o HOF Pay você sai das planilhas, gerencia a clínica pelo celular e vê quanto sobra de verdade depois da maquininha, impostos, comissões e despesas.'

export const CTA_REGISTER_LABEL = 'Comece grátis'
export const CTA_REGISTER_STRONG_LABEL = 'Criar minha clínica grátis'
export const CTA_LOGIN_LABEL = 'Já tenho conta'
export const CTA_LOGIN_SHORT_LABEL = 'Entrar'
export const CTA_FREE_NOTE = 'É grátis. Sem cartão de crédito.'

export const MOCK_CAPTION = 'Tela de exemplo. Números de demonstração, não são de uma clínica real.'
export const HERO_STAGE_CAPTION = 'No lugar da planilha de fechamento, o DRE do mês no celular.'

export const PAIN_KICKER = 'O problema hoje'
export const PAIN_TITLE = 'O faturamento entra, mas a planilha não diz o que sobrou.'

export const FEATURES_KICKER = 'O sistema'
export const FEATURES_TITLE = 'Lead, agenda, estoque e financeiro no mesmo sistema.'
export const FEATURES_LEAD =
  'Para quem é dono da clínica: o lucro, a equipe e o estoque sob controle, sem planilha paralela.'

export const RHYTHM_KICKER = 'Como funciona'
export const RHYTHM_TITLE = 'Do primeiro WhatsApp até o fechamento do mês.'
export const RHYTHM_LEAD =
  'O lead entra no sistema, a sessão é agendada e confirmada, o orçamento vira venda e o estoque só baixa quando o produto é aplicado. No fim do mês o DRE mostra o lucro depois da taxa da maquininha, dos impostos, das comissões e das despesas.'

export const MOBILE_KICKER = 'No celular'
export const MOBILE_TITLE = 'Use o sistema no celular, não só no computador da recepção.'
export const MOBILE_COPY =
  'Confirme a sessão no corredor, cobre o orçamento no balcão e abra o DRE quando quiser. A doutor·a, a recepção e a equipe acompanham no telefone a fila do dia, o paciente que parou de responder e o resultado do mês.'

export const CLINICS_KICKER = 'Para quem aplica'
export const CLINICS_TITLE = 'Feito para clínica de harmonização, estética e procedimentos injetáveis.'

export const FAQ_KICKER = 'Perguntas'
export const FAQ_TITLE = 'Perguntas frequentes'

export const ACCESS_KICKER = 'Começar'
export const ACCESS_TITLE = 'Crie a clínica e acompanhe o lucro do mês.'
export const ACCESS_COPY =
  'O cadastro abre o sistema na hora. Você já entra em leads, agenda e no DRE pelo celular.'

export const FOOTER_LUCRO_LABEL = 'Lucro do mês'

export function formatDemoBrl(n: number): string {
  return n.toLocaleString('pt-BR')
}

/** Demo DRE: resultado = receita bruta − todas as linhas; margem = round(resultado ÷ receita). */
export const DRE_DEMO = {
  receitaBruta: 52_000,
  insumos: 24_280,
  taxaMaquininha: 2_180,
  impostos: 1_620,
  comissoes: 4_160,
  despesas: 9_920,
} as const

export const DRE_DEMO_RESULTADO =
  DRE_DEMO.receitaBruta -
  DRE_DEMO.insumos -
  DRE_DEMO.taxaMaquininha -
  DRE_DEMO.impostos -
  DRE_DEMO.comissoes -
  DRE_DEMO.despesas

export const DRE_DEMO_MARGEM = Math.round((DRE_DEMO_RESULTADO / DRE_DEMO.receitaBruta) * 100)

export const painItems = [
  {
    id: 'planilha',
    title: 'Na planilha, o lucro do mês não fica claro',
    copy: 'Taxa da maquininha, imposto, comissão, aluguel e compra de produto ficam em lugares diferentes. No fim do mês fica difícil saber se a clínica lucrou ou teve prejuízo.',
  },
  {
    id: 'leads',
    title: 'O paciente interessado some no WhatsApp',
    copy: 'A mensagem chega no WhatsApp da recepção, cai no celular de alguém e ninguém faz o acompanhamento. O lead esfria sem a equipe perceber.',
  },
  {
    id: 'agenda',
    title: 'Mais faltas porque a sessão não foi confirmada',
    copy: 'A sessão de amanhã está marcada, mas ninguém enviou a confirmação pelo WhatsApp. O retorno também fica sem cobrança, e o horário vago.',
  },
] as const

export const showcases = [
  {
    id: 'lucro',
    kicker: 'Fechamento',
    title: 'Descubra quanto sobrou no fim do mês.',
    copy: 'O DRE junta receita, insumos, taxa da maquininha, impostos, comissões e despesas. Você vê margem de contribuição, margem líquida e ponto de equilíbrio: o lucro depois de tudo que saiu.',
    points: [
      'A taxa da maquininha e os impostos entram na conta, não ficam de fora.',
      'Comissão, despesa do mês e depreciação de equipamento aparecem no mesmo demonstrativo.',
      'Você acompanha margem de contribuição, margem líquida e ponto de equilíbrio.',
    ],
    mock: 'dre' as const,
  },
  {
    id: 'funil',
    kicker: 'Leads',
    title: 'Nenhum paciente interessado fica sem resposta.',
    copy: 'Do primeiro WhatsApp até a venda, o lead fica no funil: criado, abordado, respondeu, agendou, compareceu, orçamento e venda. A recepção cobra o paciente com uma mensagem pronta e personalizável, que abre direto no seu WhatsApp. Lead parado aparece na fila do dia.',
    points: [
      'Funil até a venda, com filtro por origem e por colaborador.',
      'Um toque abre a conversa no seu WhatsApp com o texto já escrito. Você envia e o contato fica registrado.',
      'A fila de acompanhamento mostra o lead que parou de responder.',
    ],
    mock: 'funnel' as const,
  },
  {
    id: 'agenda',
    kicker: 'Agenda',
    title: 'Menos faltas com a mensagem de confirmação pronta.',
    copy: 'A agenda da sessão e do retorno fica no celular. A mensagem de confirmação é pronta e personalizável: um toque abre o seu WhatsApp com o texto já escrito. A equipe envia e marca quem confirmou, quem desmarcou e quem ainda não respondeu.',
    points: [
      'Para as sessões de amanhã, a mensagem de confirmação abre pronta no seu WhatsApp.',
      'Para retorno a vencer ou atrasado, também há mensagem pronta para chamar o paciente.',
      'A agenda mostra a sessão com paciente e procedimento, não só o horário.',
    ],
    mock: 'agenda' as const,
  },
] as const

export const features = [
  {
    id: 'financeiro',
    title: 'Financeiro',
    teaser: 'DRE do mês com taxa da maquininha, imposto, comissão e despesa, para ver o lucro real.',
    href: '#lucro',
  },
  {
    id: 'resultado',
    title: 'Vendas e margem',
    teaser: 'Quanto cada produto vendeu e faturou no período, a margem de cada protocolo e quanto as cortesias custaram.',
    href: '#lucro',
  },
  {
    id: 'leads',
    title: 'Leads',
    teaser: 'Funil até a venda e mensagens prontas e personalizáveis para cobrar o paciente que parou de responder.',
    href: '#funil',
  },
  {
    id: 'agenda',
    title: 'Agenda',
    teaser: 'Sessões, retornos e mensagem de confirmação pronta, que abre no seu WhatsApp.',
    href: '#agenda',
  },
  {
    id: 'retornos',
    title: 'Retornos',
    teaser: 'O prazo de retorno do protocolo gera a data de volta de cada paciente. Você vê quem está para voltar e quem já passou do prazo.',
    href: '#agenda',
  },
  {
    id: 'pacientes',
    title: 'Pacientes',
    teaser: 'Cadastro, origem, histórico e faixa do paciente (Bronze, Prata, Ouro e Diamante) pelo quanto investiu na clínica nos últimos 12 meses.',
    href: '#produto',
  },
  {
    id: 'vendas',
    title: 'Orçamentos e vendas',
    teaser: 'Orçamento em PDF com a logo da clínica. Aceito, vira venda. O que ficou parado entra na fila de cobrança.',
    href: '#produto',
  },
  {
    id: 'protocolos',
    title: 'Protocolos',
    teaser: 'Produto, sessão e preço definidos, para não improvisar na hora do atendimento.',
    href: '#produto',
  },
  {
    id: 'estoque',
    title: 'Estoque',
    teaser: 'A baixa acontece quando o produto é aplicado, não na venda. Você recebe aviso de estoque baixo e do que vai faltar para a agenda de amanhã.',
    href: '#produto',
  },
  {
    id: 'equipe',
    title: 'Equipe e remuneração',
    teaser: 'Comissão por venda, meta do mês e remuneração por meta batida ou por faixa de faturamento da clínica, calculadas para cada colaborador.',
    href: '#produto',
  },
  {
    id: 'acessos',
    title: 'Acessos por função',
    teaser: 'Recepção, vendas, estoque e profissional veem só o que precisam. Financeiro e faturamento ficam com o administrador.',
    href: '#produto',
  },
] as const

export const rhythmSteps = [
  {
    n: '01',
    title: 'O lead entra no sistema, não no chat pessoal',
    copy: 'WhatsApp, prazo e responsável ficam visíveis para a equipe. O paciente não some da fila.',
  },
  {
    n: '02',
    title: 'Agende a sessão e envie a confirmação pronta',
    copy: 'A sessão entra na agenda. Um toque abre o seu WhatsApp com a mensagem de confirmação pronta e personalizável. A equipe envia e marca quem ainda não respondeu.',
  },
  {
    n: '03',
    title: 'Envie o orçamento e feche a venda',
    copy: 'O orçamento começa como rascunho. Quando o paciente aceita, a venda é confirmada. O produto ainda não sai do estoque.',
  },
  {
    n: '04',
    title: 'Aplique e registre o que foi usado',
    copy: 'Na sessão, o profissional de harmonização informa o que foi aplicado. Aí o estoque e o custo fecham.',
  },
  {
    n: '05',
    title: 'Feche o mês no DRE',
    copy: 'Receita, taxa da maquininha, imposto, comissão e despesa entram no mesmo demonstrativo. A margem líquida mostra se deu lucro ou prejuízo.',
  },
] as const

export const clinicLine =
  'Harmonização e estética avançada, dermatologia, odontologia e procedimentos injetáveis.'

export const faqs = [
  {
    id: 'planilha',
    q: 'O HOF Pay substitui a planilha da clínica?',
    a: 'Sim. Lead, agenda, paciente, orçamento, venda, estoque, comissão, despesa e o DRE do mês ficam no mesmo sistema, no celular. Você não precisa de uma planilha paralela para o fechamento.',
  },
  {
    id: 'lucro',
    q: 'Como eu sei se a clínica deu lucro ou prejuízo no mês?',
    a: 'Pelo DRE. Entram receita, insumo, taxa da maquininha, imposto, comissão, perda de estoque e despesa. O resultado mostra margem de contribuição, margem líquida e ponto de equilíbrio.',
  },
  {
    id: 'remuneracao',
    q: 'Dá para pagar a equipe por meta ou por faixa de faturamento?',
    a: 'Sim. Você define a meta do mês de cada colaborador e quanto paga por meta batida, por agendamento ou por faixa de faturamento da clínica. O HOF Pay calcula o valor de cada pessoa no mês, junto com as comissões, e esse custo entra no DRE.',
  },
  {
    id: 'permissoes',
    q: 'A recepção vê o faturamento da clínica?',
    a: 'Não. Cada pessoa entra com a sua função (recepção, vendas, estoque ou profissional) e vê só o que precisa no dia a dia. Financeiro, DRE e métricas de faturamento ficam com o administrador.',
  },
  {
    id: 'celular',
    q: 'Funciona no celular?',
    a: 'Sim. Foi feito para o celular. A doutor·a, a recepção e a equipe usam no dia a dia: agenda, confirmação, lead e o DRE. Não depende do computador da recepção.',
  },
  {
    id: 'whatsapp',
    q: 'O HOF Pay envia mensagens pelo WhatsApp sozinho?',
    a: 'Não. O HOF Pay não é integrado ao WhatsApp e não envia mensagens automáticas. Ele tem mensagens prontas e personalizáveis para confirmação de consulta, cobrança de lead, retorno do paciente e orçamento parado. Com um toque, abre a conversa no seu WhatsApp com o texto já escrito. Você pode personalizar os textos nas configurações da clínica, em Mensagens do WhatsApp. Você revisa e envia. O contato fica registrado no sistema.',
  },
  {
    id: 'estoque',
    q: 'A venda já baixa o estoque?',
    a: 'Não. A venda acontece primeiro. A baixa é no tratamento, quando o profissional de harmonização informa o que foi de fato aplicado.',
  },
  {
    id: 'orcamento',
    q: 'Como o orçamento vira venda?',
    a: 'O orçamento começa como rascunho da venda. Quando o paciente aceita, a venda pode ser confirmada. Agenda e consumo ficam na etapa de tratamento. Orçamento enviado sem resposta entra na fila de cobrança.',
  },
  {
    id: 'acesso',
    q: 'Como eu começo?',
    a: 'Crie a clínica em app.hofpay.com.br. É grátis. Sem cartão de crédito. O cadastro abre o sistema e o primeiro usuário administrador. Quem já tem conta entra pelo login.',
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

HOF Pay é um sistema de gestão para clínicas de harmonização e estética avançada. Este site não pede login e não se conecta ao sistema da clínica. O produto operacional está em app.hofpay.com.br.

Idioma: pt-BR.

Mensagem central: faturou, mas não sobrou nada no bolso? O HOF Pay mostra onde está o lucro. Sair das planilhas, gerenciar a clínica pelo celular e ver o lucro real depois da maquininha, impostos, comissões e despesas (margem líquida no DRE).

## Para quem

Clínicas de harmonização e estética avançada, dermatologia, odontologia e procedimentos injetáveis. Profissional de harmonização, recepção e a equipe. No celular e no consultório.

Não use “cliente” para a pessoa atendida: no produto é paciente. Não descreva o público como “médico/médica”; use profissional de harmonização ou doutor·a.

## Produto (o que existe hoje)

- Leads: cadastro, funil (lead criado → abordado → respondeu → agendou → compareceu → orçamento → venda), follow-up com mensagem pronta que abre no WhatsApp, prazos de lead parado.
- Agenda: sessões e retornos; mensagem pronta de confirmação de consulta, que abre no WhatsApp; status de confirmação marcado pela equipe.
- Pacientes: cadastro, WhatsApp, origem, histórico de orçamentos, vendas e sessões. Faixa do paciente (padrão Bronze, Prata, Ouro e Diamante, editáveis) pelo total de vendas confirmadas nos últimos 12 meses.
- Retornos: a venda de um protocolo com prazo de retorno gera a data de volta do paciente; fila de retornos a vencer/atrasados, com mensagem pronta para chamar o paciente no WhatsApp.
- Orçamentos e vendas: orçamento em rascunho → aceite do paciente → venda confirmada. Orçamento em PDF com a logo da clínica. Follow-up de orçamento enviado sem resposta.
- Protocolos: procedimento com produtos, sessões e preços (custo, sugerido, mínimo, especial).
- Estoque: a baixa acontece no tratamento, não na venda. Custo real do que foi aplicado. Aviso no sistema de estoque baixo e de estoque projetado para a agenda do dia seguinte.
- Tratamentos: o profissional informa o que foi aplicado na sessão.
- Comissões: regras por vendedor ou profissional; lançamentos do mês.
- Metas e remuneração: metas por colaborador no mês (agenda, vendas, leads abordados); remuneração por ocorrência, bônus por meta batida, faixas por agendamentos e faixas por faturamento da clínica, calculadas no mês e lançadas junto com as comissões (entram no DRE).
- Financeiro: taxas de cartão (maquininha), impostos, despesas (fixas, variáveis, marketing, pessoal, depreciação de equipamentos) e DRE mensal com margem de contribuição, margem líquida e ponto de equilíbrio.
- Métricas: comercial, funil de vendas, operação e estoque no mesmo sistema. Vendas por produto (unidades e faturamento de cada produto no período, já com descontos), margem de contribuição por protocolo, profissional, vendedor e origem, e custo das cortesias.
- Usuários e permissões: cada pessoa entra com a sua função (recepção, vendas, estoque, profissional); financeiro, DRE e métricas de faturamento ficam com o administrador.

WhatsApp: o HOF Pay não é integrado ao WhatsApp e não envia mensagens automáticas. Ele tem mensagens prontas e personalizáveis (a clínica edita os textos em Configurações > Mensagens do WhatsApp) e abre a conversa (link wa.me) no WhatsApp de quem está usando; a pessoa revisa e toca em enviar. Não descreva como integração, API, disparo ou lembrete automático.

## Preço

Não há tabela pública de preços neste site. Não invente valores, planos, descontos, depoimentos, logos de clientes, estatísticas, prazo de teste nem “para sempre”. O cadastro da clínica é grátis e não pede cartão. Crie a clínica no sistema.

## URLs

- Site: ${SITE_URL}
- Comece grátis: ${APP_REGISTER_URL}
- Entrar: ${APP_LOGIN_URL}
- Sistema da clínica: https://${APP_HOST}
- API interna do sistema (não é documentação pública): https://${API_HOST}

## Mais

- [llms-full.txt](${SITE_URL}/llms-full.txt): perguntas frequentes e o ritmo da clínica
- [Página inicial](${SITE_URL}/)
`

export const llmsFullTxt = `${llmsTxt}

## Como o trabalho da clínica anda no sistema

1. O lead entra no sistema, não no chat pessoal.
2. Agendar a sessão e enviar a confirmação: a mensagem pronta e personalizável abre no WhatsApp da equipe.
3. Enviar o orçamento; quando o paciente aceita, a venda é confirmada. O produto ainda não sai do estoque.
4. No tratamento, informar o que foi aplicado. Aí o estoque e o custo fecham.
5. Fechar o mês no DRE: margem de contribuição, margem líquida e ponto de equilíbrio.

## Perguntas frequentes

${faqs.map((item) => `### ${item.q}\n\n${item.a}`).join('\n\n')}
`
