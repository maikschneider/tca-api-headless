<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\DataAccess\EmbedPreloader;
use MaikSchneider\TcaApi\Filter\ExactFilter;
use MaikSchneider\TcaApi\Filter\FilterContext;
use MaikSchneider\TcaApi\Serializer\ResourceSerializer;
use MaikSchneider\TcaApiHeadless\Cache\CacheDependencies;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;

/**
 * Reads the records stored on some pages through TCA API's read pipeline in a
 * single query: language overlay (or the records of one language as they are),
 * enable fields, the frontend user groups of the request, embeds and column
 * processors of the definition.
 */
final readonly class RecordReader
{
    public function __construct(
        private DataRepository $dataRepository,
        private EmbedPreloader $embedPreloader,
        private ResourceSerializer $serializer,
        private FrontendGroups $frontendGroups,
        private TcaSchemaFactory $schemaFactory,
        private CacheDependencies $cacheDependencies,
    ) {
    }

    /**
     * @param list<int> $pids
     * @param int|null $languageId Read the records of this language (and of all languages) as they
     *                             are, instead of the default-language records with their overlay
     * @return list<array{array<string, mixed>, array<string, mixed>}> raw row and serialized record, by sorting
     */
    public function readByPid(ApiDefinition $config, array $pids, ServerRequestInterface $request, ?int $languageId = null): array
    {
        $language = $request->getAttribute('tca_api.language');
        $constraints = [
            [ExactFilter::class, new FilterContext($pids, $config->table, 't.pid', ['type' => 'int'], $request, $config)],
            [FrontendGroupFilter::class, new FilterContext(null, $config->table, 't.fe_group', [], $request, $config)],
        ];
        $overlayLanguage = $language;
        $schema = $this->schemaFactory->get($config->table);
        if ($languageId !== null && $schema->isLanguageAware()) {
            $languageField = $schema->getCapability(TcaSchemaCapability::Language)->getLanguageField()->getName();
            $constraints[] = [ExactFilter::class, new FilterContext([$languageId, -1], $config->table, 't.' . $languageField, ['type' => 'int'], $request, $config)];
            $overlayLanguage = null;
        }

        // DataHandler flushes the pageId tag of the page a record is created on, moved to or changed on.
        foreach ($pids as $pid) {
            $this->cacheDependencies->addTag('pageId_' . $pid);
        }
        $this->cacheDependencies->expireWithRecords($config->table, $constraints, $config, $overlayLanguage);

        $rows = $this->dataRepository->findCollection($config->table, $constraints, 0, 0, ['sorting' => 'asc'], $config, $overlayLanguage);
        // The query checks the groups of the default language; a translation may restrict itself further.
        $rows = array_values(array_filter($rows, fn (array $row): bool => $this->frontendGroups->grant($row['fe_group'] ?? '')));
        if ($rows === []) {
            return [];
        }

        $allowedResources = $request->getAttribute('tca_api.allowed_resources');
        $preloaded = $this->embedPreloader->preload(
            $rows,
            $config,
            $language,
            [],
            'show',
            \is_array($allowedResources) ? array_values(array_filter($allowedResources, \is_string(...))) : [],
        ) + ['__request' => $request];
        $apiPrefix = (string)$request->getAttribute('tca_api.api_prefix', '/_api');
        $serialized = $this->serializer->serializeCollection($rows, $config, $apiPrefix . '/' . $config->resourceName, [], $preloaded, 'show');

        return array_map(null, $rows, $serialized);
    }
}
