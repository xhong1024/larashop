<?php

namespace App\Jobs;

use App\Models\CrowdfundingProduct;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// ShouldQueue 代表此任务需要异步执行
class RefundCrowdfundingOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $crowdfunding;

    /**
     * Create a new job instance.
     *
     * @param CrowdfundingProduct $crowdfunding
     */
    public function __construct(CrowdfundingProduct $crowdfunding)
    {
        $this->crowdfunding = $crowdfunding;
    }

    /**
     * 对于这个定时任务来说，众筹失败退款部分涉及到了与外部 API 的交互，是属于长耗时的操作，因此把这部分的逻辑抽取成一个异步任务
     *
     * @return void
     */
    public function handle()
    {
        // 如果众筹的状态不是失败则不执行退款，原则上不会发生，这里只是增加健壮性
        if ($this->crowdfunding->status !== CrowdfundingProduct::STATUS_FAIL) {
            return;
        }
        // 将定时任务中的众筹失败退款代码移到这里
        $orderService = app(OrderService::class);
        Order::query()
            ->where('type', Order::TYPE_CROWDFUNDING)
            ->whereNotNull('paid_at')
            ->whereHas('items', function ($query) {
                $query->where('product_id', $this->crowdfunding->product_id);
            })
            ->get()
            ->each(function (Order $order) use ($orderService) {
                $orderService->refundOrder($order);
            });
    }
}
