<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Tests\Functional\Acceptance;

use MaikSchneider\TcaApiHeadless\Tests\Functional\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Login-protected pages and content for three audiences: anonymous, the
 * member (fe_users 1, group 1) and the partner (fe_users 2, group 2). Fixtures
 * in pages_access.csv and tt_content_access.csv; the German pages and content
 * are translations of the English ones.
 */
final class AccessTest extends AbstractApiTestCase
{
    private const ANONYMOUS = 'anonymous';
    private const MEMBER = 'member';
    private const PARTNER = 'partner';
    private const USERS = [self::ANONYMOUS => null, self::MEMBER => 1, self::PARTNER => 2];

    private const ALL = [self::ANONYMOUS, self::MEMBER, self::PARTNER];

    /** Page uid => audiences that may see it: by language, and by path. */
    private const PAGES = [
        400 => ['' => ['/access', self::ALL], '/de' => ['/zugang', self::ALL]],
        401 => ['' => ['/access/members', [self::MEMBER]], '/de' => ['/zugang/mitglieder', [self::MEMBER]]],
        402 => ['' => ['/access/partners', [self::PARTNER]], '/de' => ['/zugang/partner', [self::PARTNER]]],
        403 => ['' => ['/access/partners/page', [self::PARTNER]], '/de' => ['/zugang/partner/seite', [self::PARTNER]]],
        404 => ['' => ['/access/any-login', [self::MEMBER, self::PARTNER]]],
        405 => ['' => ['/access/hide-at-login', [self::ANONYMOUS]]],
        406 => ['' => ['/access/hidden-section', []]],
        407 => ['' => ['/access/hidden-section/page', []]],
        408 => ['' => ['/access/translated-for-members', self::ALL], '/de' => ['/zugang/uebersetzt-fuer-mitglieder', [self::MEMBER]]],
        409 => ['' => ['/access/scheduled-section', []]],
        419 => ['' => ['/access/scheduled-section/page', []]],
    ];

