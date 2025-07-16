<?php

namespace Condoedge\Finance\Tests\Unit\Billing;

use Condoedge\Finance\Billing\Contracts\PayableInterface;
use Condoedge\Finance\Billing\Core\PaymentActionEnum;
use Condoedge\Finance\Billing\Core\PaymentContext;
use Condoedge\Finance\Billing\Core\PaymentResult;
use Condoedge\Finance\Billing\Providers\Stripe\StripePaymentProvider;
use Condoedge\Finance\Casts\SafeDecimal;
use Condoedge\Finance\Models\PaymentMethodEnum;
use Condoedge\Utils\Models\ContactInfo\Maps\Address;
use Illuminate\Validation\ValidationException;
use Mockery;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;
use Tests\TestCase;

class StripePaymentProviderTest extends TestCase
{
    private StripePaymentProvider $provider;
    private StripeClient $mockStripeClient;
    private PaymentIntentService $mockPaymentIntentService;
    private PaymentMethodService $mockPaymentMethodService;

    public function setUp(): void
    {
        parent::setUp();

        // Set up config
        config([
            'kompo-finance.services.stripe.secret_key' => 'sk_test_mock',
            'kompo-finance.services.stripe.webhook_secret' => 'whsec_test_mock',
        ]);

        // Create mock Stripe services
        $this->mockPaymentIntentService = Mockery::mock(PaymentIntentService::class);
        $this->mockPaymentMethodService = Mockery::mock(PaymentMethodService::class);

        $this->mockStripeClient = Mockery::mock(StripeClient::class);
        $this->mockStripeClient->paymentIntents = $this->mockPaymentIntentService;
        $this->mockStripeClient->paymentMethods = $this->mockPaymentMethodService;

        // Create provider with mocked client
        $this->provider = new StripePaymentProvider();

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($this->provider);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($this->provider, $this->mockStripeClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_correct_code()
    {
        $this->assertEquals('stripe', $this->provider->getCode());
    }

    public function test_it_supports_correct_payment_methods()
    {
        $supportedMethods = $this->provider->getSupportedPaymentMethods();

        $this->assertContains(PaymentMethodEnum::CREDIT_CARD, $supportedMethods);
        $this->assertContains(PaymentMethodEnum::BANK_TRANSFER, $supportedMethods);
        $this->assertNotContains(PaymentMethodEnum::CASH, $supportedMethods);
    }

    public function test_it_processes_successful_credit_card_payment()
    {
        // Arrange
        $payable = $this->createMockPayable(150.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_card_visa',
                'complete_name' => 'John Doe',
            ]
        );

        $mockPaymentIntent = $this->createMockPaymentIntent(
            'pi_test_123',
            15000, // Amount in cents
            PaymentIntent::STATUS_SUCCEEDED
        );

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['amount'] === 15000 &&
                       $params['currency'] === 'cad' &&
                       $params['payment_method_types'] === ['card'];
            }))
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->with('pi_test_123', Mockery::any())
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertInstanceOf(PaymentResult::class, $result);
        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('pi_test_123', $result->transactionId);
        $this->assertEquals(150.00, $result->amount);
        $this->assertEquals('stripe', $result->paymentProviderCode);
    }

    public function test_it_handles_3ds_authentication_required()
    {
        // Arrange
        $payable = $this->createMockPayable(200.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_card_threeDSecureRequired',
                'complete_name' => 'Jane Doe',
            ],
            returnUrl: 'https://example.com/payment/return'
        );

        $mockPaymentIntent = $this->createMockPaymentIntent(
            'pi_3ds_test',
            20000,
            PaymentIntent::STATUS_REQUIRES_ACTION,
            [
                'type' => 'redirect_to_url',
                'redirect_to_url' => ['url' => 'https://stripe.com/3ds/authenticate'],
            ]
        );

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertTrue($result->isPending);
        $this->assertEquals('https://stripe.com/3ds/authenticate', $result->redirectUrl);
        $this->assertEquals(PaymentActionEnum::REDIRECT, $result->action);
    }

    public function test_it_handles_failed_payment()
    {
        // Arrange
        $payable = $this->createMockPayable(100.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_card_declined',
                'complete_name' => 'John Doe',
            ]
        );

        $mockPaymentIntent = $this->createMockPaymentIntent(
            'pi_failed_test',
            10000,
            PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
            null,
            [
                'message' => 'Your card was declined.',
                'code' => 'card_declined',
            ]
        );

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('Your card was declined.', $result->errorMessage);
        $this->assertEquals('pi_failed_test', $result->transactionId);
    }

    public function test_it_validates_credit_card_input()
    {
        // Arrange
        $payable = $this->createMockPayable(100.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                // Missing required fields
                'payment_method_id' => 'pm_test',
                // Missing complete_name
            ]
        );

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->provider->processPayment($context);
    }

    public function test_it_processes_bank_transfer_payment()
    {
        // Arrange
        $payable = $this->createMockPayable(500.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER,
            paymentData: [
                'account_holder_name' => 'John Doe',
                'transit_number' => '12345',
                'institution_number' => '123',
                'account_number' => '1234567',
                'authorize_debit' => true,
            ]
        );

        $mockPaymentMethod = new PaymentMethod('pm_acss_test');

        $mockPaymentIntent = $this->createMockPaymentIntent(
            'pi_acss_test',
            50000,
            PaymentIntent::STATUS_PROCESSING
        );

        $this->mockPaymentMethodService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['type'] === 'acss_debit' &&
                       $params['acss_debit']['account_number'] === '1234567';
            }))
            ->andReturn($mockPaymentMethod);

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) {
                return $params['payment_method_types'] === ['acss_debit'] &&
                       $params['currency'] === 'cad';
            }))
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertTrue($result->isPending); // Bank transfers are processing
        $this->assertEquals('pi_acss_test', $result->transactionId);
        $this->assertEquals(500.00, $result->amount);
    }

    public function test_it_includes_billing_details_when_available()
    {
        // Arrange
        $payable = $this->createMockPayableWithAddress();
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_test',
                'complete_name' => 'John Doe',
            ]
        );

        $mockPaymentIntent = $this->createMockPaymentIntent('pi_test', 10000, PaymentIntent::STATUS_SUCCEEDED);

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($params) use ($payable) {
                return $params['receipt_email'] === $payable->getEmail();
            }))
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertTrue($result->isSuccessful());
    }

    public function test_it_handles_stripe_api_errors()
    {
        // Arrange
        $payable = $this->createMockPayable(100.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD,
            paymentData: [
                'payment_method_id' => 'pm_test',
                'complete_name' => 'John Doe',
            ]
        );

        $apiError = Mockery::mock(ApiErrorException::class);
        $apiError->shouldReceive('getMessage')->andReturn('Invalid API key');
        $apiError->shouldReceive('getStripeCode')->andReturn('invalid_api_key');

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->andThrow($apiError);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertFalse($result->isSuccessful());
        $this->assertEquals('Invalid API key', $result->errorMessage);
        $this->assertEquals('stripe', $result->paymentProviderCode);
    }

    public function test_it_handles_verify_with_microdeposits()
    {
        // Arrange
        $payable = $this->createMockPayable(100.00);
        $context = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER,
            paymentData: [
                'account_holder_name' => 'John Doe',
                'transit_number' => '12345',
                'institution_number' => '123',
                'account_number' => '1234567',
                'authorize_debit' => true,
            ]
        );

        $mockPaymentMethod = new PaymentMethod('pm_acss_test');

        $mockPaymentIntent = $this->createMockPaymentIntent(
            'pi_microdeposits',
            10000,
            PaymentIntent::STATUS_REQUIRES_ACTION,
            [
                'type' => 'verify_with_microdeposits',
                'verify_with_microdeposits' => [
                    'hosted_verification_url' => 'https://stripe.com/verify-microdeposits',
                ],
            ]
        );

        $this->mockPaymentMethodService
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockPaymentMethod);

        $this->mockPaymentIntentService
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockPaymentIntent);

        $this->mockPaymentIntentService
            ->shouldReceive('confirm')
            ->once()
            ->andReturn($mockPaymentIntent);

        // Act
        $result = $this->provider->processPayment($context);

        // Assert
        $this->assertTrue($result->isPending);
        $this->assertEquals('https://stripe.com/verify-microdeposits', $result->redirectUrl);
        $this->assertEquals(PaymentActionEnum::REDIRECT, $result->action);
    }

    public function test_it_gets_correct_payment_form()
    {
        // Arrange
        $payable = $this->createMockPayable(100.00);

        // Test credit card form
        $creditCardContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::CREDIT_CARD
        );

        $bankTransferContext = new PaymentContext(
            payable: $payable,
            paymentMethod: PaymentMethodEnum::BANK_TRANSFER
        );

        // Act
        $creditCardForm = $this->provider->getPaymentForm($creditCardContext);
        $bankTransferForm = $this->provider->getPaymentForm($bankTransferContext);

        // Assert
        $this->assertNotNull($creditCardForm);
        $this->assertNotNull($bankTransferForm);
        $this->assertNotSame($creditCardForm, $bankTransferForm);
    }

    // Helper methods

    private function createMockPayable(float $amount): PayableInterface
    {
        $payable = Mockery::mock(PayableInterface::class);
        $payable->shouldReceive('getPayableId')->andReturn(1);
        $payable->shouldReceive('getPayableType')->andReturn('test_order');
        $payable->shouldReceive('getTeamId')->andReturn(1);
        $payable->shouldReceive('getPayableAmount')->andReturn(new SafeDecimal($amount));
        $payable->shouldReceive('getPayableLines')->andReturn(collect());
        $payable->shouldReceive('getPaymentDescription')->andReturn('Test payment');
        $payable->shouldReceive('getPaymentMetadata')->andReturn(['test' => true]);
        $payable->shouldReceive('getAddress')->andReturn(null);
        $payable->shouldReceive('getEmail')->andReturn('test@example.com');
        $payable->shouldReceive('getCustomerName')->andReturn('Test Customer');

        return $payable;
    }

    private function createMockPayableWithAddress(): PayableInterface
    {
        $payable = $this->createMockPayable(100.00);

        $address = new Address([
            'street_number' => '123',
            'address1' => 'Main Street',
            'address2' => 'Suite 100',
            'city' => 'Toronto',
            'state' => 'ON',
            'postal_code' => 'M5V 3A8',
            'country' => 'CA',
        ]);

        $payable->shouldReceive('getAddress')->andReturn($address);

        return $payable;
    }

    private function createMockPaymentIntent(
        string $id,
        int $amountInCents,
        string $status,
        ?array $nextAction = null,
        ?array $lastPaymentError = null
    ): PaymentIntent {
        $intent = Mockery::mock(PaymentIntent::class);
        $intent->id = $id;
        $intent->amount = $amountInCents;
        $intent->status = $status;
        $intent->metadata = collect(['test' => true]);

        if ($nextAction) {
            $intent->next_action = (object) $nextAction;
        }

        if ($lastPaymentError) {
            $intent->last_payment_error = (object) $lastPaymentError;
        }

        return $intent;
    }
}
