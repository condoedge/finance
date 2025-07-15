<?php

namespace Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Mockery;
use Tests\TestCase;

class PaymentContextTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_creates_context_with_required_fields()
    {
        // Arrange
        $payable = $this->createMockPayable();

        // Act
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        // Assert
        $this->assertSame($payable, $context->payable);
        $this->assertEquals(PaymentMethodEnum::CREDIT_CARD, $context->paymentMethod);
        $this->assertEquals([], $context->paymentData);
        $this->assertNull($context->returnUrl);
        $this->assertNull($context->cancelUrl);
        $this->assertEquals([], $context->metadata);
    }

    public function test_it_creates_context_with_all_fields()
    {
        // Arrange
        $payable = $this->createMockPayable();
        $paymentData = [
            'card_number' => '4111111111111111',
            'exp_month' => '12',
            'exp_year' => '2025',
            'cvv' => '123',
        ];
        $metadata = [
            'order_ref' => 'ORD-123',
            'customer_ip' => '192.168.1.1',
        ];

        // Act
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER,
            paymentData: $paymentData,
            returnUrl: 'https://example.com/return',
            cancelUrl: 'https://example.com/cancel',
            metadata: $metadata
        );

        // Assert
        $this->assertSame($payable, $context->payable);
        $this->assertEquals(PaymentMethodEnum::BANK_TRANSFER, $context->paymentMethod);
        $this->assertEquals($paymentData, $context->paymentData);
        $this->assertEquals('https://example.com/return', $context->returnUrl);
        $this->assertEquals('https://example.com/cancel', $context->cancelUrl);
        $this->assertEquals($metadata, $context->metadata);
    }

    public function test_it_generates_provider_metadata()
    {
        // Arrange
        $payable = $this->createMockPayable(['invoice_number' => 'INV-2024-001']);

        $customMetadata = [
            'source' => 'web',
            'user_agent' => 'test-browser',
        ];

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            metadata: $customMetadata
        );

        // Act
        $providerMetadata = $context->toProviderMetadata();

        // Assert
        $this->assertIsArray($providerMetadata);

        // Should include payable info
        $this->assertEquals('test_order', $providerMetadata['payable_type']);
        $this->assertEquals(123, $providerMetadata['payable_id']);
        $this->assertEquals(1, $providerMetadata['team_id']);
        $this->assertEquals(PaymentMethodEnum::CREDIT_CARD->value, $providerMetadata['payment_method_id']);

        // Should include payable metadata
        $this->assertArrayHasKey('invoice_number', $providerMetadata);

        // Should include custom metadata
        $this->assertEquals('web', $providerMetadata['source']);
        $this->assertEquals('test-browser', $providerMetadata['user_agent']);
    }

    public function test_metadata_merging_prioritizes_custom_metadata()
    {
        // Arrange
        $payable = $this->createMockPayable([
                'duplicate_key' => 'from_payable',
                'payable_only' => 'payable_value',
            ]);

        $customMetadata = [
            'duplicate_key' => 'from_custom',
            'custom_only' => 'custom_value',
        ];

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::INTERAC,
            metadata: $customMetadata
        );

        // Act
        $providerMetadata = $context->toProviderMetadata();

        // Assert
        // Custom metadata should override payable metadata for duplicate keys
        $this->assertEquals('from_custom', $providerMetadata['duplicate_key']);

        // Both unique keys should be present
        $this->assertEquals('payable_value', $providerMetadata['payable_only']);
        $this->assertEquals('custom_value', $providerMetadata['custom_only']);
    }

    public function test_it_handles_empty_payable_metadata()
    {
        // Arrange
        $payable = $this->createMockPayable([]);

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CHECK
        );

        // Act
        $providerMetadata = $context->toProviderMetadata();

        // Assert
        $this->assertIsArray($providerMetadata);
        $this->assertArrayHasKey('payable_type', $providerMetadata);
        $this->assertArrayHasKey('payable_id', $providerMetadata);
        $this->assertArrayHasKey('team_id', $providerMetadata);
        $this->assertArrayHasKey('payment_method_id', $providerMetadata);
        $this->assertCount(4, $providerMetadata); // Only the 4 base fields
    }

    public function test_it_preserves_data_types_in_metadata()
    {
        // Arrange
        $payable = $this->createMockPayable([
                'string_value' => 'test',
                'int_value' => 42,
                'float_value' => 123.45,
                'bool_value' => true,
                'array_value' => ['nested' => 'data'],
                'null_value' => null,
            ]);

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CASH
        );

        // Act
        $providerMetadata = $context->toProviderMetadata();

        // Assert
        $this->assertIsString($providerMetadata['string_value']);
        $this->assertIsInt($providerMetadata['int_value']);
        $this->assertIsFloat($providerMetadata['float_value']);
        $this->assertIsBool($providerMetadata['bool_value']);
        $this->assertIsArray($providerMetadata['array_value']);
        $this->assertNull($providerMetadata['null_value']);
    }

    public function test_context_is_immutable()
    {
        // Arrange
        $payable = $this->createMockPayable();
        $originalPaymentData = ['card' => '4111'];
        $originalMetadata = ['ref' => 'TEST'];

        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: $originalPaymentData,
            metadata: $originalMetadata
        );

        // Act - Try to modify the arrays
        $paymentData = $context->paymentData;
        $paymentData['new_field'] = 'new_value';

        $metadata = $context->metadata;
        $metadata['new_meta'] = 'new_meta_value';

        // Assert - Original context should be unchanged
        $this->assertEquals($originalPaymentData, $context->paymentData);
        $this->assertEquals($originalMetadata, $context->metadata);
        $this->assertArrayNotHasKey('new_field', $context->paymentData);
        $this->assertArrayNotHasKey('new_meta', $context->metadata);
    }

    // Helper methods

    private function createMockPayable(array $paymentMetadata = []): PayableInterface
    {
        $payable = Mockery::mock(PayableInterface::class);
        $payable->shouldReceive('getPayableId')->andReturn(123);
        $payable->shouldReceive('getPayableType')->andReturn('test_order');
        $payable->shouldReceive('getTeamId')->andReturn(1);
        $payable->shouldReceive('getPayableAmount')->andReturn(new SafeDecimal(100));
        $payable->shouldReceive('getPayableLines')->andReturn(collect());
        $payable->shouldReceive('getPaymentDescription')->andReturn('Test payment');
        $payable->shouldReceive('getPaymentMetadata')->andReturn($paymentMetadata);
        $payable->shouldReceive('getAddress')->andReturn(null);
        $payable->shouldReceive('getEmail')->andReturn('test@example.com');
        $payable->shouldReceive('getCustomerName')->andReturn('Test Customer');

        return $payable;
    }
}
