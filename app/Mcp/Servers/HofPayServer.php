<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AgendaTool;
use App\Mcp\Tools\BuscarPacientesTool;
use App\Mcp\Tools\DreTool;
use App\Mcp\Tools\EstoqueBaixoTool;
use App\Mcp\Tools\LeadsTool;
use App\Mcp\Tools\MetasDoMesTool;
use App\Mcp\Tools\OrcamentosTool;
use App\Mcp\Tools\PacienteDetalheTool;
use App\Mcp\Tools\ResumoDoMesTool;
use App\Mcp\Tools\RetornosPendentesTool;
use App\Mcp\Tools\VendasPorProdutoTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('HOF Pay')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
HOF Pay é o sistema de gestão da clínica. Este conector é SÓ LEITURA: nenhuma ferramenta cadastra, altera ou apaga dados.
Os dados são só da clínica da conta conectada e respeitam as permissões dessa conta no app.
Datas e meses seguem o calendário de Brasília (America/Sao_Paulo); informe meses como AAAA-MM e datas como AAAA-MM-DD.
Valores são em reais (R$). Faturamento = vendas confirmadas, pela data da venda.
Use os termos do app: "paciente" (nunca "cliente"), "consulta" para o primeiro atendimento e "profissional" para quem atende.
Por privacidade, telefones, e-mails, CPF e anotações clínicas não são enviados, e as buscas são só por nome.
TXT)]
class HofPayServer extends Server
{
    /** Todas as ferramentas estendem HofPayTool (só leitura, com auditoria). */
    protected array $tools = [
        ResumoDoMesTool::class,
        VendasPorProdutoTool::class,
        DreTool::class,
        AgendaTool::class,
        LeadsTool::class,
        BuscarPacientesTool::class,
        PacienteDetalheTool::class,
        MetasDoMesTool::class,
        EstoqueBaixoTool::class,
        RetornosPendentesTool::class,
        OrcamentosTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
