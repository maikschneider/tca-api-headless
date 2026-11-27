<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional;

use MaikSchneider\TcaApi\Command\OpenApiSpecCommand;
use MaikSchneider\TcaApi\Loader\ApiDefinitionLoader;
use MaikSchneider\TcaApi\Registry\ApiRegistry;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Two sites share the page tree of Fixtures/pages.csv: "main" (http://localhost/,
 * root 1) includes the add-on's site set, "other" (http://other.localhost/,
 * root 10) only TCA API's.
 */
abstract class AbstractApiTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['seo'];

    protected array $testExtensionsToLoad = [
        'maikschneider/tca-api',
        'maikschneider/tca-api-headless',
    ];

    protected array $pathsToLinkInTestInstance = [
        'typo3conf/ext/tca_api_headless/Tests/Functional/Fixtures/Sites' => 'typo3conf/sites',
    ];

    /** @var array{mixed, mixed, mixed, AspectInterface}|null EXEC_TIME, SIM_EXEC_TIME, SIM_ACCESS_TIME and the date aspect before moveClockTo() */
    private ?array $clock = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The registry keeps its definitions in a static property, which outlives
        // the instance of a test class that loads other extensions.
        $this->get(ApiRegistry::class)->reset();
        $this->get(ApiDefinitionLoader::class)->load();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
    }

    protected function tearDown(): void
    {
        if ($this->clock !== null) {
            [$GLOBALS['EXEC_TIME'], $GLOBALS['SIM_EXEC_TIME'], $GLOBALS['SIM_ACCESS_TIME'], $date] = $this->clock;
            $this->get(Context::class)->setAspect('date', $date);
            $this->clock = null;
        }
        parent::tearDown();
    }

    /**
     * Moves the clock of the date aspect and of core's start and end time
     * restrictions, which read the simulated access time; restored after the test.
     */
    protected function moveClockTo(int $timestamp): void
    {
        $this->clock ??= [$GLOBALS['EXEC_TIME'], $GLOBALS['SIM_EXEC_TIME'], $GLOBALS['SIM_ACCESS_TIME'], $this->get(Context::class)->getAspect('date')];
        $GLOBALS['EXEC_TIME'] = $GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['SIM_ACCESS_TIME'] = $timestamp;
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable('@' . $timestamp)));
    }

    protected function request(string $url): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest($url));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        $data = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * The OpenAPI document of a site, as written by the CLI command.
     *
     * @return array<string, mixed>
     */
    protected function openApiDocument(string $site): array
    {
        $tester = new CommandTester($this->get(OpenApiSpecCommand::class));
        self::assertSame(Command::SUCCESS, $tester->execute(['site' => $site]));

        $document = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($document);

        return $document;
    }
}
