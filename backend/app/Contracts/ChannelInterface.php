<?php

namespace App\Contracts;

use App\Models\Contact;

interface ChannelInterface
{
    /**
     * Send a message through this channel
     *
     * @param string $message The message content to send
     * @param Contact $contact The contact to send the message to
     * @param array $context Additional context for the message
     * @return array Result of the send operation
     */
    public function send(string $message, Contact $contact, array $context = []): array;

    /**
     * Check if the channel is available for sending
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the channel name/type
     *
     * @return string
     */
    public function getName(): string;
}