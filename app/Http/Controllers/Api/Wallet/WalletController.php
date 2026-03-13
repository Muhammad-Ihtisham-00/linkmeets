<?php

namespace App\Http\Controllers\api\Wallet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Traits\ApiResponse;
use App\Models\User;

class WalletController extends Controller
{
    use ApiResponse;

    /* ═══════════════════════════════════════════════════════════
       1. GET WALLET
       GET /api/wallet
    ═══════════════════════════════════════════════════════════ */
    public function index(): JsonResponse
    {
        try {
            $wallet = Wallet::firstOrCreate(
                ['user_id' => Auth::id()],
                [
                    'available_balance' => 0.00,
                    'pending_balance' => 0.00,
                    'total_earning' => 0.00,
                    'total_withdrawn' => 0.00,
                    'currency' => 'USD',
                ]
            );

            return $this->successResponse('Wallet fetched successfully.', $wallet);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       2. TRANSACTIONS LIST
       GET /api/wallet/transactions?type=pending|completed
    ═══════════════════════════════════════════════════════════ */
    public function transactions(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => ['nullable', Rule::in(['add_money', 'send_money', 'withdraw', 'earning', 'refund', 'fee'])],
                'status' => ['nullable', Rule::in(['pending', 'completed', 'failed', 'refunded', 'cancelled'])],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            $wallet = Wallet::where('user_id', Auth::id())->first();

            if (!$wallet)
                return $this->errorResponse('Wallet not found.', null, 404);

            $transactions = Transaction::where('wallet_id', $wallet->id)
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                ->when($request->status, fn($q) => $q->where('status', $request->status))
                ->with([
                    'receiver:id,first_name,last_name,profile_picture',
                    'appointment:id,appointment_date',
                    'paymentMethod:id,type,card_last_four,card_brand',
                ])
                ->orderByDesc('created_at')
                ->paginate(15);

            return $this->successResponse('Transactions fetched successfully.', $transactions);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       3. ADD MONEY (via Stripe/PayPal)
       POST /api/wallet/add-money
    ═══════════════════════════════════════════════════════════ */
    public function addMoney(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
                'payment_method_id' => ['required', 'exists:payment_methods,id'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            // Payment method user ki honi chahiye
            $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
                ->where('user_id', Auth::id())
                ->where('is_connected', true)
                ->first();

            if (!$paymentMethod)
                return $this->errorResponse('Payment method not found or not connected.', null, 404);

            $wallet = Wallet::firstOrCreate(['user_id' => Auth::id()]);

            if (!$wallet->isUsable())
                return $this->errorResponse('Your wallet is currently frozen. Please contact support.', null, 403);

            DB::beginTransaction();

            // Platform fee — 2.5%
            $fee = round($request->amount * 0.025, 2);
            $netAmount = $request->amount - $fee;

            // ── HERE: Stripe/PayPal API call hogi ──────────────────
            // $gatewayResponse = StripeService::charge($paymentMethod, $request->amount);
            // Abhi simulate kar rahe hain
            $gatewayTransactionId = 'gtx_' . Str::random(20);
            // ────────────────────────────────────────────────────────

            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => Auth::id(),
                'type' => 'add_money',
                'amount' => $request->amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'currency' => $wallet->currency,
                'description' => 'Added money to wallet',
                'reference_number' => 'TXN-' . strtoupper(Str::random(10)),
                'payment_method_id' => $paymentMethod->id,
                'gateway_transaction_id' => $gatewayTransactionId,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Wallet balance update
            $wallet->increment('available_balance', $netAmount);

            DB::commit();

            return $this->successResponse(
                'Money added successfully.',
                [
                    'transaction' => $transaction->load('paymentMethod'),
                    'available_balance' => $wallet->fresh()->available_balance,
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       4. SEND MONEY (User to User)
       POST /api/wallet/send-money
    ═══════════════════════════════════════════════════════════ */
    public function sendMoney(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'receiver_id' => ['required', 'exists:users,id', 'different:' . Auth::id()],
                'amount' => ['required', 'numeric', 'min:1'],
                'description' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            $senderWallet = Wallet::where('user_id', Auth::id())->first();

            if (!$senderWallet)
                return $this->errorResponse('Wallet not found.', null, 404);

            if (!$senderWallet->isUsable())
                return $this->errorResponse('Your wallet is currently frozen.', null, 403);

            if (!$senderWallet->hasSufficientBalance($request->amount))
                return $this->errorResponse('Insufficient balance.', null, 422);

            $receiver = User::find($request->receiver_id);

            $receiverWallet = Wallet::firstOrCreate(
                ['user_id' => $request->receiver_id],
                ['available_balance' => 0, 'pending_balance' => 0, 'total_earning' => 0, 'total_withdrawn' => 0, 'currency' => $senderWallet->currency]
            );

            DB::beginTransaction();

            // Platform fee — 1%
            $fee = round($request->amount * 0.01, 2);
            $netAmount = $request->amount - $fee;

            // Sender transaction (debit)
            $senderTransaction = Transaction::create([
                'wallet_id' => $senderWallet->id,
                'user_id' => Auth::id(),
                'receiver_id' => $request->receiver_id,
                'type' => 'send_money',
                'amount' => $request->amount,
                'fee' => $fee,
                'net_amount' => $request->amount,
                'currency' => $senderWallet->currency,
                'description' => $request->description ?? "Sent to {$receiver->first_name} {$receiver->last_name}",
                'reference_number' => 'TXN-' . strtoupper(Str::random(10)),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Receiver transaction (credit)
            Transaction::create([
                'wallet_id' => $receiverWallet->id,
                'user_id' => $request->receiver_id,
                'receiver_id' => null,
                'type' => 'earning',
                'amount' => $netAmount,
                'fee' => 0,
                'net_amount' => $netAmount,
                'currency' => $receiverWallet->currency,
                'description' => "Received from " . Auth::user()->first_name . ' ' . Auth::user()->last_name,
                'reference_number' => 'TXN-' . strtoupper(Str::random(10)),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Balances update
            $senderWallet->decrement('available_balance', $request->amount);
            $receiverWallet->increment('available_balance', $netAmount);
            $receiverWallet->increment('total_earning', $netAmount);

            DB::commit();

            return $this->successResponse(
                'Money sent successfully.',
                [
                    'transaction' => $senderTransaction->load('receiver'),
                    'available_balance' => $senderWallet->fresh()->available_balance,
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       5. WITHDRAW
       POST /api/wallet/withdraw
    ═══════════════════════════════════════════════════════════ */
    public function withdraw(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => ['required', 'numeric', 'min:10'],
                'payment_method_id' => ['required', 'exists:payment_methods,id'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            $paymentMethod = PaymentMethod::where('id', $request->payment_method_id)
                ->where('user_id', Auth::id())
                ->where('is_connected', true)
                ->first();

            if (!$paymentMethod)
                return $this->errorResponse('Payment method not found or not connected.', null, 404);

            $wallet = Wallet::where('user_id', Auth::id())->first();

            if (!$wallet)
                return $this->errorResponse('Wallet not found.', null, 404);

            if (!$wallet->isUsable())
                return $this->errorResponse('Your wallet is currently frozen.', null, 403);

            if (!$wallet->hasSufficientBalance($request->amount))
                return $this->errorResponse('Insufficient balance.', null, 422);

            DB::beginTransaction();

            // Withdraw fee — 1.5%
            $fee = round($request->amount * 0.015, 2);
            $netAmount = $request->amount - $fee;

            // ── HERE: Stripe/PayPal payout API call hogi ───────────
            // $gatewayResponse = StripeService::payout($paymentMethod, $netAmount);
            $gatewayTransactionId = 'gtx_' . Str::random(20);
            // ────────────────────────────────────────────────────────

            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => Auth::id(),
                'type' => 'withdraw',
                'amount' => $request->amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'currency' => $wallet->currency,
                'description' => 'Withdrawal to ' . $paymentMethod->display_name,
                'reference_number' => 'TXN-' . strtoupper(Str::random(10)),
                'payment_method_id' => $paymentMethod->id,
                'gateway_transaction_id' => $gatewayTransactionId,
                'status' => 'pending', // Payout processing mein time lagta hai
            ]);

            // Balance deduct — pending mein move karo
            $wallet->decrement('available_balance', $request->amount);
            $wallet->increment('pending_balance', $request->amount);

            DB::commit();

            return $this->successResponse(
                'Withdrawal request submitted. It will be processed within 1-3 business days.',
                [
                    'transaction' => $transaction->load('paymentMethod'),
                    'available_balance' => $wallet->fresh()->available_balance,
                    'pending_balance' => $wallet->fresh()->pending_balance,
                ],
                201
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       6. PAYMENT METHODS LIST
       GET /api/wallet/payment-methods
    ═══════════════════════════════════════════════════════════ */
    public function paymentMethods(): JsonResponse
    {
        try {
            $methods = PaymentMethod::where('user_id', Auth::id())
                ->orderByDesc('is_default')
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse('Payment methods fetched successfully.', $methods);

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       7. ADD PAYMENT METHOD
       POST /api/wallet/payment-methods
    ═══════════════════════════════════════════════════════════ */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => ['required', Rule::in(['paypal', 'google_pay', 'apple_pay', 'card'])],
                'gateway_token' => ['required', 'string'],  // Stripe token / PayPal token
                // Card specific
                'card_holder_name' => ['required_if:type,card', 'nullable', 'string'],
            ]);

            if ($validator->fails())
                return $this->errorResponse('Validation failed.', $validator->errors(), 422);

            // Duplicate check
            $exists = PaymentMethod::where('user_id', Auth::id())
                ->where('type', $request->type)
                ->where('gateway_token', $request->gateway_token)
                ->exists();

            if ($exists)
                return $this->errorResponse('This payment method is already added.', null, 422);

            DB::beginTransaction();

            // ── HERE: Gateway se card details fetch honge ──────────
            // $gatewayDetails = StripeService::getPaymentMethod($request->gateway_token);
            // Abhi simulate kar rahe hain
            $methodData = [
                'user_id' => Auth::id(),
                'type' => $request->type,
                'gateway_token' => $request->gateway_token,
                'is_connected' => true,
                'is_default' => !PaymentMethod::where('user_id', Auth::id())->exists(),
            ];

            if ($request->type === 'card') {
                $methodData['card_holder_name'] = $request->card_holder_name;
                // Gateway se milega: $methodData['card_brand'] = $gatewayDetails->brand;
                // $methodData['card_last_four']  = $gatewayDetails->last4;
            }

            if ($request->type === 'paypal') {
                // $methodData['paypal_email'] = $gatewayDetails->email;
            }

            $method = PaymentMethod::create($methodData);

            DB::commit();

            return $this->successResponse('Payment method added successfully.', $method, 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       8. SET DEFAULT PAYMENT METHOD
       PUT /api/wallet/payment-methods/{id}/default
    ═══════════════════════════════════════════════════════════ */
    public function setDefaultPaymentMethod(int $id): JsonResponse
    {
        try {
            $method = PaymentMethod::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$method)
                return $this->errorResponse('Payment method not found.', null, 404);

            DB::beginTransaction();

            // Pehle sab false karo
            PaymentMethod::where('user_id', Auth::id())->update(['is_default' => false]);

            // Phir yeh default banao
            $method->update(['is_default' => true]);

            DB::commit();

            return $this->successResponse('Default payment method updated.', $method->fresh());

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }

    /* ═══════════════════════════════════════════════════════════
       9. DELETE PAYMENT METHOD
       DELETE /api/wallet/payment-methods/{id}
    ═══════════════════════════════════════════════════════════ */
    public function deletePaymentMethod(int $id): JsonResponse
    {
        try {
            $method = PaymentMethod::where('id', $id)
                ->where('user_id', Auth::id())
                ->first();

            if (!$method)
                return $this->errorResponse('Payment method not found.', null, 404);

            if ($method->is_default)
                return $this->errorResponse('Cannot delete default payment method. Please set another method as default first.', null, 422);

            $method->delete();

            return $this->successResponse('Payment method deleted successfully.');

        } catch (\Throwable $e) {
            return $this->errorResponse('Something went wrong.', config('app.debug') ? $e->getMessage() : null, 500);
        }
    }
}
