<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiProvider;
use App\Services\Ai\FakeAiProvider;
use App\Services\SettingService;
use Tests\TestCase;

abstract class AiTestCase extends TestCase
{
    protected FakeAiProvider $fake;

    protected function enableAi(array $responses = []): FakeAiProvider
    {
        config(['ai.api_key' => 'sk-test-SECRET-KEY-1234567890', 'ai.provider' => 'anthropic']);
        app(SettingService::class)->set('ai.enabled', true);
        $this->fake = new FakeAiProvider($responses);
        $this->app->instance(AiProvider::class, $this->fake);

        return $this->fake;
    }
}
