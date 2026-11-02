<?php

namespace App\Mcp\Support;

use RuntimeException;

/** Erro mostrado ao Claude como resposta da ferramenta (texto em português, sem detalhes internos). */
class McpToolException extends RuntimeException {}
