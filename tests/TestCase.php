<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.enabled' => false,
            'updater.enabled' => false,
            'backup.restore.enabled' => false,
        ]);
    }

    protected function opensslKeyOptions(): array
    {
        $candidates = [
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            (string) getenv('OPENSSL_CONF'),
        ];

        $configPath = collect($candidates)->first(fn ($path) => $path !== '' && is_file($path));

        return $configPath ? ['config' => $configPath] : [];
    }
}
