<?php

namespace App\Services;

use App\Contracts\ChannelInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

class ChannelRegistry
{
    protected array $channels = [];

    public function __construct()
    {
        $this->registerDefaultChannels();
    }

    /**
     * Register a channel with the registry
     */
    public function register(string $name, string $class): void
    {
        $this->channels[$name] = $class;
    }

    /**
     * Get a channel by name
     */
    public function getChannel(string $name): ?ChannelInterface
    {
        if (!isset($this->channels[$name])) {
            return null;
        }

        return App::make($this->channels[$name]);
    }

    /**
     * Get all registered channels
     */
    public function getAllChannels(): Collection
    {
        $channelInstances = collect();
        
        foreach ($this->channels as $name => $class) {
            $channelInstances->put($name, App::make($class));
        }

        return $channelInstances;
    }

    /**
     * Check if a channel is registered
     */
    public function hasChannel(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    /**
     * Get channel names
     */
    public function getChannelNames(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Register default channels
     */
    protected function registerDefaultChannels(): void
    {
        $this->register('sms', \App\Channels\SMSChannel::class);
        $this->register('email', \App\Channels\EmailChannel::class);
    }

    /**
     * Dynamically register channels from configuration
     */
    public function registerFromConfig(array $config): void
    {
        foreach ($config as $name => $class) {
            $this->register($name, $class);
        }
    }
}