<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Channels\PushChannel;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProjectedLowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly string $plannedQuantity,
        public readonly string $projectedQuantity,
        public readonly CarbonImmutable $day,
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        return ['database', PushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $date = $this->day->toDateString();

        return [
            'type' => 'projected_low_stock',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'stock_quantity' => (string) $this->product->stock_quantity,
            'min_stock' => (string) $this->product->min_stock,
            'planned_quantity' => $this->plannedQuantity,
            'projected_quantity' => $this->projectedQuantity,
            'day' => $date,
            'title' => 'Estoque projetado baixo',
            'message' => "Após os atendimentos de {$date}, o estoque de {$this->product->name} deve ficar em {$this->projectedQuantity} (mínimo {$this->product->min_stock}). Consumo previsto: {$this->plannedQuantity}.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toPush(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => $data,
        ];
    }
}
