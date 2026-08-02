<?php

namespace Recca0120\Terminal\Tests\Http\Middleware;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Recca0120\Terminal\Http\Middleware\AuthorizeTerminal;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthorizeTerminalTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_denies_when_disabled()
    {
        $this->givenConfig(['enabled' => false, 'whitelists' => []]);

        $this->expectException(NotFoundHttpException::class);

        $this->handle($this->requestFrom('127.0.0.1'));
    }

    public function test_allows_when_enabled_and_whitelist_empty()
    {
        $this->givenConfig(['enabled' => true, 'whitelists' => []]);

        self::assertSame('next', $this->handle($this->requestFrom('203.0.113.9')));
    }

    public function test_denies_ip_outside_whitelist()
    {
        $this->givenConfig(['enabled' => true, 'whitelists' => ['127.0.0.1']]);

        $this->expectException(NotFoundHttpException::class);

        $this->handle($this->requestFrom('203.0.113.9'));
    }

    public function test_allows_ip_inside_whitelist()
    {
        $this->givenConfig(['enabled' => true, 'whitelists' => ['203.0.113.9']]);

        self::assertSame('next', $this->handle($this->requestFrom('203.0.113.9')));
    }

    /**
     * Regresyon: 'enabled' her zaman bool döndüğü için beyaz liste dalı
     * hiç çalışmıyordu. Etkin + liste dolu iken IP kontrolü zorunludur.
     */
    public function test_whitelist_is_enforced_when_enabled_is_true()
    {
        $this->givenConfig(['enabled' => true, 'whitelists' => ['10.0.0.1']]);

        $this->expectException(NotFoundHttpException::class);

        $this->handle($this->requestFrom('127.0.0.1'));
    }

    /**
     * @param  array<string, mixed>  $terminal
     */
    private function givenConfig(array $terminal): void
    {
        $container = new Container();
        $container->instance('config', new Repository(['terminal' => $terminal]));
        Container::setInstance($container);
    }

    private function requestFrom(string $ip): Request
    {
        return Request::create('/terminal', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    private function handle(Request $request): mixed
    {
        return (new AuthorizeTerminal())->handle($request, fn () => 'next');
    }
}
