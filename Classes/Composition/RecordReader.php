<?php

declare(strict_types=1);

namespace MaikSchneider\TcaApiHeadless\Composition;

use MaikSchneider\TcaApi\Configuration\ApiDefinition;
use MaikSchneider\TcaApi\DataAccess\DataRepository;
use MaikSchneider\TcaApi\DataAccess\EmbedPreloader;
use MaikSchneider\TcaApi\Filter\ExactFilter;
use MaikSchneider\TcaApi\Filter\FilterContext;
use MaikSchneider\TcaApi\Serializer\ResourceSerializer;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the records stored on some pages through TCA API's read pipeline in a
 * single query: language overlay, enable fields, the frontend user groups of
 * the request, embeds and column processors of the definition.
 */
final readonly class RecordReader
{
    public function __construct(
        private DataRepository $dataRepository,
        private EmbedPreloader $embedPreloader,
        private ResourceSerializer $serializer,
    ) {
    }

    /**
     * @param list<int> $pids
     * @return list<array{array<string, mixed>, array<string, mixed>}> raw row and serialized record, by sorting
     */
    public function readByPid(ApiDefinition $config, array $pids, ServerRequestInterface $request): array
    {
        $language = $request->getAttribute('tca_api.language');
        $constraints = [
            [ExactFilter::class, new FilterContext($pids, $config->table, 't.pid', ['type' => 'int'], $request, $config)],
            [FrontendGroupFilter::class, new FilterContext(null, $config->table, 't.fe_group', [], $request, $config)],
        ];

        $rows = $this->dataRepository->findCollection($config->table, $constraints, 0, 0, ['sorting' => 'asc'], $config, $language);
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
