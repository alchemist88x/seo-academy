<?php

namespace App\Livewire\Admin\Commerce;

use App\Domain\Commerce\Models\CommerceCouponRedemption;
use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Models\CommerceTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Commerce & Revenue Reports')]
class CommerceReportsManager extends Component
{
    #[Url(as: 'period')]
    public string $period = 'all'; // 30days, this_month, all

    public function sanitizeForCsv(mixed $value): string
    {
        $str = (string) $value;
        // Formula injection protection: prepend apostrophe if string begins with =, +, -, @
        if (preg_match('/^[\=\+\-\@]/', $str)) {
            $str = "'".$str;
        }

        return str_replace(['"', "\r", "\n"], ['""', ' ', ' '], $str);
    }

    public function exportCsv(): mixed
    {
        $query = CommerceOrder::with(['user', 'coupon'])->latest();
        if ($this->period === '30days') {
            $query->where('created_at', '>=', now()->subDays(30));
        } elseif ($this->period === 'this_month') {
            $query->where('created_at', '>=', now()->startOfMonth());
        }

        $orders = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="commerce-orders-report-'.date('Ymd-His').'.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($orders) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Order Number',
                'Date',
                'Student Name',
                'Student Email',
                'Item Type',
                'Item Title',
                'Currency',
                'Base Price',
                'Promotional Discount',
                'Coupon Code',
                'Coupon Discount',
                'Total Amount',
                'Paid Amount',
                'Outstanding Balance',
                'Payment Status',
                'Payment Method',
            ]);

            foreach ($orders as $order) {
                fputcsv($file, [
                    $this->sanitizeForCsv($order->order_number),
                    $this->sanitizeForCsv($order->created_at->toDateTimeString()),
                    $this->sanitizeForCsv($order->user->name ?? 'User #'.$order->user_id),
                    $this->sanitizeForCsv($order->user->email ?? ''),
                    $this->sanitizeForCsv($order->orderable_type),
                    $this->sanitizeForCsv($order->target_title),
                    $this->sanitizeForCsv($order->currency),
                    $order->base_amount,
                    $order->discount_amount,
                    $this->sanitizeForCsv($order->coupon_code ?? ''),
                    $order->coupon_discount_amount,
                    $order->total_amount,
                    $order->paid_amount,
                    $order->outstanding_balance,
                    $this->sanitizeForCsv($order->payment_status),
                    $this->sanitizeForCsv($order->payment_method ?? ''),
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function render(): View
    {
        $baseQuery = CommerceTransaction::where('status', 'successful');
        $orderQuery = CommerceOrder::query();

        if ($this->period === '30days') {
            $baseQuery->where('paid_at', '>=', now()->subDays(30));
            $orderQuery->where('created_at', '>=', now()->subDays(30));
        } elseif ($this->period === 'this_month') {
            $baseQuery->where('paid_at', '>=', now()->startOfMonth());
            $orderQuery->where('created_at', '>=', now()->startOfMonth());
        }

        // Verified revenue collected (excluding complimentary, waivers, and net of refunds)
        $totalCollected = (float) $baseQuery->clone()
            ->whereIn('payment_gateway', ['razorpay', 'cash', 'bank_transfer', 'manual', 'manual_offline'])
            ->where('transaction_type', 'payment')
            ->sum('amount');

        $totalRefunded = (float) $baseQuery->clone()
            ->where('transaction_type', 'refund')
            ->sum('amount');

        $netRevenue = max(0.00, round($totalCollected - $totalRefunded, 2));

        // Outstanding balance
        $totalOutstanding = (float) $orderQuery->clone()
            ->whereNotIn('payment_status', ['complimentary', 'refunded'])
            ->sum('outstanding_balance');

        // Order count breakdown
        $totalOrdersCount = $orderQuery->count();
        $paidOrdersCount = $orderQuery->clone()->where('payment_status', 'paid')->count();
        $partialOrdersCount = $orderQuery->clone()->where('payment_status', 'partially_paid')->count();
        $complimentaryOrdersCount = $orderQuery->clone()->where('payment_status', 'complimentary')->count();

        // Coupon discounts given
        $couponRedemptionsCount = CommerceCouponRedemption::count();
        $totalCouponSavings = (float) CommerceCouponRedemption::sum('discount_amount');

        $recentTransactions = CommerceTransaction::with(['user', 'order'])
            ->where('status', 'successful')
            ->latest()
            ->take(8)
            ->get();

        return view('livewire.admin.commerce.commerce-reports-manager', [
            'netRevenue' => $netRevenue,
            'totalOutstanding' => $totalOutstanding,
            'totalOrdersCount' => $totalOrdersCount,
            'paidOrdersCount' => $paidOrdersCount,
            'partialOrdersCount' => $partialOrdersCount,
            'complimentaryOrdersCount' => $complimentaryOrdersCount,
            'couponRedemptionsCount' => $couponRedemptionsCount,
            'totalCouponSavings' => $totalCouponSavings,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
