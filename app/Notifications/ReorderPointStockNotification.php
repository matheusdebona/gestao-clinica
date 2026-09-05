<?php

namespace App\Notifications;

use App\Models\Product;
use App\Notifications\Channels\PushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReorderPointStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Product $product,
        public readonly string $avgDailyConsumption,
        public readonly string $reorderPoint,
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
        return [
            'type' => 'reorder_point',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'stock_quantity' => (string) $this->product->stock_quantity,
            'min_stock' => (string) $this->product->min_stock,
            'lead_time_days' => (int) $this->product->lead_time_days,
            'avg_daily_consumption' => $this->avgDailyConsumption,
            'reorder_point' => $this->reorderPoint,
            'title' => 'Ponto de reposição',
            'message' => "O produto {$this->product->name} atingiu o ponto de reposição ({$this->reorderPoint}) considerando lead time de {$this->product->lead_time_days} dia(s). Disponível: {$this->product->stock_quantity}; consumo médio/dia (30d): {$this->avgDailyConsumption}.",
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
