<?php

namespace App\Support\Demo;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CardBrand;
use App\Models\CardFeeRule;
use App\Models\CardOperator;
use App\Models\ClientOrigin;
use App\Models\Clinic;
use App\Models\CommissionRule;
use App\Models\EquipmentAsset;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GoalRemuneration;
use App\Models\PatientReturn;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Protocol;
use App\Models\RecurringExpense;
use App\Models\RemunerationTier;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\UserGoal;
use App\Services\CommissionService;
use App\Services\ExpenseGenerator;
use App\Services\PatientReturnService;
use App\Services\ProtocolPricingService;
use App\Services\StockService;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Popula UMA clínica com dados fictícios e coerentes (leads → consultas → orçamentos → vendas →
 * sessões → estoque → retornos → metas/comissões → despesas), sempre pelos serviços reais do app,
 * em ordem cronológica e com o relógio do app parado no instante de cada evento.
 *
 * Não envia nada: o comando que chama isto liga Mail/Notification/Queue fake antes.
 */
final class DemoClinicSeeder
{
    use DemoSeederHelpers;
    use DemoSeederPatients;

    public const TZ = 'America/Sao_Paulo';

    public const HOLIDAYS = ['2026-09-07', '2026-10-12', '2026-11-02', '2026-11-20', '2026-12-25'];

    private int $cid;

    private DemoTimeline $tl;

    /** @var array<string, int> */
    public array $stats = [];

    /** @var array<int, array{stock: string, cost: string}> produtos que já existiam (estoque/custo antes) */
    public array $preexistingProducts = [];

    private User $sdr;

    private User $seller;

    private User $pro1;

    private User $pro2;

    /** @var array<string, int> */
    private array $origins = [];

    /** @var array<string, int> */
    private array $campaigns = [];

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<string, Protocol> */
    private array $protocols = [];

    /** @var array<string, PaymentMethod> */
    private array $methods = [];

    private int $stoneId;

    /** @var array<string, int> */
    private array $cardBrands = [];

    /** @var array<int, array<string, list<array{0: int, 1: int}>>> */
    private array $busy = [];

    /** @var array<string, list<array{0: float, 1: list<list<string>>, 2: string}>> */
    private array $decks = [];

    /** @var array<string, int> */
    private array $pendingConsultas = [];

    /** @var array<int, list<array{0: CarbonImmutable, 1: float}>> */
    private array $planned = [];

    /** @var array<string, true> */
    private array $usedNames = [];

    private int $phoneSeq = 0;

    private ?CarbonImmutable $forceFirstSession = null;

    /** @var list<int> pacientes criados pelo seed (os sorteios de agenda futura só usam estes) */
    private array $demoClients = [];

    private CarbonImmutable $lowFrom;

    public function __construct(
        private readonly Clinic $clinic,
        private readonly CarbonImmutable $from,
        private readonly CarbonImmutable $to,
        private readonly CarbonImmutable $now,
        private readonly float $scale,
        int $seed,
    ) {
        $this->cid = (int) $clinic->id;
        $this->tl = new DemoTimeline;
        mt_srand($seed);
        $this->lowFrom = $this->to->subMonthNoOverflow()->startOfMonth()->addDays(18);
    }

    public function run(): void
    {
        CurrentClinic::setId($this->cid);
        $this->clock($this->from->setTime(7, 0));

        $this->setupReferences();
        $this->setupPeople();
        $this->setupCatalog();
        $this->setupConfig();
        $this->setupFinance();
        $this->buildDecks();

        $this->scheduleStock();
        $this->scheduleBasePatients();
        $this->scheduleDirectPatients();
        $this->scheduleFutureAgenda();
        $this->scheduleLeads();
        $this->scheduleCheckins();

        $this->tl->run($this->now, fn () => CurrentClinic::setId($this->cid));
        $this->stats['timeline_events'] = $this->tl->executed;

        Carbon::setTestNow();
        CurrentClinic::setId($this->cid);
        $this->closeOldReturns();
        $this->recalculateMonths();
        Carbon::setTestNow();
    }

    // ---------------------------------------------------------------- setup

