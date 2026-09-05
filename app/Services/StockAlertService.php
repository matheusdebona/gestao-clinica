<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Treatment;
use App\Models\User;
use App\Notifications\AppointmentStockWarningNotification;
use App\Notifications\LowStockProductNotification;
use App\Notifications\ProjectedLowStockNotification;
use App\Notifications\ReorderPointStockNotification;
use App\Support\CurrentClinic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class StockAlertService
{
    public const CONSUMPTION_WINDOW_DAYS = 30;

    public function __construct(private readonly TreatmentService $treatments) {}

    /**
     * @param  list<array{sale_item_id: int, product_id: int, product_name: string, quantity: string, stock_quantity: string}>  $suggested
     * @return list<string>
     */
    public function warningMessagesForSuggested(array $suggested): array
    {
        $messages = [];
        $productIds = collect($suggested)->pluck('product_id')->unique()->filter()->all();
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($suggested as $row) {
            /** @var Product|null $product */
            $product = $products->get($row['product_id']);
            if ($product === null) {
                continue;
            }

            $stock = (float) $product->stock_quantity;
            $min = (float) $product->min_stock;
            $needed = (float) $row['quantity'];

            if ($stock < $needed) {
                $messages[] = "Estoque insuficiente de {$product->name}: disponível {$product->stock_quantity}, previsto {$row['quantity']}.";
            } elseif ($stock <= $min) {
                $messages[] = "Estoque de {$product->name} está no/abaixo do mínimo ({$product->min_stock}); disponível {$product->stock_quantity}.";
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * @param  list<string>  $warnings
     */
    public function notifyAppointmentWarnings(Appointment $appointment, array $warnings): void
    {
        if ($warnings === []) {
            return;
        }

        $recipients = $this->recipientsForClinic((int) $appointment->clinic_id);
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new AppointmentStockWarningNotification($appointment, $warnings),
        );
    }

    public function runDailyChecks(?CarbonImmutable $day = null): void
    {
        $day ??= CarbonImmutable::now();

        Clinic::query()->orderBy('id')->each(function (Clinic $clinic) use ($day): void {
            $this->checkClinic($clinic, $day);
        });
    }

    public function checkClinic(Clinic $clinic, CarbonImmutable $day): void
    {
        $previousClinicId = CurrentClinic::id();
        CurrentClinic::setId($clinic->id);

        try {
            $recipients = $this->recipientsForClinic($clinic->id);
            if ($recipients->isEmpty()) {
                return;
            }

            $lowStockProducts = Product::query()
                ->where('clinic_id', $clinic->id)
                ->where('is_active', true)
                ->whereColumn('stock_quantity', '<=', 'min_stock')
                ->orderBy('name')
                ->get();

            foreach ($lowStockProducts as $product) {
                $this->notifyOncePerDay(
                    $recipients,
                    'low_stock',
                    (int) $product->id,
                    new LowStockProductNotification($product),
                );
            }

            $this->notifyReorderPoints($clinic->id, $recipients, $day);

            $plannedByProduct = $this->plannedUsageForDay($clinic->id, $day);
            if ($plannedByProduct === []) {
                return;
            }

            $products = Product::query()
                ->where('clinic_id', $clinic->id)
                ->whereIn('id', array_keys($plannedByProduct))
                ->get()
                ->keyBy('id');

            foreach ($plannedByProduct as $productId => $plannedQty) {
                /** @var Product|null $product */
                $product = $products->get($productId);
                if ($product === null || ! $product->is_active) {
                    continue;
                }

                if ($product->isLowStock()) {
                    continue;
                }

                $projected = (float) $product->stock_quantity - $plannedQty;
                if ($projected > (float) $product->min_stock) {
                    continue;
                }

                $this->notifyOncePerDay(
                    $recipients,
                    'projected_low_stock',
                    (int) $product->id,
                    new ProjectedLowStockNotification(
                        product: $product,
                        plannedQuantity: number_format($plannedQty, 4, '.', ''),
                        projectedQuantity: number_format($projected, 4, '.', ''),
                        day: $day,
                    ),
                );
            }
        } finally {
            if ($previousClinicId === null) {
                CurrentClinic::forget();
            } else {
                CurrentClinic::setId($previousClinicId);
            }
        }
    }

    /**
     * @return array<int, float> product_id => planned qty
     */
    public function plannedUsageForDay(int $clinicId, CarbonImmutable $day): array
    {
        $dayStart = $day->startOfDay();
        $dayEnd = $day->endOfDay();

        $treatmentIds = Appointment::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('status', [Appointment::STATUS_SCHEDULED, Appointment::STATUS_IN_PROGRESS])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
            ->pluck('treatment_id')
            ->unique()
            ->values();

        if ($treatmentIds->isEmpty()) {
            return [];
        }

        $planned = [];

        Treatment::query()
            ->where('clinic_id', $clinicId)
            ->whereIn('id', $treatmentIds)
            ->orderBy('id')
            ->each(function (Treatment $treatment) use (&$planned): void {
                foreach ($this->treatments->suggestedConsumptions($treatment) as $row) {
                    $productId = (int) $row['product_id'];
                    $planned[$productId] = ($planned[$productId] ?? 0.0) + (float) $row['quantity'];
                }
            });

        return $planned;
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notifyReorderPoints(int $clinicId, Collection $recipients, CarbonImmutable $day): void
    {
        $avgByProduct = $this->averageDailyConsumptionByProduct($clinicId, $day);
        if ($avgByProduct === []) {
            return;
        }

        $products = Product::query()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->where('lead_time_days', '>', 0)
            ->whereIn('id', array_keys($avgByProduct))
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            if ($product->isLowStock()) {
                continue;
            }

            $avgDaily = $avgByProduct[(int) $product->id] ?? 0.0;
            if ($avgDaily <= 0) {
                continue;
            }

            $reorderPoint = ($avgDaily * (int) $product->lead_time_days) + (float) $product->min_stock;
            if ((float) $product->stock_quantity > $reorderPoint) {
                continue;
            }

            $this->notifyOncePerDay(
                $recipients,
                'reorder_point',
                (int) $product->id,
                new ReorderPointStockNotification(
                    product: $product,
                    avgDailyConsumption: number_format($avgDaily, 4, '.', ''),
                    reorderPoint: number_format($reorderPoint, 4, '.', ''),
                ),
            );
        }
    }

    /**
     * Average daily appointment consumption over the last CONSUMPTION_WINDOW_DAYS ending on $day.
     *
     * @return array<int, float> product_id => avg daily qty
     */
    public function averageDailyConsumptionByProduct(int $clinicId, CarbonImmutable $day): array
    {
        $to = $day->endOfDay();
        $from = $day->subDays(self::CONSUMPTION_WINDOW_DAYS - 1)->startOfDay();
        $windowDays = self::CONSUMPTION_WINDOW_DAYS;

        $totals = StockMovement::query()
            ->where('clinic_id', $clinicId)
            ->where('type', StockMovementType::Out->value)
            ->where('reason', 'appointment_complete')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('product_id, COALESCE(SUM(quantity), 0) as total_qty')
            ->groupBy('product_id')
            ->pluck('total_qty', 'product_id');

        $averages = [];
        foreach ($totals as $productId => $totalQty) {
            $avg = (float) $totalQty / $windowDays;
            if ($avg > 0) {
                $averages[(int) $productId] = $avg;
            }
        }

        return $averages;
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsForClinic(int $clinicId): Collection
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('clinic_id', $clinicId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can('products.view'))
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function notifyOncePerDay(
        Collection $recipients,
        string $type,
        int $productId,
        object $notification,
    ): void {
        $filtered = $recipients->filter(
            fn (User $user): bool => ! $this->hasUnreadToday($user, $type, $productId)
        );

        if ($filtered->isEmpty()) {
            return;
        }

        Notification::send($filtered, $notification);
    }

    private function hasUnreadToday(User $user, string $type, int $productId): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->whereDate('created_at', now()->toDateString())
            ->where('data->type', $type)
            ->where('data->product_id', $productId)
            ->exists();
    }
}
