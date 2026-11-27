<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every resource of the add-on is switched on per site by its site set, is
 * cached only on sites that turn the cache on and exposes only the columns it
 * lists.
 */
final class ResourceDefinitionsTest extends UnitTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function definitionFiles(): array
    {
        $files = glob(__DIR__ . '/../../../Configuration/TcaApi/*.php') ?: [];
        self::assertNotSame([], $files);

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file, '.php')] = [$file];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('definitionFiles')]
    public function theResourceIsEnabledByTheSiteSet(string $file): void
    {
        self::assertSame('tca_api_headless.enabled', $this->definition($file)['general']['enabledSetting'] ?? null);
    }

    #[Test]
    #[DataProvider('definitionFiles')]
    public function theResourceIsCachedOnlyWhereTheSiteTurnsTheCacheOn(string $file): void
    {
        $cache = $this->definition($file)['cache'] ?? [];

        self::assertTrue($cache['enabled'] ?? null);
        self::assertSame('tca_api_headless.cache.enabled', $cache['enabledSetting'] ?? null);
    }

    #[Test]
    public function theCacheIsOffByDefault(): void
    {
        $definitions = Yaml::parseFile(__DIR__ . '/../../../Configuration/Sets/TcaApiHeadless/settings.definitions.yaml');

        self::assertSame(['category' => 'TcaApiHeadless', 'type' => 'bool', 'default' => false], $definitions['settings']['tca_api_headless.cache.enabled'] ?? null);
    }

    #[Test]
    #[DataProvider('definitionFiles')]
    public function everyColumnIsListedExplicitly(string $file): void
    {
        $columns = $this->definition($file)['columns'] ?? [];

        self::assertNotSame([], $columns);
        foreach ($columns as $column => $config) {
            self::assertNotEmpty($config['groups'] ?? null, $column);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function definition(string $file): array
    {
        $definition = require $file;
        self::assertIsArray($definition);

        return $definition;
    }
}