    private function setupReferences(): void
    {
        foreach (array_keys(DemoCatalog::ORIGINS) as $name) {
            $origin = ClientOrigin::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? ClientOrigin::query()->create(['clinic_id' => $this->cid, 'name' => $name, 'is_active' => true]);
            $this->origins[$name] = (int) $origin->id;
        }

        foreach (['dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'boleto'] as $code) {
            $method = PaymentMethod::query()->where('code', $code)->first();
            if ($method === null) {
                throw new RuntimeException("Forma de pagamento '{$code}' não existe na clínica.");
            }
            $this->methods[$code] = $method;
        }

        $stone = CardOperator::query()->where('code', 'stone')->first()
            ?? CardOperator::query()->where('is_active', true)->orderBy('id')->first();
        if ($stone === null) {
            throw new RuntimeException('Nenhuma operadora de cartão na clínica.');
        }
        $this->stoneId = (int) $stone->id;
        foreach (['visa', 'mastercard'] as $code) {
            $brand = CardBrand::query()->where('code', $code)->first();
            if ($brand === null) {
                throw new RuntimeException("Bandeira '{$code}' não existe na clínica.");
            }
            $this->cardBrands[$code] = (int) $brand->id;
        }
    }

    private function setupPeople(): void
    {
        $this->sdr = $this->makeUser('Juliana Ramos', 'receptionist');
        $this->seller = $this->makeUser('Rafael Monteiro', 'seller');
        $this->pro1 = $this->makeUser('Camila Andrade', 'professional');
        $this->pro2 = $this->makeUser('Bruno Teixeira', 'professional');
        $this->stats['users'] = 4;
    }

    private function makeUser(string $name, string $role): User
    {
        $this->usedNames[$name] = true;
        $email = Str::slug($name, '.').'.demo'.$this->cid.'@example.com';
        if (User::query()->withoutGlobalScopes()->where('email', $email)->exists()) {
            throw new RuntimeException("Usuário {$email} já existe (rode o purge antes).");
        }
        // Senha aleatória, nunca exibida nem guardada: o colaborador fictício não tem login utilizável.
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Str::random(64),
            'clinic_id' => $this->cid,
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function setupCatalog(): void
    {
        $units = UnitOfMeasure::query()->get()->keyBy('symbol');
        foreach (DemoCatalog::PRODUCTS as $key => [$brandName, $typeName, $unit, $name, $cost, $sale, $min, $minStock]) {
            $existing = Product::query()->where('name', $name)->first();
            if ($existing !== null) {
                $this->products[$key] = $existing;
                $this->preexistingProducts[(int) $existing->id] = [
                    'stock' => (string) $existing->stock_quantity,
                    'cost' => (string) $existing->cost,
                ];

                continue;
            }
            $brand = Brand::query()->where('name', $brandName)->first()
                ?? Brand::query()->create(['clinic_id' => $this->cid, 'name' => $brandName, 'is_active' => true]);
            $type = ProductType::query()->where('brand_id', $brand->id)->where('slug', Str::slug($typeName))->first()
                ?? ProductType::query()->create([
                    'clinic_id' => $this->cid, 'brand_id' => $brand->id, 'name' => $typeName,
                    'slug' => Str::slug($typeName), 'is_active' => true,
                ]);
            $uom = $units->get($unit) ?? UnitOfMeasure::query()->create([
                'clinic_id' => $this->cid, 'name' => ucfirst($unit), 'symbol' => $unit, 'is_active' => true,
            ]);
            $units->put($unit, $uom);
            $this->products[$key] = Product::query()->create([
                'clinic_id' => $this->cid,
                'product_type_id' => $type->id,
                'brand_id' => $brand->id,
                'unit_of_measure_id' => $uom->id,
                'name' => $name,
                'cost' => $cost,
                'sale_price' => $sale,
                'min_sale_price' => $min,
                'stock_quantity' => 0,
                'min_stock' => $minStock,
                'lead_time_days' => in_array($key, ['LIDO', 'LUVA', 'SERINGA', 'AGULHA'], true) ? 2 : 5,
                'is_active' => true,
            ]);
            $this->bump('products');
        }

        $pricing = app(ProtocolPricingService::class);
        foreach (DemoCatalog::PROTOCOLS as $key => [$name, $items, $returnDays]) {
            $protocol = Protocol::query()->create([
                'clinic_id' => $this->cid,
                'name' => $name,
                'description' => 'Protocolo de demonstração.',
                'is_active' => true,
                'return_after_days' => $returnDays,
            ]);
            $pricing->syncItems($protocol, collect($items)->map(fn ($qty, $p) => [
                'product_id' => $this->products[$p]->id, 'quantity' => $qty,
            ])->values()->all());
            $this->protocols[$key] = $protocol->fresh();
            $this->bump('protocols');
        }
    }

    private function setupConfig(): void
    {
        $rule = fn (string $role, float $value, string $base, string $notes) => CommissionRule::query()->create([
            'clinic_id' => $this->cid, 'role' => $role, 'calc_type' => CommissionRule::TYPE_PERCENT,
            'value' => $value, 'base' => $base, 'is_active' => true, 'notes' => $notes,
        ]);
        $rule(CommissionRule::ROLE_SELLER, 3, CommissionRule::BASE_GROSS, 'Comissão de venda (demonstração)');
        $rule(CommissionRule::ROLE_PROFESSIONAL, 8, CommissionRule::BASE_NET, 'Comissão por sessão executada (demonstração)');

        $goals = [
            GoalRemuneration::METRIC_APPOINTMENTS => [80, 300],
            GoalRemuneration::METRIC_APPROACHED => [4, 0],
            GoalRemuneration::METRIC_SALES => [0, 500],
        ];
        foreach ($goals as $metric => [$per, $bonus]) {
            if (! GoalRemuneration::query()->where('metric', $metric)->exists()) {
                GoalRemuneration::query()->create([
                    'clinic_id' => $this->cid, 'metric' => $metric,
                    'per_occurrence_amount' => $per, 'target_bonus_amount' => $bonus,
                ]);
            }
        }
        if (! RemunerationTier::query()->where('kind', RemunerationTier::KIND_APPOINTMENTS)->whereNull('user_id')->exists()) {
            foreach ([[20, 250], [30, 500]] as [$threshold, $amount]) {
                RemunerationTier::query()->create([
                    'clinic_id' => $this->cid, 'kind' => RemunerationTier::KIND_APPOINTMENTS,
                    'threshold' => $threshold, 'fixed_amount' => $amount, 'percent' => 0,
                ]);
            }
        }
        RemunerationTier::query()->create([
            'clinic_id' => $this->cid, 'kind' => RemunerationTier::KIND_REVENUE, 'user_id' => $this->seller->id,
            'threshold' => 70000, 'fixed_amount' => 600, 'percent' => 0,
        ]);

        $month = $this->from;
        $i = 0;
        while ($month->lte($this->to)) {
            $m = $month->format('Y-m');
            UserGoal::query()->create([
                'clinic_id' => $this->cid, 'user_id' => $this->sdr->id, 'month' => $m,
                'appointments_target' => $i === 0 ? 20 : 25, 'approached_target' => $i === 0 ? 60 : 80,
            ]);
            UserGoal::query()->create([
                'clinic_id' => $this->cid, 'user_id' => $this->seller->id, 'month' => $m,
                'appointments_target' => 6, 'sales_target' => $i === 0 ? 45000 : 55000, 'approached_target' => 15,
            ]);
            $month = $month->addMonthNoOverflow();
            $i++;
        }

        $firstMonth = $this->from->toDateString();
        if (! TaxSetting::query()->whereDate('effective_from', '<=', $firstMonth)->exists()) {
            TaxSetting::query()->create([
                'clinic_id' => $this->cid, 'effective_from' => $firstMonth, 'regime' => TaxSetting::REGIME_SIMPLES,
                'simples_rate_percent' => 6, 'notes' => 'Demonstração',
            ]);
            $this->bump('tax_settings');
        }

        $credit = [1 => 3.15, 2 => 4.49, 3 => 5.09, 4 => 5.69, 5 => 6.29, 6 => 6.89, 7 => 7.49, 8 => 8.09, 9 => 8.69, 10 => 9.29];
        foreach ($this->cardBrands as $brandId) {
            foreach ($credit as $n => $pct) {
                $this->feeRule($this->methods['cartao_credito']->id, $brandId, $n, $pct);
            }
            $this->feeRule($this->methods['cartao_debito']->id, $brandId, 1, 1.37);
        }

        $this->campaigns['meta'] = $this->campaign('Instagram', 'Meta Ads — Harmonização');
        $this->campaigns['google'] = $this->campaign('Google', 'Google Ads — Pesquisa');
        $this->campaigns['rosa'] = $this->campaign('Instagram', 'Outubro Rosa');
    }

    private function feeRule(int $methodId, int $brandId, int $installments, float $pct): void
    {
        $exists = CardFeeRule::query()->where('payment_method_id', $methodId)->where('card_operator_id', $this->stoneId)
            ->where('card_brand_id', $brandId)->where('installments', $installments)->exists();
        if (! $exists) {
            CardFeeRule::query()->create([
                'clinic_id' => $this->cid, 'payment_method_id' => $methodId, 'card_operator_id' => $this->stoneId,
                'card_brand_id' => $brandId, 'installments' => $installments, 'fee_percent' => $pct, 'is_active' => true,
            ]);
        }
    }

    private function campaign(string $origin, string $name): int
    {
        $c = Campaign::query()->where('client_origin_id', $this->origins[$origin])->where('name', $name)->first()
            ?? Campaign::query()->create([
                'clinic_id' => $this->cid, 'client_origin_id' => $this->origins[$origin], 'name' => $name, 'is_active' => true,
            ]);

        return (int) $c->id;
    }

    private function setupFinance(): void
    {
        $cat = fn (string $name) => (int) (ExpenseCategory::query()->where('name', $name)->value('id')
            ?? throw new RuntimeException("Categoria de despesa '{$name}' não existe."));

        $recurring = [
            ['Aluguel', 'Aluguel da sala comercial', 4200, 5, 'Imobiliária (contrato de locação)'],
            ['Condomínio e IPTU', 'Condomínio + IPTU', 950, 10, null],
            ['Energia elétrica', 'Conta de energia', 780, 15, null],
            ['Água', 'Conta de água', 140, 15, null],
            ['Internet e telefone', 'Internet fibra + telefone', 249, 10, null],
            ['Software e sistemas', 'Sistemas de gestão e agenda', 299, 5, null],
            ['Contabilidade', 'Honorários contábeis', 790, 10, 'Escritório de contabilidade'],
            ['Limpeza e lavanderia', 'Limpeza e lavanderia', 650, 5, null],
            ['Seguros', 'Seguro do consultório', 180, 20, null],
            ['Salários', 'Salário — recepção', 2400, 5, null],
            ['Salários', 'Salário fixo — comercial', 2600, 5, null],
            ['Encargos e benefícios', 'Encargos, VT e VR', 1650, 20, null],
            ['Pró-labore', 'Pró-labore dos sócios', 3000, 5, null],
            ['Mídia externa (anúncios)', 'Meta Ads (Instagram/Facebook)', 2000, 25, 'Meta', 'Instagram', 'meta'],
            ['Mídia externa (anúncios)', 'Google Ads', 1000, 25, 'Google', 'Google', 'google'],
            ['Agência e produção de conteúdo', 'Social media e produção de conteúdo', 1200, 10, 'Agência de marketing'],
            ['Tarifas bancárias', 'Tarifas bancárias', 89, 28, null],
        ];

        $this->clock($this->from->setTime(0, 1));
        foreach ($recurring as $row) {
            RecurringExpense::query()->create([
                'clinic_id' => $this->cid,
                'expense_category_id' => $cat($row[0]),
                'description' => $row[1],
                'amount' => $row[2],
                'due_day' => $row[3],
                'starts_month' => $this->from->toDateString(),
                'supplier' => $row[4],
                'client_origin_id' => isset($row[5]) ? $this->origins[$row[5]] : null,
                'campaign_id' => isset($row[6]) ? $this->campaigns[$row[6]] : null,
                'is_active' => true,
            ]);
            $this->bump('recurring_expenses');
        }
        EquipmentAsset::query()->create([
            'clinic_id' => $this->cid,
            'expense_category_id' => $cat('Depreciação de equipamentos'),
            'name' => 'Mobiliário e equipamentos da sala (maca, foco, autoclave)',
            'purchase_value' => 24000,
            'residual_value' => 0,
            'purchased_on' => $this->from->subMonths(2)->toDateString(),
            'useful_life_months' => 60,
            'depreciation_start_month' => $this->from->toDateString(),
            'is_active' => true,
        ]);

        // Geração mensal (como o agendador das 00:05) e pagamento de cada conta no vencimento.
        $generator = app(ExpenseGenerator::class);
        $month = $this->from;
        while ($month->lte($this->to)) {
            $m = $month;
            $this->tl->at($m->setTime(0, 5), function () use ($generator, $m): void {
                $generator->generateMonth($this->cid, $m);
                $expenses = Expense::query()->whereDate('competence_month', $m->toDateString())
                    ->whereNotNull('due_on')->whereNull('paid_on')->get();
                foreach ($expenses as $expense) {
                    $due = CarbonImmutable::parse($expense->due_on->toDateString().' 10:00', self::TZ);
                    $this->tl->at($due, fn () => $expense->fresh()->update(['paid_on' => $due->toDateString()]));
                }
            });
            $month = $month->addMonthNoOverflow();
        }

        $oneOff = [
            [0, 9, 'Manutenção', 'Manutenção do ar-condicionado', 420],
            [0, 3, 'Material de escritório', 'Material de escritório e impressos', 230],
            [1, 2, 'Agência e produção de conteúdo', 'Ação Outubro Rosa (conteúdo + impulsionamento)', 1500],
            [1, 16, 'Material de escritório', 'Material de escritório', 180],
        ];
        foreach ($oneOff as [$mi, $day, $category, $desc, $amount]) {
            $date = $this->from->addMonthsNoOverflow($mi)->setDay($day)->setTime(11, 0);
            $rosa = str_contains($desc, 'Outubro Rosa');
            $this->tl->at($date, function () use ($date, $cat, $category, $desc, $amount, $rosa): void {
                Expense::query()->create([
                    'clinic_id' => $this->cid,
                    'expense_category_id' => $cat($category),
                    'description' => $desc,
                    'amount' => $amount,
                    'competence_month' => $date->startOfMonth()->toDateString(),
                    'due_on' => $date->toDateString(),
                    'paid_on' => $date->toDateString(),
                    'campaign_id' => $rosa ? $this->campaigns['rosa'] : null,
                    'client_origin_id' => $rosa ? $this->origins['Instagram'] : null,
                ]);
            });
        }
    }

    private function buildDecks(): void
    {
        $month = $this->from;
        $i = 0;
        while ($month->lte($this->to)) {
            $deck = [];
            foreach (DemoCatalog::DECKS[$i] ?? [] as $profile => $count) {
                $top = in_array($profile, ['vip', 'excelente'], true);
                $n = $top ? $count : max(1, (int) round($count * $this->scale));
                for ($k = 0; $k < $n; $k++) {
                    $options = DemoCatalog::PROFILES[$profile];
                    [$discount, $purchases] = $options[mt_rand(0, count($options) - 1)];
                    $deck[] = [$discount, $purchases, $profile];
                }
            }
            // vip/excelente primeiro (precisam de tempo para a segunda compra); o resto embaralhado.
            $top = array_values(array_filter($deck, fn ($p) => in_array($p[2], ['vip', 'excelente'], true)));
            $rest = array_values(array_filter($deck, fn ($p) => ! in_array($p[2], ['vip', 'excelente'], true)));
            $this->shuffle($rest);
            $this->decks[$month->format('Y-m')] = array_merge($top, $rest);
            $month = $month->addMonthNoOverflow();
            $i++;
        }
    }

    // ---------------------------------------------------------------- stock

    private function scheduleStock(): void
    {
        $this->tl->at($this->from->setTime(8, 0), fn () => $this->replenish(true));
        $day = $this->from->addDay();
        while ($day->lte($this->to)) {
            if ($day->isMonday()) {
                $d = $this->isBusinessDay($day) ? $day : $day->addDay();
                $this->tl->at($d->setTime(8, 30), fn () => $this->replenish(false));
            }
            $day = $day->addDay();
        }
        // Uma perda por validade no 2º mês (aparece na DRE como perda de estoque).
        $loss = $this->from->addMonthNoOverflow()->setDay(14)->setTime(17, 30);
        $this->tl->at($loss, function (): void {
            $p = $this->products['HYALO']->fresh();
            if ((float) $p->stock_quantity >= 1) {
                app(StockService::class)->move($p, StockMovementType::Out, '1', null, 'Descarte', 'Frasco vencido no estoque', $this->sdr, false, null, 'expired');
                $this->bump('stock_losses');
            }
        });
    }

    private function replenish(bool $opening): void
    {
        $now = CarbonImmutable::now();
        foreach ($this->products as $key => $product) {
            $product = $product->fresh();
            $spec = DemoCatalog::PRODUCTS[$key];
            if (! $opening && in_array($key, DemoCatalog::LOW_AT_END, true) && $now->gte($this->lowFrom)) {
                continue;
            }
            $need = 0.0;
            foreach ($this->planned[$product->id] ?? [] as [$at, $qty]) {
                if ($at->gt($now) && $at->lte($now->addDays(14))) {
                    $need += $qty;
                }
            }
            $weekly = match ($key) {
                'LUVA' => 60, 'SERINGA' => 25, 'AGULHA' => 60, 'LIDO' => 25,
                'BOTOX' => 2, 'PDO' => 10, 'R_CAN' => 10, 'VITAL' => 2, 'SCULPTRA' => 2, 'RADIESSE' => 1,
                default => 1,
            };
            // Itens que a demo deixa abaixo do mínimo: a última reposição cobre só o que já está agendado.
            $lowSoon = in_array($key, DemoCatalog::LOW_AT_END, true) && $now->gte($this->lowFrom->subDays(10));
            $target = $lowSoon ? $need : $need + max((float) $spec[7], 0) + $weekly;
            $stock = (float) $product->stock_quantity;
            if ($stock < $target) {
                $this->purchase($key, $target - $stock, 'Compra de reposição');
            }
        }
    }

    private function purchase(string $key, float $qty, string $reason): void
    {
        $spec = DemoCatalog::PRODUCTS[$key];
        $pack = max(1.0, (float) $spec[8]);
        $qty = ceil(round($qty, 4) / $pack) * $pack;
        if ($qty <= 0) {
            return;
        }
        $variance = 1 + (mt_rand(-30, 40) / 1000);
        app(StockService::class)->move(
            product: $this->products[$key]->fresh(),
            type: StockMovementType::In,
            quantity: (string) $qty,
            unitCost: number_format((float) $spec[4] * $variance, 4, '.', ''),
            reason: $reason,
            notes: "Fornecedor: distribuidor {$spec[0]} (demonstração)",
            user: $this->sdr,
        );
        $this->bump('stock_purchases');
    }

    private function ensureStock(string $key, float $qty): void
    {
        $stock = (float) $this->products[$key]->fresh()->stock_quantity;
        if ($stock >= $qty) {
            return;
        }
        $low = in_array($key, DemoCatalog::LOW_AT_END, true) && CarbonImmutable::now()->gte($this->lowFrom);
        $want = $low ? $qty - $stock : ($qty - $stock) + (float) DemoCatalog::PRODUCTS[$key][7];
        $this->purchase($key, $want, 'Compra emergencial');
    }

    // ---------------------------------------------------------------- wrap-up

    /** Retornos vencidos: a recepção chamou no WhatsApp e a maioria foi resolvida. */
    private function closeOldReturns(): void
    {
        $service = app(PatientReturnService::class);
        $returns = PatientReturn::query()->where('status', PatientReturn::STATUS_PENDING)
            ->whereDate('due_on', '<', $this->now->setTimezone(self::TZ)->subDays(3)->toDateString())
            ->orderBy('id')->get();
        foreach ($returns as $return) {
            $due = CarbonImmutable::parse($return->due_on->toDateString().' 10:00', self::TZ)->addMinutes(mt_rand(0, 300));
            if ($due->utc()->gt($this->now)) {
                continue;
            }
            Carbon::setTestNow($due->utc());
            $service->logWhatsApp($return, $this->sdr);
            if (mt_rand(1, 100) <= 60) {
                $service->markDone($return->fresh(), $this->sdr);
                $this->bump('returns_done');
            }
        }
        Carbon::setTestNow();
    }

    /** "Recalcular mês" de cada mês do período (comissões, metas e faixas). */
    private function recalculateMonths(): void
    {
        $month = $this->from;
        $current = $this->now->setTimezone(self::TZ)->format('Y-m');
        while ($month->lte($this->to) && $month->format('Y-m') <= $current) {
            app(CommissionService::class)->rebuildMonth($this->cid, $month->startOfMonth());
            $month = $month->addMonthNoOverflow();
        }
    }
}
