<?php

declare(strict_types=1);

namespace Survos\FtmResolver;

use Survos\FollowTheMoney\{Entity, Model};
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

final readonly class YenteProvider implements ResolutionProvider
{
    public function __construct(private HttpClientInterface $http, private DecoderInterface $decoder, private Model $model, private string $baseUri, private ?string $token = null)
    {
        if (!in_array(parse_url($baseUri, PHP_URL_SCHEME), ['http', 'https'], true) || !parse_url($baseUri, PHP_URL_HOST)) {
            throw new \InvalidArgumentException('A full HTTP(S) provider URL is required');
        }
    }
    public function name(): string
    {
        return 'yente';
    }
    public function catalog(): array
    {
        return $this->request('GET', 'catalog');
    }
    public function search(string $dataset, string $query, int $offset = 0, int $limit = 20): ResultPage
    {
        $this->pageBounds($offset, $limit);
        return $this->page($this->request('GET', 'search/'.rawurlencode($dataset), ['query' => ['q' => $query, 'schema' => 'Person', 'offset' => $offset, 'limit' => $limit]]));
    }
    public function match(string $dataset, Entity $entity, int $limit = 10): ResultPage
    {
        $this->pageBounds(0, $limit);
        $wire = $entity->jsonSerialize();
        $data = $this->request('POST', 'match/'.rawurlencode($dataset), ['query' => ['limit' => $limit], 'json' => ['queries' => ['subject' => ['schema' => $wire['schema'],'properties' => $wire['properties']]]]]);
        if (!isset($data['responses']['subject'])) {
            throw new ProviderException(null, 'Missing correlated match response');
        }
        return $this->page($data['responses']['subject']);
    }
    public function entity(string $id): Entity
    {
        return $this->model->fromArray($this->request('GET', 'entities/'.rawurlencode($id)));
    }
    public function adjacent(string $id, int $offset = 0, int $limit = 20): AdjacentGraph
    {
        $this->pageBounds($offset, $limit);
        $data = $this->request('GET', 'entities/'.rawurlencode($id).'/adjacent', ['query' => ['offset' => $offset,'limit' => $limit]]);
        if (!isset($data['entity'],$data['adjacent'])) {
            throw new ProviderException(null, 'Malformed adjacency response');
        }
        $groups = [];
        foreach ($data['adjacent'] as $property => $group) {
            $entities = [];
            foreach ($group['results'] ?? [] as $row) {
                $entities[] = is_string($row) ? $row : $this->model->fromArray($row);
            }
            $groups[$property] = ['entities' => $entities,'total' => $group['total']['value'],'relation' => $group['total']['relation']];
        }
        return new AdjacentGraph($this->model->fromArray($data['entity']), $groups, $data);
    }
    private function pageBounds(int $offset, int $limit): void
    {
        if ($offset < 0 || $offset > 9499 || $limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Invalid page bounds');
        }
    }
    private function page(array $data): ResultPage
    {
        if (!isset($data['results'], $data['total']['value'], $data['total']['relation']) || !is_array($data['results'])) {
            throw new ProviderException(null, 'Malformed provider result page');
        }
        $candidates = [];
        foreach ($data['results'] as $row) {
            if (isset($row['score']) && (!is_numeric($row['score']) || !is_finite((float) $row['score']))) {
                throw new ProviderException(null, 'Invalid candidate score');
            }
            $candidates[] = new Candidate($this->model->fromArray($row), isset($row['score']) ? (float)$row['score'] : null, isset($row['match']) ? (bool)$row['match'] : null, $row);
        }
        return new ResultPage($candidates, (int)$data['total']['value'], (string)$data['total']['relation'], $data);
    }
    private function request(string $method, string $path, array $options = []): array
    {
        // No redirects: gateway credentials must never follow another origin.
        $options += ['max_redirects' => 0, 'timeout' => 30, 'max_duration' => 60];
        if ($this->token !== null && $this->token !== '') {
            $options['auth_bearer'] = $this->token;
        }
        try {
            $response = $this->http->request($method, rtrim($this->baseUri, '/').'/'.$path, $options);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new ProviderException($status, 'Resolution provider returned HTTP '.$status);
            }
            $data = $this->decoder->decode($response->getContent(), 'json');
            if (!is_array($data)) {
                throw new ProviderException($status, 'Expected a JSON object');
            }
            return $data;
        } catch (ProviderException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProviderException(null, 'Resolution provider request failed', $e);
        }
    }
}
