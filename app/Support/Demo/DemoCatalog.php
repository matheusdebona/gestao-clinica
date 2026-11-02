<?php

namespace App\Support\Demo;

/**
 * Catálogo, protocolos e textos fictícios usados pelo seed de demonstração.
 */
final class DemoCatalog
{
    /**
     * Produtos: chave => [marca, tipo, unidade (símbolo), nome, custo, venda, mínimo, estoque mínimo, embalagem de compra].
     * As 5 chaves R_* reaproveitam os produtos Rennova que já existirem na clínica (pelo nome).
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float, 6: float, 7: float, 8: float}>
     */
    public const PRODUCTS = [
        'R_LIFT' => ['Rennova', 'Preenchedor', 'seringa', 'Lift Ultra Volume Lido', 224.99, 382.48, 337.49, 0, 1],
        'R_LIFT2' => ['Rennova', 'Preenchedor', 'seringa', 'Lift Ultra Volume Lido 2ml', 401.99, 683.38, 602.99, 0, 1],
        'R_DEEP' => ['Rennova', 'Preenchedor', 'seringa', 'Lift Deep Line Lido', 231.99, 394.38, 347.99, 0, 1],
        'R_DIAM' => ['Rennova', 'Preenchedor', 'seringa', 'Diamond Intense c/ Agulha TSK', 418.99, 712.28, 628.49, 0, 1],
        'R_CAN' => ['Rennova', 'Cânula', 'un', 'Caixa Perfect Cannula 1 Furo 22G x 50mm', 2.40, 9.99, 5.00, 25, 50],
        'BOTOX' => ['Allergan', 'Toxina botulínica', 'frasco', 'Botox 100U', 1090.00, 4980.00, 3980.00, 2, 1],
        'DYSPORT' => ['Galderma', 'Toxina botulínica', 'frasco', 'Dysport 300U', 1290.00, 5580.00, 4480.00, 1, 1],
        'SCULPTRA' => ['Galderma', 'Bioestimulador de colágeno', 'frasco', 'Sculptra 150mg', 1390.00, 3290.00, 2690.00, 4, 1],
        'VITAL' => ['Galderma', 'Skinbooster', 'seringa', 'Restylane Skinboosters Vital 1ml', 480.00, 1290.00, 1050.00, 4, 1],
        'RADIESSE' => ['Merz', 'Bioestimulador de colágeno', 'seringa', 'Radiesse 1,5ml', 1090.00, 2690.00, 2190.00, 2, 1],
        'PDO' => ['I-Thread', 'Fios de PDO', 'un', 'Fio PDO espiculado 19G', 85.00, 290.00, 230.00, 20, 10],
        'HYALO' => ['Biometil', 'Enzima', 'frasco', 'Hyalozima 2000 UTR (hialuronidase)', 160.00, 590.00, 450.00, 3, 1],
        'LIDO' => ['DFL', 'Anestésico', 'un', 'Lidocaína 2% c/ epinefrina (tubete)', 2.30, 0, 0, 30, 10],
        'LUVA' => ['Descarpack', 'Descartáveis', 'par', 'Luva nitrílica de procedimento', 0.70, 0, 0, 100, 50],
        'SERINGA' => ['BD', 'Descartáveis', 'un', 'Seringa 1ml Luer Lock', 1.20, 0, 0, 50, 100],
        'AGULHA' => ['BD', 'Descartáveis', 'un', 'Agulha 30G 13mm', 0.35, 0, 0, 100, 100],
    ];

    /** Produtos que terminam abaixo do estoque mínimo (alerta de estoque na demonstração). */
    public const LOW_AT_END = ['SCULPTRA', 'VITAL', 'HYALO'];