    protected array $coreExtensionsToLoad = ['seo'];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['fe_groups', 'fe_users', 'pages_access', 'tt_content_access', 'sys_file_storage', 'sys_file', 'sys_file_metadata', 'sys_file_reference_access'] as $fixture) {
            $this->importCSVDataSet(__DIR__ . '/../Fixtures/' . $fixture . '.csv');
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function audiencesAndLanguages(): iterable
    {
        foreach (array_keys(self::USERS) as $audience) {
            yield $audience . ' EN' => [$audience, ''];
            yield $audience . ' DE' => [$audience, '/de'];
        }
    }

    #[Test]
    #[DataProvider('audiencesAndLanguages')]
    public function aPageIsOnlyServedToItsAudience(string $audience, string $language): void
    {
        $actual = [];
        $expected = [];
        foreach (self::PAGES as $uid => $languages) {
            $response = $this->requestAs("http://localhost{$language}/_api/headless_pages/{$uid}", $audience);
            $actual[$uid] = $response->getStatusCode();
            $expected[$uid] = \in_array($audience, $languages[$language][1] ?? [], true) ? 200 : 404;
        }

        self::assertSame($expected, $actual);
    }

    #[Test]
    #[DataProvider('audiencesAndLanguages')]
    public function aPathOnlyLeadsToPagesOfTheAudience(string $audience, string $language): void
    {
        $actual = [];
        $expected = [];
        foreach (self::PAGES as $uid => $languages) {
            if (!isset($languages[$language])) {
                continue;
            }
            [$path, $audiences] = $languages[$language];
            $response = $this->requestAs("http://localhost{$language}/_api/headless_pages?path=" . rawurlencode($path), $audience);
            self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
            $actual[$path] = array_column($this->json($response)['hydra:member'], 'uid');
            $expected[$path] = \in_array($audience, $audiences, true) ? [$uid] : [];
        }

        self::assertSame($expected, $actual);
    }

    /**
     * @return iterable<string, array{string, string, list<array{int, string}>}>
     */
    public static function contentPerAudience(): iterable
    {
        yield 'anonymous EN' => [self::ANONYMOUS, '', [[400, 'Public'], [405, 'Public in English']]];
        yield 'member EN' => [self::MEMBER, '', [[400, 'Public'], [401, 'For members'], [403, 'Members image'], [405, 'Public in English']]];
        yield 'partner EN' => [self::PARTNER, '', [[400, 'Public'], [402, 'For partners'], [404, 'For partners in every language'], [405, 'Public in English']]];
        yield 'anonymous DE' => [self::ANONYMOUS, '/de', [[400, 'Öffentlich']]];
        yield 'member DE' => [self::MEMBER, '/de', [[400, 'Öffentlich'], [401, 'Für Mitglieder'], [403, 'Mitgliederbild'], [405, 'Nur für Mitglieder auf Deutsch']]];
        yield 'partner DE' => [self::PARTNER, '/de', [[400, 'Öffentlich'], [402, 'Für Partner'], [404, 'For partners in every language']]];
    }

    /**
     * @param list<array{int, string}> $expected uid and header of each element
     */
    #[Test]
    #[DataProvider('contentPerAudience')]
    public function contentIsOnlyComposedForItsAudience(string $audience, string $language, array $expected): void
    {
        $response = $this->requestAs("http://localhost{$language}/_api/headless_pages/400", $audience);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $content = $this->json($response)['regions']['main'];

        self::assertSame($expected, array_map(static fn (array $element): array => [$element['uid'], $element['header']], $content));
        foreach ($content as $element) {
            if ($element['CType'] === 'image') {
                self::assertSame([$language === '' ? 'Members image' : 'Mitgliederbild'], array_column(array_column($element['image'], 'metadata'), 'alternative'));
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, array<string, list<string>>}>
     */
    public static function navigationPerAudience(): iterable
    {
        yield 'anonymous EN' => [self::ANONYMOUS, '', ['Hide at login' => [], 'Translated for members' => []]];
        yield 'member EN' => [self::MEMBER, '', ['Members' => [], 'Any login' => [], 'Translated for members' => []]];
        yield 'partner EN' => [self::PARTNER, '', ['Partners' => ['Partner page'], 'Any login' => [], 'Translated for members' => []]];
        yield 'anonymous DE' => [self::ANONYMOUS, '/de', []];
        yield 'member DE' => [self::MEMBER, '/de', ['Mitglieder' => [], 'Übersetzt für Mitglieder' => []]];
        yield 'partner DE' => [self::PARTNER, '/de', ['Partner' => ['Partnerseite']]];
    }

    /**
     * @param array<string, list<string>> $expected titles of the subpages and their subpages
     */
    #[Test]
    #[DataProvider('navigationPerAudience')]
    public function theNavigationOnlyListsPagesOfTheAudience(string $audience, string $language, array $expected): void
    {
        $response = $this->requestAs("http://localhost{$language}/_api/headless_navigation/400", $audience);
        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());

        $actual = [];
        foreach ($this->json($response)['children'] as $child) {
            $actual[$child['title']] = array_column($child['children'], 'title');
        }
        self::assertSame($expected, $actual);
    }

    #[Test]
    public function noResponseIsCachedForAnyAudience(): void
    {
        foreach (array_keys(self::USERS) as $audience) {
            foreach (['/_api/headless_pages/401', '/_api/headless_pages/400', '/_api/headless_pages?path=/access/members', '/_api/headless_navigation/400'] as $path) {
                foreach (['', '/de'] as $language) {
                    self::assertFalse($this->requestAs('http://localhost' . $language . $path, $audience)->hasHeader('X-TCA-API-Cache'));
                }
            }
        }

        self::assertSame(0, $this->getConnectionPool()->getConnectionForTable('cache_tca_api')->count('*', 'cache_tca_api', []));
    }

    private function requestAs(string $url, string $audience): ResponseInterface
    {
        $context = new InternalRequestContext();
        if (self::USERS[$audience] !== null) {
            $context = $context->withFrontendUserId(self::USERS[$audience]);
        }

        return $this->executeFrontendSubRequest(new InternalRequest($url), $context);
    }
}
