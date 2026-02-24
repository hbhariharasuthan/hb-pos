<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    /**
     * Validate credit limit for a customer before creating a sale
     *
     * @param int|null $customerId
     * @param float $saleTotal
     * @param string $paymentMethod
     * @return void
     * @throws ValidationException
     */
    public function validateCreditLimit(?int $customerId, float $saleTotal, string $paymentMethod): void
    {
        // Skip validation if payment method is not credit
        if ($paymentMethod !== 'credit' || !$customerId) {
            return;
        }

        DB::transaction(function () use ($customerId, $saleTotal) {
            // Lock customer record to prevent race condition
            $customer = Customer::lockForUpdate()->find($customerId);

            if (!$customer) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Customer not found.']
                ]);
            }

            if (!$customer->hasSufficientCredit($saleTotal)) {
                throw ValidationException::withMessages([
                    'payment_method' => [
                        "Insufficient credit limit. Available balance: ₹" . number_format($customer->getAvailableCreditAttribute(), 2)
                    ]
                ]);
            }
        });
    }

    /**
     * Process credit sale and update customer balance
     *
     * @param Sale $sale
     * @return void
     */
    public function processCreditSale(Sale $sale): void
    {
        if ($sale->payment_method === 'credit' && $sale->customer_id) {
            DB::transaction(function () use ($sale) {
                $customer = Customer::lockForUpdate()->find($sale->customer_id);
                if ($customer) {
                    $customer->increment('balance', $sale->total);
                }
            });
        }
    }
}