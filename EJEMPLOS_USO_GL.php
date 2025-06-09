<?php
/**
 * EJEMPLO DE USO DEL MÓDULO GL
 * 
 * Este archivo muestra cómo usar correctamente el módulo GL
 */

use Condoedge\Finance\Services\GlTransactionService;
use Condoedge\Finance\Models\GlTransactionHeader;

// Instanciar el servicio
$glService = app(GlTransactionService::class);

// EJEMPLO 1: Crear una transacción manual simple
try {
    $transaction = $glService->createTransaction(
        // Header data
        [
            'fiscal_date' => '2025-06-05',
            'gl_transaction_type' => GlTransactionHeader::TYPE_MANUAL_GL,
            'transaction_description' => 'Registro de venta en efectivo',
            'customer_id' => 123, // opcional
        ],
        // Lines data
        [
            [
                'account_id' => '01-100-1000', // Caja
                'line_description' => 'Efectivo recibido por venta',
                'debit_amount' => 1000.00,
                'credit_amount' => 0,
            ],
            [
                'account_id' => '02-400-4000', // Ingresos por ventas
                'line_description' => 'Venta de productos',
                'debit_amount' => 0,
                'credit_amount' => 1000.00,
            ],
        ]
    );
    
    echo "Transacción creada: " . $transaction->gl_transaction_id . "\n";
    echo "Balanceada: " . ($transaction->is_balanced ? 'Sí' : 'No') . "\n";
    
    // Postear la transacción para hacerla definitiva
    $glService->postTransaction($transaction->gl_transaction_id);
    echo "Transacción posteada exitosamente\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// EJEMPLO 2: Obtener balance de cuenta
try {
    $balance = $glService->getAccountBalance(
        '01-100-1000', // Account ID
        \Carbon\Carbon::parse('2025-01-01'), // Start date
        \Carbon\Carbon::parse('2025-06-05'), // End date
        true // Posted only
    );
    
    echo "Balance de cuenta 01-100-1000: " . $balance . "\n";
    
} catch (\Exception $e) {
    echo "Error obteniendo balance: " . $e->getMessage() . "\n";
}

// EJEMPLO 3: Generar trial balance
try {
    $trialBalance = $glService->getTrialBalance(
        \Carbon\Carbon::parse('2025-01-01'),
        \Carbon\Carbon::parse('2025-06-05'),
        true
    );
    
    echo "Trial Balance:\n";
    foreach ($trialBalance as $account) {
        echo sprintf(
            "%s - %s: %s\n",
            $account['account_id'],
            $account['account_description'],
            $account['balance']
        );
    }
    
} catch (\Exception $e) {
    echo "Error generando trial balance: " . $e->getMessage() . "\n";
}

// EJEMPLO 4: Reversar una transacción posteada
try {
    $reversalTransaction = $glService->reverseTransaction(
        '2025-01-000001', // Transaction ID to reverse
        'Corrección de error en registro'
    );
    
    echo "Transacción reversada: " . $reversalTransaction->gl_transaction_id . "\n";
    
} catch (\Exception $e) {
    echo "Error reversando transacción: " . $e->getMessage() . "\n";
}

// EJEMPLO 5: Uso vía API
/*
POST /api/gl/transactions
{
    "fiscal_date": "2025-06-05",
    "gl_transaction_type": 1,
    "transaction_description": "Pago de nómina",
    "lines": [
        {
            "account_id": "05-600-6100",
            "line_description": "Sueldos y salarios",
            "debit_amount": 50000,
            "credit_amount": 0
        },
        {
            "account_id": "01-100-1000",
            "line_description": "Pago en efectivo",
            "debit_amount": 0,
            "credit_amount": 50000
        }
    ]
}

POST /api/gl/transactions/2025-01-000002/post

GET /api/gl/trial-balance?start_date=2025-01-01&end_date=2025-06-05&posted_only=true
*/
