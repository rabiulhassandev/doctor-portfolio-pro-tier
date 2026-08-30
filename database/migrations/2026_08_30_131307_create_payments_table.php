<?php

use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One attempt to pay for an appointment.
 *
 * A row per *attempt*, not per appointment: a patient whose card is declined
 * and who then pays with bKash leaves two rows, and both are worth keeping.
 * The appointment carries a denormalised `payment_status` so the admin list
 * does not have to work out which attempt was the real one.
 *
 * Gateway-agnostic by design — nothing below names SSLCommerz. See
 * App\Contracts\PaymentGateway.
 *
 * @see Payment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            /*
             | restrictOnDelete, not cascade.
             |
             | Deleting an appointment that has money attached to it should fail
             | loudly rather than quietly destroy the only record that a patient
             | paid. If a clinic genuinely needs the appointment gone, the
             | payment has to be dealt with first — deliberately.
             */
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();

            // The config key of the gateway used: 'sslcommerz', 'cash'.
            $table->string('gateway', 30);

            /*
             | Our transaction id, sent to the gateway and quoted back by it.
             | e.g. "APT-000123-8F2K9Q". It carries randomness so it cannot be
             | guessed or enumerated, and it is what the callback looks the
             | payment up by.
             */
            $table->string('reference', 64)->unique();

            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('BDT');

            // App\Enums\PaymentStatus.
            $table->string('status', 20)->default('pending');

            // The gateway's own id for the transaction, for reconciliation.
            $table->string('gateway_transaction_id')->nullable();

            // A session token some gateways issue when a payment is initiated.
            $table->string('gateway_session_key')->nullable();

            /*
             | The gateway's *validated* response, verbatim.
             |
             | Only ever written after the response has been confirmed with the
             | gateway's own API — never the raw inbound POST, which is
             | attacker-controlled. This is the record that settles a dispute
             | six months later.
             */
            $table->json('payload')->nullable();

            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();

            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
