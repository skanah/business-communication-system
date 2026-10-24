<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Channels\SMSChannel;
use App\Channels\EmailChannel;
use App\Services\ChannelRegistry;
use App\Services\SmsService;
use App\Services\EmailService;
use App\Models\Contact;
use Mockery;

class ChannelAbstractionTest extends TestCase
{
    public function test_sms_channel_send_method()
    {
        // Create a mock SMS service
        $mockSmsService = Mockery::mock(SmsService::class);
        $mockSmsService->shouldReceive('isConfigured')->andReturn(true);
        $mockSmsService->shouldReceive('sendSms')
            ->with('+1234567890', 'Test message', Mockery::any())
            ->andReturn([
                'message_id' => 'msg_123',
                'provider_response' => 'sent',
                'cost' => 0.05,
                'currency' => 'USD',
            ]);

        // Create a contact with SMS consent
        $contact = Mockery::mock(Contact::class);
        $contact->shouldReceive('getAttribute')->with('phone')->andReturn('+1234567890');
        $contact->shouldReceive('getAttribute')->with('tenant_id')->andReturn(1);
        $contact->shouldReceive('hasSmsConsent')->andReturn(true);
        $contact->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $contact->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('phone')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('tenant_id')->andReturn(true);
        $contact->shouldReceive('setAttribute')->with('id', 1)->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('phone', '+1234567890')->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('tenant_id', 1)->andReturnUndefined();

        // Create an SMS channel instance
        $smsChannel = new SMSChannel($mockSmsService);

        // Test sending an SMS
        $result = $smsChannel->send('Test message', $contact, [
            'test_context' => true
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_sms_channel_no_consent()
    {
        // Create a mock SMS service
        $mockSmsService = Mockery::mock(SmsService::class);

        // Create an SMS channel instance
        $smsChannel = new SMSChannel($mockSmsService);

        // Create a contact without SMS consent
        $contact = Mockery::mock(Contact::class);
        $contact->shouldReceive('getAttribute')->with('phone')->andReturn('+1234567890');
        $contact->shouldReceive('getAttribute')->with('email')->andReturn('test@example.com');
        $contact->shouldReceive('hasSmsConsent')->andReturn(false);
        $contact->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $contact->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('phone')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('email')->andReturn(true);
        $contact->shouldReceive('setAttribute')->with('id', 1)->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('phone', '+1234567890')->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('email', 'test@example.com')->andReturnUndefined();

        // Test sending an SMS to a contact without consent
        $result = $smsChannel->send('Test message', $contact, []);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_SMS_CONSENT', $result['error_code']);
    }

    public function test_email_channel_send_method()
    {
        // Create a mock Email service
        $mockEmailService = Mockery::mock(EmailService::class);
        $mockEmailService->shouldReceive('isConfigured')->andReturn(true);
        $mockEmailService->shouldReceive('sendEmail')
            ->with('test@example.com', 'Test Subject', 'Test Body', Mockery::any())
            ->andReturn([
                'message_id' => 'email_123',
                'provider_response' => 'sent',
                'cost' => 0.01,
                'currency' => 'USD',
            ]);

        // Create an Email channel instance
        $emailChannel = new EmailChannel($mockEmailService);

        // Create a contact with email consent
        $contact = Mockery::mock(Contact::class);
        $contact->shouldReceive('getAttribute')->with('email')->andReturn('test@example.com');
        $contact->shouldReceive('getAttribute')->with('tenant_id')->andReturn(1);
        $contact->shouldReceive('hasEmailConsent')->andReturn(true);
        $contact->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $contact->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('email')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('tenant_id')->andReturn(true);
        $contact->shouldReceive('setAttribute')->with('id', 1)->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('email', 'test@example.com')->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('tenant_id', 1)->andReturnUndefined();

        // Test sending an email
        $result = $emailChannel->send('Test Body', $contact, [
            'subject' => 'Test Subject'
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_email_channel_no_consent()
    {
        // Create a mock Email service
        $mockEmailService = Mockery::mock(EmailService::class);

        // Create an Email channel instance
        $emailChannel = new EmailChannel($mockEmailService);

        // Create a contact without email consent
        $contact = Mockery::mock(Contact::class);
        $contact->shouldReceive('getAttribute')->with('email')->andReturn('test@example.com');
        $contact->shouldReceive('hasEmailConsent')->andReturn(false);
        $contact->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $contact->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $contact->shouldReceive('offsetExists')->with('email')->andReturn(true);
        $contact->shouldReceive('setAttribute')->with('id', 1)->andReturnUndefined();
        $contact->shouldReceive('setAttribute')->with('email', 'test@example.com')->andReturnUndefined();

        // Test sending an email to a contact without consent
        $result = $emailChannel->send('Test Body', $contact, [
            'subject' => 'Test Subject'
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('NO_EMAIL_CONSENT', $result['error_code']);
    }

    public function test_sms_channel_availability()
    {
        // Create a mock SMS service
        $mockSmsService = Mockery::mock(SmsService::class);
        $mockSmsService->shouldReceive('isConfigured')->andReturn(true);

        // Create an SMS channel instance
        $smsChannel = new SMSChannel($mockSmsService);

        $this->assertTrue($smsChannel->isAvailable());
        $this->assertEquals('sms', $smsChannel->getName());
    }

    public function test_email_channel_availability()
    {
        // Create a mock Email service
        $mockEmailService = Mockery::mock(EmailService::class);
        $mockEmailService->shouldReceive('isConfigured')->andReturn(true);

        // Create an Email channel instance
        $emailChannel = new EmailChannel($mockEmailService);

        $this->assertTrue($emailChannel->isAvailable());
        $this->assertEquals('email', $emailChannel->getName());
    }

    public function test_channel_registry()
    {
        // Create a channel registry instance
        $registry = new ChannelRegistry();

        // Check if default channels are registered
        $this->assertTrue($registry->hasChannel('sms'));
        $this->assertTrue($registry->hasChannel('email'));

        // Get the channels
        $smsChannel = $registry->getChannel('sms');
        $emailChannel = $registry->getChannel('email');

        $this->assertInstanceOf(\App\Channels\SMSChannel::class, $smsChannel);
        $this->assertInstanceOf(\App\Channels\EmailChannel::class, $emailChannel);

        // Check all channels
        $allChannels = $registry->getAllChannels();
        $this->assertCount(2, $allChannels);
        $this->assertArrayHasKey('sms', $allChannels);
        $this->assertArrayHasKey('email', $allChannels);
    }
}