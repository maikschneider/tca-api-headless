<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Cache;

use PHPUnit\Framework\Attributes\Test;

/**
 * A cached response expires when one of the records it is built from starts or
 * ends, including records not visible yet: it is served from the cache until
 * then and built anew right after. The scheduled fixtures start at self::AT,
 * before the cache backend's upper expiry bound of 2038.
 */
final class ScheduledVisibilityTest extends AbstractCacheTestCase
{
    /** 2030-01-01 */
    private const AT = 1893456000;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_composition', 'tt_content_composition', 'pages_navigation', 'pages_access'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
        $this->update('tt_content', 209, ['starttime' => self::AT]);
        $this->update('pages', 82, ['starttime' => self::AT]);
        $this->update('pages', 409, ['starttime' => self::AT]);
        // An hour before the transitions, well within the default cache lifetime of a day.
        $this->moveClockTo(self::AT - 3600);
    }

    #[Test]
    public function aPageExpiresWhenItsContentStarts(): void
    {
        self::assertNotContains('Scheduled', self::headersIn($this->warm('/_api/headless_pages/4')));

        $this->moveClockTo(self::AT - 60);
        self::assertNotContains('Scheduled', self::headersIn($this->fetch('/_api/headless_pages/4', 'HIT')));

        $this->moveClockTo(self::AT + 60);
        self::assertContains('Scheduled', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function aPageExpiresWhenItsContentEnds(): void
    {
        $this->update('tt_content', 201, ['endtime' => self::AT]);
        self::assertContains('First in main', self::headersIn($this->warm('/_api/headless_pages/4')));

        $this->moveClockTo(self::AT - 60);
        self::assertContains('First in main', self::headersIn($this->fetch('/_api/headless_pages/4', 'HIT')));

        $this->moveClockTo(self::AT + 60);
        self::assertNotContains('First in main', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function aTranslatedPageExpiresWhenATranslationOfItsContentStarts(): void
    {
        $this->update('tt_content', 211, ['starttime' => self::AT]);
        self::assertNotContains('Zweiter im Hauptbereich', self::headersIn($this->warm('/de/_api/headless_pages/4')));

        $this->moveClockTo(self::AT - 60);
        self::assertNotContains('Zweiter im Hauptbereich', self::headersIn($this->fetch('/de/_api/headless_pages/4', 'HIT')));

        $this->moveClockTo(self::AT + 60);
        self::assertContains('Zweiter im Hauptbereich', self::headersIn($this->fetch('/de/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function aNavigationExpiresWhenASubpageStartsOrEnds(): void
    {
        $this->update('pages', 74, ['endtime' => self::AT]);
        $navigation = $this->warm('/_api/headless_navigation/70');
        self::assertNotContains('Scheduled', self::childTitles($navigation));
        self::assertSame(['Product A'], self::childTitles($navigation['children'][0]));

        $this->moveClockTo(self::AT - 60);
        self::assertSame($navigation, $this->fetch('/_api/headless_navigation/70', 'HIT'));

        $this->moveClockTo(self::AT + 60);
        $navigation = $this->fetch('/_api/headless_navigation/70', 'MISS');
        self::assertContains('Scheduled', self::childTitles($navigation));
        self::assertSame([], self::childTitles($navigation['children'][0]));
    }

    #[Test]
    public function aLookupExpiresWhenThePageStarts(): void
    {
        self::assertSame([], self::memberUids($this->warm('/_api/headless_pages?path=/navigation/scheduled')));

        $this->moveClockTo(self::AT - 60);
        self::assertSame([], self::memberUids($this->fetch('/_api/headless_pages?path=/navigation/scheduled', 'HIT')));

        $this->moveClockTo(self::AT + 60);
        self::assertSame([82], self::memberUids($this->fetch('/_api/headless_pages?path=/navigation/scheduled', 'MISS')));
    }

    #[Test]
    public function aLookupExpiresWhenAnAncestorThatExtendsToSubpagesStarts(): void
    {
        self::assertSame([], self::memberUids($this->warm('/_api/headless_pages?path=/access/scheduled-section/page')));

        $this->moveClockTo(self::AT - 60);
        self::assertSame([], self::memberUids($this->fetch('/_api/headless_pages?path=/access/scheduled-section/page', 'HIT')));

        $this->moveClockTo(self::AT + 60);
        self::assertSame([419], self::memberUids($this->fetch('/_api/headless_pages?path=/access/scheduled-section/page', 'MISS')));
    }

    #[Test]
    public function aResponseWithoutScheduledRecordsKeepsTheDefaultLifetime(): void
    {
        $this->warm('/_api/headless_pages/2');

        $this->moveClockTo(self::AT + 60);
        $this->fetch('/_api/headless_pages/2', 'HIT');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function update(string $table, int $uid, array $values): void
    {
        $this->getConnectionPool()->getConnectionForTable($table)->update($table, $values, ['uid' => $uid]);
    }
}
