<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateGlTransactionBalanceValidationFunction extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create function to validate GL transaction balance
        DB::unprepared("
            DROP FUNCTION IF EXISTS validate_gl_transaction_balance;
            
            CREATE FUNCTION validate_gl_transaction_balance(
                p_gl_transaction_id VARCHAR(20)
            )
            RETURNS BOOLEAN
            READS SQL DATA
            DETERMINISTIC
            SQL SECURITY DEFINER
            BEGIN
                DECLARE v_total_debits DECIMAL(15, 2) DEFAULT 0.00;
                DECLARE v_total_credits DECIMAL(15, 2) DEFAULT 0.00;
                DECLARE v_is_balanced BOOLEAN DEFAULT FALSE;
                
                -- Calculate total debits and credits
                SELECT 
                    COALESCE(SUM(debit_amount), 0.00),
                    COALESCE(SUM(credit_amount), 0.00)
                INTO v_total_debits, v_total_credits
                FROM fin_gl_transaction_lines
                WHERE gl_transaction_id = p_gl_transaction_id;
                
                -- Check if balanced (debits = credits)
                SET v_is_balanced = (v_total_debits = v_total_credits);
                
                RETURN v_is_balanced;
            END;
        ");

        // Create function to get GL transaction out of balance amount
        DB::unprepared("
            DROP FUNCTION IF EXISTS get_gl_transaction_out_of_balance_amount;
            
            CREATE FUNCTION get_gl_transaction_out_of_balance_amount(
                p_gl_transaction_id VARCHAR(20)
            )
            RETURNS DECIMAL(15, 2)
            READS SQL DATA
            DETERMINISTIC
            SQL SECURITY DEFINER
            BEGIN
                DECLARE v_total_debits DECIMAL(15, 2) DEFAULT 0.00;
                DECLARE v_total_credits DECIMAL(15, 2) DEFAULT 0.00;
                DECLARE v_out_of_balance DECIMAL(15, 2) DEFAULT 0.00;
                
                -- Calculate total debits and credits
                SELECT 
                    COALESCE(SUM(debit_amount), 0.00),
                    COALESCE(SUM(credit_amount), 0.00)
                INTO v_total_debits, v_total_credits
                FROM fin_gl_transaction_lines
                WHERE gl_transaction_id = p_gl_transaction_id;
                
                -- Calculate out of balance amount (debit - credit)
                SET v_out_of_balance = v_total_debits - v_total_credits;
                
                RETURN v_out_of_balance;
            END;
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::unprepared("DROP FUNCTION IF EXISTS validate_gl_transaction_balance;");
        DB::unprepared("DROP FUNCTION IF EXISTS get_gl_transaction_out_of_balance_amount;");
    }
}
