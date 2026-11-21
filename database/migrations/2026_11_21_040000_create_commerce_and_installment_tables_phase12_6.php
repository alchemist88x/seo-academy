<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Course Bundles
        if (! Schema::hasTable('course_bundles')) {
            Schema::create('course_bundles', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('thumbnail_path')->nullable();
                $table->decimal('price', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('USD');
                $table->decimal('original_total_price', 10, 2)->nullable();
                $table->decimal('discount_price', 10, 2)->nullable();
                $table->decimal('discount_percentage', 5, 2)->nullable();
                $table->timestamp('discount_starts_at')->nullable();
                $table->timestamp('discount_expires_at')->nullable();
                $table->json('regional_prices')->nullable();
                $table->string('status')->default('published'); // draft, published, archived
                $table->boolean('is_coupon_eligible')->default(true);
                $table->unsignedBigInteger('installment_plan_id')->nullable();
                $table->string('ownership_policy')->default('full_bundle_price'); // full_bundle_price, exclude_owned_courses
                $table->timestamps();

                $table->index(['academy_id', 'slug']);
                $table->index(['academy_id', 'status']);
            });
        }

        // 2. Bundle Courses Pivot
        if (! Schema::hasTable('course_bundle_courses')) {
            Schema::create('course_bundle_courses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bundle_id')->constrained('course_bundles')->cascadeOnDelete();
                $table->foreignUuid('course_id')->constrained('courses')->cascadeOnDelete();
                $table->integer('display_order')->default(0);
                $table->timestamps();

                $table->unique(['bundle_id', 'course_id']);
            });
        }

        // 3. Discount Coupons
        if (! Schema::hasTable('commerce_coupons')) {
            Schema::create('commerce_coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('code');
                $table->text('description')->nullable();
                $table->string('discount_type')->default('percentage'); // percentage, fixed
                $table->decimal('discount_value', 10, 2)->default(0.00);
                $table->decimal('max_discount_amount', 10, 2)->nullable();
                $table->decimal('min_purchase_amount', 10, 2)->nullable();
                $table->string('currency', 3)->default('USD');
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->integer('global_usage_limit')->nullable();
                $table->integer('per_student_usage_limit')->default(1);
                $table->integer('usage_count')->default(0);
                $table->boolean('first_purchase_only')->default(false);
                $table->json('eligible_course_ids')->nullable();
                $table->json('eligible_bundle_ids')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['academy_id', 'code']);
                $table->index(['academy_id', 'is_active']);
            });
        }

        // 4. Installment Plans
        if (! Schema::hasTable('commerce_installment_plans')) {
            Schema::create('commerce_installment_plans', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->string('title');
                $table->string('plan_type')->default('milestone_completion'); // milestone_completion, fixed_dates, custom
                $table->string('initial_deposit_type')->default('percentage'); // percentage, fixed
                $table->decimal('initial_deposit_value', 10, 2)->default(50.00);
                $table->integer('number_of_installments')->default(2);
                $table->decimal('completion_milestone_percentage', 5, 2)->default(60.00); // 50, 60, 70, 80
                $table->string('bundle_completion_basis')->default('average'); // average, all_courses, primary_course
                $table->string('access_policy')->default('immediate_on_deposit'); // immediate_on_deposit, full_payment_only, restrict_when_overdue
                $table->integer('grace_period_days')->default(7);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['academy_id', 'is_active']);
            });
        }

        // 5. Orders
        if (! Schema::hasTable('commerce_orders')) {
            Schema::create('commerce_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('order_number')->unique();
                $table->string('orderable_type'); // course, bundle
                $table->string('orderable_id');
                $table->string('currency', 3)->default('USD');
                $table->decimal('base_amount', 10, 2)->default(0.00);
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->unsignedBigInteger('coupon_id')->nullable();
                $table->string('coupon_code')->nullable();
                $table->decimal('coupon_discount_amount', 10, 2)->default(0.00);
                $table->decimal('owned_courses_discount', 10, 2)->default(0.00);
                $table->decimal('total_amount', 10, 2)->default(0.00);
                $table->decimal('paid_amount', 10, 2)->default(0.00);
                $table->decimal('outstanding_balance', 10, 2)->default(0.00);
                $table->string('payment_status')->default('unpaid'); // unpaid, partially_paid, paid, complimentary, refunded
                $table->string('payment_method')->nullable(); // razorpay, manual_offline, cash, bank_transfer, complimentary, scholarship, sponsored
                $table->unsignedBigInteger('installment_plan_id')->nullable();
                $table->boolean('is_installment')->default(false);
                $table->string('ownership_policy_applied')->nullable();
                $table->json('price_snapshot')->nullable();
                $table->json('terms_snapshot')->nullable();
                $table->string('assigned_by_user_id')->nullable();
                $table->string('assignment_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['academy_id', 'user_id']);
                $table->index(['academy_id', 'payment_status']);
                $table->index(['orderable_type', 'orderable_id']);
            });
        }

        // 6. Order Installments
        if (! Schema::hasTable('commerce_order_installments')) {
            Schema::create('commerce_order_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->integer('installment_number')->default(1);
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->decimal('paid_amount', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('USD');
                $table->string('due_condition')->default('initial_deposit'); // initial_deposit, completion_milestone, calendar_date
                $table->timestamp('due_date')->nullable();
                $table->decimal('milestone_percentage', 5, 2)->nullable();
                $table->timestamp('milestone_triggered_at')->nullable();
                $table->string('status')->default('upcoming'); // upcoming, due, partially_paid, paid, overdue, waived, cancelled
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
                $table->index(['academy_id', 'status']);
            });
        }

        // 7. Transactions
        if (! Schema::hasTable('commerce_transactions')) {
            Schema::create('commerce_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->unsignedBigInteger('installment_id')->nullable();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('transaction_type')->default('payment'); // payment, refund, waiver
                $table->decimal('amount', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('USD');
                $table->string('payment_gateway')->default('manual'); // razorpay, manual, cash, bank_transfer, waiver, scholarship
                $table->string('gateway_order_id')->nullable();
                $table->string('gateway_payment_id')->nullable();
                $table->string('gateway_signature')->nullable();
                $table->json('gateway_response')->nullable();
                $table->string('status')->default('successful'); // successful, failed, pending, refunded
                $table->string('reference_number')->nullable();
                $table->text('notes')->nullable();
                $table->string('recorded_by_user_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'status']);
                $table->index(['academy_id', 'payment_gateway']);
            });
        }

        // 8. Coupon Redemptions
        if (! Schema::hasTable('commerce_coupon_redemptions')) {
            Schema::create('commerce_coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('academy_id')->constrained('academies')->cascadeOnDelete();
                $table->foreignId('coupon_id')->constrained('commerce_coupons')->cascadeOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('order_id')->constrained('commerce_orders')->cascadeOnDelete();
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->string('currency', 3)->default('USD');
                $table->timestamp('redeemed_at')->useCurrent();
                $table->timestamps();

                $table->index(['coupon_id', 'user_id']);
            });
        }

        // 9. Additive Columns on Courses
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'discount_price')) {
                $table->decimal('discount_price', 10, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('courses', 'discount_percentage')) {
                $table->decimal('discount_percentage', 5, 2)->nullable()->after('discount_price');
            }
            if (! Schema::hasColumn('courses', 'discount_starts_at')) {
                $table->timestamp('discount_starts_at')->nullable()->after('discount_percentage');
            }
            if (! Schema::hasColumn('courses', 'discount_expires_at')) {
                $table->timestamp('discount_expires_at')->nullable()->after('discount_starts_at');
            }
            if (! Schema::hasColumn('courses', 'regional_prices')) {
                $table->json('regional_prices')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('courses', 'is_coupon_eligible')) {
                $table->boolean('is_coupon_eligible')->default(true)->after('is_free');
            }
            if (! Schema::hasColumn('courses', 'installment_plan_id')) {
                $table->unsignedBigInteger('installment_plan_id')->nullable()->after('is_coupon_eligible');
            }
        });

        // 10. Additive Columns on Enrollments
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('assigned_by');
            }
            if (! Schema::hasColumn('enrollments', 'bundle_id')) {
                $table->unsignedBigInteger('bundle_id')->nullable()->after('order_id');
            }
            if (! Schema::hasColumn('enrollments', 'payment_status')) {
                $table->string('payment_status')->default('paid')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
            if (Schema::hasColumn('enrollments', 'bundle_id')) {
                $table->dropColumn('bundle_id');
            }
            if (Schema::hasColumn('enrollments', 'order_id')) {
                $table->dropColumn('order_id');
            }
        });

        Schema::table('courses', function (Blueprint $table) {
            $columnsToDrop = [
                'installment_plan_id',
                'is_coupon_eligible',
                'regional_prices',
                'discount_expires_at',
                'discount_starts_at',
                'discount_percentage',
                'discount_price',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('commerce_coupon_redemptions');
        Schema::dropIfExists('commerce_transactions');
        Schema::dropIfExists('commerce_order_installments');
        Schema::dropIfExists('commerce_orders');
        Schema::dropIfExists('commerce_installment_plans');
        Schema::dropIfExists('commerce_coupons');
        Schema::dropIfExists('course_bundle_courses');
        Schema::dropIfExists('course_bundles');
    }
};