    /**
     * Protocolos: chave => [nome, itens [produto => qtd], retorno em dias, plano de sessões].
     * Plano: lista de [dias após a 1ª sessão, duração (min), [produto => fração do vendido]].
     *
     * @var array<string, array{0: string, 1: array<string, float>, 2: int|null, 3: list<array{0: int, 1: int, 2: array<string, float>}>}>
     */
    public const PROTOCOLS = [
        'TOX_FULL' => ['Toxina botulínica full face (Botox)', ['BOTOX' => 0.5], 15, [[0, 30, ['BOTOX' => 1]]]],
        'TOX_SUP' => ['Toxina botulínica terço superior (Botox)', ['BOTOX' => 0.25], 15, [[0, 30, ['BOTOX' => 1]]]],
        'DYS' => ['Toxina botulínica full face (Dysport)', ['DYSPORT' => 0.5], 15, [[0, 30, ['DYSPORT' => 1]]]],
        'LAB' => ['Preenchimento labial', ['R_LIFT' => 1, 'R_CAN' => 1], 180, [[0, 60, ['R_LIFT' => 1, 'R_CAN' => 1]]]],
        'OLH' => ['Preenchimento de olheiras', ['R_DIAM' => 1, 'R_CAN' => 1], 180, [[0, 60, ['R_DIAM' => 1, 'R_CAN' => 1]]]],
        'MAND' => ['Contorno mandibular', ['R_LIFT2' => 2, 'R_CAN' => 2], 180, [[0, 60, ['R_LIFT2' => 1, 'R_CAN' => 1]]]],
        'SULCO' => ['Sulco nasogeniano', ['R_DEEP' => 1, 'R_CAN' => 1], 180, [[0, 60, ['R_DEEP' => 1, 'R_CAN' => 1]]]],
        'SCUL' => ['Bioestimulador de colágeno — Sculptra (2 sessões)', ['SCULPTRA' => 2], 90, [[0, 60, ['SCULPTRA' => 0.5]], [30, 60, ['SCULPTRA' => 0.5]]]],
        'RAD' => ['Bioestimulador de colágeno — Radiesse', ['RADIESSE' => 1], 180, [[0, 60, ['RADIESSE' => 1]]]],
        'PDO' => ['Fios de PDO — sustentação (10 fios)', ['PDO' => 10], 180, [[0, 90, ['PDO' => 1]]]],
        'SKIN' => ['Skinbooster — 3 sessões', ['VITAL' => 3], 30, [[0, 60, ['VITAL' => 1 / 3]], [30, 60, ['VITAL' => 1 / 3]], [60, 60, ['VITAL' => 1 / 3]]]],
        'HFF' => ['Harmonização facial full face', ['BOTOX' => 0.5, 'R_LIFT2' => 2, 'R_DIAM' => 1, 'R_LIFT' => 1, 'RADIESSE' => 1, 'PDO' => 10, 'R_CAN' => 4], 180, [
            [0, 120, ['BOTOX' => 1, 'R_LIFT2' => 1, 'R_DIAM' => 1, 'R_LIFT' => 1, 'R_CAN' => 1]],
            [15, 90, ['RADIESSE' => 1, 'PDO' => 1]],
        ]],
        'ENZ' => ['Correção com enzima (hialuronidase)', ['HYALO' => 1], null, [[0, 30, ['HYALO' => 1]]]],
    ];

    /**
     * Perfis de compra por faixa (patient tiers da clínica: compras dos últimos 12 meses).
     * Cada perfil: [rótulo, desconto, lista de compras (cada compra = protocolos)].
     *
     * @var array<string, list<array{0: float, 1: list<list<string>>}>>
     */
    public const PROFILES = [
        'vip' => [[0.03, [['HFF'], ['SCUL', 'TOX_FULL', 'SKIN']]]],
        'excelente' => [[0.03, [['HFF'], ['SKIN', 'TOX_SUP', 'OLH']]]],
        'muito_bom' => [[0.03, [['SCUL', 'TOX_FULL', 'RAD']]]],
        'bom' => [[0.03, [['PDO', 'TOX_FULL', 'LAB']]], [0.03, [['SKIN', 'TOX_FULL']]], [0.03, [['RAD', 'TOX_FULL', 'OLH']]]],
        'baixo' => [[-1, [['TOX_FULL', 'OLH']]], [-1, [['PDO', 'SULCO']]], [-1, [['RAD', 'MAND']]], [-1, [['SKIN']]]],
        'muito_baixo' => [[-1, [['TOX_FULL']]], [-1, [['TOX_SUP', 'LAB']]], [-1, [['DYS']]], [-1, [['OLH']]], [-1, [['MAND']]], [-1, [['TOX_SUP']]], [-1, [['LAB', 'SULCO']]], [-1, [['ENZ', 'LAB']]]],
    ];

    /**
     * Compradores novos por mês (perfil => quantidade). As segundas compras de vip/excelente
     * entram no mês seguinte ao da primeira.
     *
     * @var array<int, array<string, int>> índice do mês no período (0 = primeiro mês)
     */
    public const DECKS = [
        0 => ['vip' => 2, 'excelente' => 1, 'bom' => 2, 'baixo' => 3, 'muito_baixo' => 7],
        1 => ['muito_bom' => 1, 'bom' => 1, 'baixo' => 3, 'muito_baixo' => 10],
    ];

    /** Leads por dia útil e por mês do período (fim de semana pesa menos). */
    public const LEADS_PER_DAY = [0 => 1.75, 1 => 2.45, 2 => 2.2];

    /** Origem => peso. */
    public const ORIGINS = [
        'Instagram' => 30, 'Indicação' => 18, 'Google' => 14, 'WhatsApp' => 14,
        'Facebook' => 10, 'Site' => 6, 'TikTok' => 4, 'Fachada' => 4,
    ];

