<?php

namespace App\Http\Controllers\Commerce;

use App\Domain\Commerce\Models\CommerceOrder;
use App\Domain\Commerce\Services\OrderAndEnrollmentService;
use App\Domain\Commerce\Services\RazorpayPaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayWebhookController extends Controller
{
    public function handle(
        Request $request,
        RazorpayPaymentService $razorpayService,
        OrderAndEnrollmentService $orderService
    ): JsonResponse {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $razorpayService->verifyWebhookSignature($rawPayload, $signature)) {
            Log::warning('Razorpay Webhook: Invalid signature received.');

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $payload = json_decode($rawPayload, true);
        $event = $payload['event'] ?? '';

        Log::info("Razorpay Webhook received event: {$event}");

        if (in_array($event, ['payment.captured', 'order.paid'], true)) {
            $paymentEntity = $payload['payload']['payment']['entity'] ?? [];
            $orderId = $paymentEntity['notes']['order_id'] ?? null;
            $orderNumber = $paymentEntity['notes']['order_number'] ?? null;

            $order = null;
            if ($orderId) {
                $order = CommerceOrder::find($orderId);
            } elseif ($orderNumber) {
                $order = CommerceOrder::where('order_number', $orderNumber)->first();
            }

            if ($order) {
                $amountPaid = isset($paymentEntity['amount']) ? ((float) $paymentEntity['amount'] / 100) : (float) $order->total_amount;
                $paymentId = $paymentEntity['id'] ?? ('pay_'.uniqid());

                // Check idempotency: if transaction with this payment_id already recorded
                $alreadyProcessed = $order->transactions()
                    ->where('gateway_payment_id', $paymentId)
                    ->exists();

                if (! $alreadyProcessed) {
                    $orderService->recordPayment(
                        order: $order,
                        amount: $amountPaid,
                        paymentGateway: 'razorpay',
                        gatewayData: [
                            'gateway_order_id' => $paymentEntity['order_id'] ?? null,
                            'gateway_payment_id' => $paymentId,
                            'gateway_signature' => $signature,
                            'reference_number' => $paymentId,
                            'notes' => "Webhook processed for event {$event}",
                        ]
                    );
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
