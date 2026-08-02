<?php

namespace Recca0120\Terminal\Tests\Console\Commands;

use Illuminate\Container\Container;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Recca0120\Terminal\Console\Commands\Composer;
use Symfony\Component\Console\Tester\CommandTester;

class ComposerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_help_is_shown_without_command()
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([], []);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('composer install', $commandTester->getDisplay());
        self::assertStringContainsString('composer require', $commandTester->getDisplay());
    }

    public function test_blocked_command_is_rejected()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => 'exec ls'], []);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('desteklenmiyor', $commandTester->getDisplay());
    }

    public function test_embedded_composer_runs_in_process()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => 'about'], []);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Composer', $commandTester->getDisplay());
    }

    /**
     * Regresyon: JS argümanı --command="..." biçiminde tırnaklarla gönderir
     * ve Application::call() boşluk içeren değeri bir kez daha tırnaklar.
     * Tırnaklar soyulmazsa Symfony komut satırının tamamını komut adı sanıp
     * 'Command "config --list" is not defined' hatası verir.
     */
    public function test_strips_quotes_added_by_the_web_terminal()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => '"config --list"'], []);

        self::assertStringNotContainsString('is not defined', $commandTester->getDisplay());
        self::assertSame(0, $exitCode);
    }

    public function test_strips_leaked_command_prefix()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => '--command="config --list"'], []);

        self::assertStringNotContainsString('is not defined', $commandTester->getDisplay());
        self::assertSame(0, $exitCode);
    }

    /**
     * Regresyon: kullanıcı filtreyi tırnaklarsa (show "composer/") normalizasyon
     * yalnızca en dıştaki çifti soyar; iç tırnaklar filtreden temizlenmezse
     * eşleşme bulunamaz.
     */
    public function test_show_filter_ignores_surrounding_quotes()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => 'show "composer/"'], []);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('composer/composer', $commandTester->getDisplay());
    }

    public function test_show_reads_installed_packages()
    {
        $commandTester = $this->getCommandTester();

        $exitCode = $commandTester->execute(['--command' => 'show composer/composer'], []);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('composer/composer', $commandTester->getDisplay());
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    private function getCommandTester(): CommandTester
    {
        $laravel = m::mock(new Container());
        $laravel->shouldReceive('runningUnitTests')->andReturn(false);
        $laravel->shouldReceive('basePath')->andReturnUsing(
            fn ($path = '') => realpath(__DIR__ . '/../../..') . ($path !== '' ? '/' . $path : '')
        );
        $laravel->shouldReceive('make')->andReturnUsing(function ($abstract, array $parameters = []) {
            return $abstract === 'path.storage'
                ? sys_get_temp_dir()
                : (new Container())->make($abstract, $parameters);
        });

        // tests/bootstrap.php içindeki base_path()/storage_path() helper'ları
        // Container::getInstance() üzerinden çalışır.
        Container::setInstance($laravel);

        $command = new Composer();
        $command->setLaravel($laravel);

        return new CommandTester($command);
    }
}