    public const FEMALE = [
        'Ana', 'Beatriz', 'Camila', 'Carolina', 'Daniela', 'Débora', 'Eduarda', 'Fernanda', 'Gabriela', 'Helena',
        'Isabela', 'Jéssica', 'Juliana', 'Larissa', 'Letícia', 'Luana', 'Mariana', 'Marina', 'Natália', 'Patrícia',
        'Priscila', 'Rafaela', 'Renata', 'Sabrina', 'Tatiane', 'Thaís', 'Vanessa', 'Viviane', 'Aline', 'Bruna',
        'Cláudia', 'Elaine', 'Flávia', 'Giovana', 'Kátia', 'Lívia', 'Mônica', 'Paula', 'Roberta', 'Simone',
        'Talita', 'Yasmin', 'Adriana', 'Bianca', 'Cristiane', 'Denise', 'Fabiana', 'Lorena', 'Michele', 'Sandra',
    ];

    public const MALE = [
        'André', 'Bruno', 'Carlos', 'Diego', 'Eduardo', 'Felipe', 'Gustavo', 'Henrique', 'Leonardo', 'Marcelo',
        'Rodrigo', 'Thiago', 'Vinícius', 'Fábio', 'Ricardo', 'Renato',
    ];

    public const SURNAMES = [
        'Almeida', 'Araújo', 'Barbosa', 'Barros', 'Batista', 'Cardoso', 'Carvalho', 'Castro', 'Correia', 'Costa',
        'Cunha', 'Dias', 'Duarte', 'Farias', 'Fernandes', 'Freitas', 'Gomes', 'Lima', 'Lopes', 'Machado',
        'Martins', 'Melo', 'Mendes', 'Monteiro', 'Moraes', 'Moreira', 'Nascimento', 'Nunes', 'Oliveira', 'Pereira',
        'Pinto', 'Prado', 'Queiroz', 'Ramos', 'Reis', 'Ribeiro', 'Rocha', 'Rodrigues', 'Sales', 'Santana',
        'Santos', 'Siqueira', 'Soares', 'Souza', 'Tavares', 'Teixeira', 'Vasconcelos', 'Vieira', 'Xavier', 'Azevedo',
    ];

    public const INTERESTS = [
        'Interesse em toxina botulínica (rugas da testa e pés de galinha).',
        'Quer preenchimento labial com resultado natural.',
        'Olheiras profundas, pergunta sobre preenchimento.',
        'Flacidez facial, quer saber sobre bioestimulador de colágeno.',
        'Pediu valores de harmonização facial full face.',
        'Viu antes e depois no Instagram e quer saber sobre o contorno mandibular.',
        'Quer melhorar a qualidade da pele (skinbooster).',
        'Pergunta sobre fios de PDO para sustentação.',
        'Bigode chinês marcado, quer tratar o sulco nasogeniano.',
        'Fez preenchimento em outro lugar e não gostou, quer ver uma correção.',
        'Quer prevenir rugas, nunca fez procedimento.',
        'Pediu horário para consulta depois do trabalho.',
    ];

    public const LOST_REASONS = [
        'Achou o valor alto no momento.',
        'Vai pensar e retorna depois do pagamento.',
        'Preferiu clínica mais perto de casa.',
        'Sem resposta após 3 tentativas.',
        'Só queria saber o preço.',
        'Adiou para o próximo ano.',
    ];

    public const CANCEL_REASONS = [
        'Número não é do lead (WhatsApp errado).',
        'Lead duplicado (já falava com a recepção por outro número).',
        'Mensagem de spam / perfil falso.',
        'Pediu para não ser mais contatado.',
    ];

    public const COMMENTS = [
        'Enviei fotos de antes e depois do procedimento.',
        'Paciente perguntou sobre formas de pagamento e parcelamento.',
        'Prefere horários no fim da tarde.',
        'Enviei áudio explicando como funciona a consulta.',
        'Perguntou se a consulta tem custo. Expliquei que é cortesia.',
        'Quer vir com uma amiga que também tem interesse.',
    ];

    public const CHECKIN_WORKED = [
        'Respondi todos os leads do Instagram antes das 11h.',
        'Liguei para os leads parados há mais de 7 dias.',
        'Confirmei as consultas de amanhã por WhatsApp.',
        'Mandei áudio no primeiro contato e a taxa de resposta melhorou.',
        'Retomei orçamentos enviados na semana passada.',
    ];

    public const CHECKIN_CHANGE = [
        'Responder os leads novos em até 15 minutos.',
        'Oferecer dois horários fechados em vez de perguntar a disponibilidade.',
        'Mandar antes e depois logo na primeira mensagem.',
        'Fazer follow-up dos orçamentos no 3º dia.',
        'Separar um bloco de 30 minutos só para leads parados.',
    ];
}
