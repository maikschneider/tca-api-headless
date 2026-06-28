<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Composition;

use MaikSchneider\TcaApiHeadless\Composition\PageComposer;
use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractHeadlessTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Site\Entity\Site;

final class PageComposerTest extends AbstractHeadlessTestCase
{
    private function defaultSite(): Site
    {
        return new Site('test', 1, [
            'base' => '/',
            'languages' => [
                ['languageId' => 0, 'locale' => 'en_US.UTF-8', 'base' => '/'],
                ['languageId' => 1, 'locale' => 'de_DE.UTF-8', 'base' => '/de/'],
            ],
        ]);
    }

    #[Test]
    public function composesPageEnvelopeWithMeta(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $payload = $this->get(PageComposer::class)->compose(2, $this->defaultSite()->getDefaultLanguage());

        self::assertNotNull($payload);
        self::assertSame('1.0', $payload['contract']);
        self::assertSame('page', $payload['type']);
        self::assertSame(2, $payload['id']);
        self::assertSame('Team', $payload['meta']['title']);
        self::assertSame('en', $payload['meta']['language']);
        self::assertSame('/team', $payload['meta']['slug']);
        self::assertInstanceOf(\stdClass::class, $payload['regions']);
    }

    #[Test]
    public function overlaysTranslatedTitleAndSlug(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        $german = $this->defaultSite()->getLanguageById(1);
        $payload = $this->get(PageComposer::class)->compose(2, $german);

        self::assertNotNull($payload);
        self::assertSame('Team (DE)', $payload['meta']['title']);
        self::assertSame('de', $payload['meta']['language']);
        self::assertSame('/de/team', $payload['meta']['slug']);
    }

    #[Test]
    public function returnsNullForMissingPage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');

        self::assertNull(
            $this->get(PageComposer::class)->compose(999, $this->defaultSite()->getDefaultLanguage()),
        );
    }
}
