<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Cache;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * An edit in the backend (through DataHandler) flushes every cached response
 * it changes: the next request is a MISS that shows the edit.
 */
final class InvalidationTest extends AbstractCacheTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pages_composition', 'tt_content_composition', 'pages_navigation'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Cache/be_users.csv');
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($this->setUpBackendUser(2));
    }

    #[Test]
    public function creatingContentFlushesItsPage(): void
    {
        $this->warm('/_api/headless_pages/4');

        $this->process(['tt_content' => ['NEW1' => ['pid' => 4, 'CType' => 'header', 'header' => 'New content', 'colPos' => 0]]]);

        self::assertContains('New content', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function movingContentFlushesTheOldAndTheNewPage(): void
    {
        $this->warm('/_api/headless_pages/4');
        $this->warm('/_api/headless_pages/5');

        $this->process([], ['tt_content' => [201 => ['move' => 5]]]);

        self::assertNotContains('First in main', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
        self::assertContains('First in main', self::headersIn($this->fetch('/_api/headless_pages/5', 'MISS')));
    }

    #[Test]
    public function deletingContentFlushesItsPage(): void
    {
        $this->warm('/_api/headless_pages/4');

        $this->process([], ['tt_content' => [200 => ['delete' => 1]]]);

        self::assertNotContains('Second in main', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function hidingContentFlushesItsPage(): void
    {
        $this->warm('/_api/headless_pages/4');

        $this->process(['tt_content' => [200 => ['hidden' => 1]]]);

        self::assertNotContains('Second in main', self::headersIn($this->fetch('/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function editingTheSeoFieldsOfAPageFlushesIt(): void
    {
        $this->warm('/_api/headless_pages/4');

        $this->process(['pages' => [4 => ['description' => 'Edited description']]]);

        self::assertSame('Edited description', $this->fetch('/_api/headless_pages/4', 'MISS')['description']);
    }

    #[Test]
    public function changingTheLayoutOfAPageFlushesIt(): void
    {
        self::assertSame(['content', 'aside', 'right'], array_keys($this->warm('/_api/headless_pages/6')['regions']));

        $this->process(['pages' => [6 => ['backend_layout' => 'pagets__numbered']]]);

        self::assertSame([0], array_keys($this->fetch('/_api/headless_pages/6', 'MISS')['regions']));
    }

    #[Test]
    public function hidingAPageInMenusFlushesTheNavigation(): void
    {
        self::assertContains('Products', self::childTitles($this->warm('/_api/headless_navigation/70')));

        $this->process(['pages' => [71 => ['nav_hide' => 1]]]);

        self::assertNotContains('Products', self::childTitles($this->fetch('/_api/headless_navigation/70', 'MISS')));
    }

    #[Test]
    public function editingAPageTranslationFlushesIt(): void
    {
        $this->warm('/de/_api/headless_pages/4');

        $this->process(['pages' => [22 => ['title' => 'Bearbeitet']]]);

        self::assertSame('Bearbeitet', $this->fetch('/de/_api/headless_pages/4', 'MISS')['title']);
    }

    #[Test]
    public function editingAContentTranslationFlushesItsPage(): void
    {
        $this->warm('/de/_api/headless_pages/4');

        $this->process(['tt_content' => [211 => ['header' => 'Bearbeiteter Inhalt']]]);

        self::assertContains('Bearbeiteter Inhalt', self::headersIn($this->fetch('/de/_api/headless_pages/4', 'MISS')));
    }

    #[Test]
    public function creatingAPageFlushesTheNavigationAndTheLookup(): void
    {
        $this->warm('/_api/headless_navigation/70');
        self::assertSame([], self::memberUids($this->warm('/_api/headless_pages?path=/navigation/new')));

        $uid = $this->process(['pages' => ['NEW1' => ['pid' => 70, 'title' => 'New page', 'slug' => '/navigation/new', 'doktype' => 1, 'hidden' => 0]]])['NEW1'];

        self::assertContains('New page', self::childTitles($this->fetch('/_api/headless_navigation/70', 'MISS')));
        self::assertSame([$uid], self::memberUids($this->fetch('/_api/headless_pages?path=/navigation/new', 'MISS')));
    }

    #[Test]
    public function movingAPageFlushesTheNavigation(): void
    {
        $this->warm('/_api/headless_navigation/70');

        $this->process([], ['pages' => [73 => ['move' => 71]]]);

        $navigation = $this->fetch('/_api/headless_navigation/70', 'MISS');
        self::assertNotContains('External', self::childTitles($navigation));
        self::assertContains('External', self::childTitles($navigation['children'][0]));
    }

    #[Test]
    public function hidingAPageFlushesTheNavigationAndTheLookup(): void
    {
        $this->warm('/_api/headless_navigation/70');
        self::assertSame([71], self::memberUids($this->warm('/_api/headless_pages?path=/navigation/products')));

        $this->process(['pages' => [71 => ['hidden' => 1]]]);

        self::assertNotContains('Products', self::childTitles($this->fetch('/_api/headless_navigation/70', 'MISS')));
        self::assertSame([], self::memberUids($this->fetch('/_api/headless_pages?path=/navigation/products', 'MISS')));
    }

    /**
     * @param array<string, array<int|string, array<string, mixed>>> $data
     * @param array<string, array<int, array<string, mixed>>> $commands
     * @return array<string, int> uids of the new records by their NEW id
     */
    private function process(array $data, array $commands = []): array
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, $commands);
        $dataHandler->process_datamap();
        $dataHandler->process_cmdmap();
        self::assertSame([], $dataHandler->errorLog);

        return $dataHandler->substNEWwithIDs;
    }
}
