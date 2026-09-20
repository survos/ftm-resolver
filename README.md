# survos/ftm-resolver

Provider-neutral historical candidate lookup, with a Yente 5.5 adapter. See the
[shared ecosystem contract](../followthemoney/docs/ecosystem.md).

Supported subset: Person search (offset/limit), FtM query-by-example matching,
entity retrieval, property-grouped adjacency and catalog metadata. It is not a
complete Yente SDK: reconciliation, administrative update and algorithm endpoints
remain available directly but have no client methods. Adjacency is upstream beta.

```php
$provider = new Survos\FtmResolver\YenteProvider(
    Symfony\Component\HttpClient\HttpClient::create(),
    new Symfony\Component\Serializer\Serializer([], [new Symfony\Component\Serializer\Encoder\JsonEncoder()]),
    Survos\FollowTheMoney\Model::bundled(),
    'http://127.0.0.1:8100',
);
$page = $provider->search('rappnews_1952_1954', 'Mary Miller');
foreach ($page->candidates as $candidate) {
    // Original FtM entity, optional score/flag, and unmodified provider row.
    echo $candidate->entity->name;
}
```

A full base URL may include a path prefix. Optional token means a gateway bearer
credential, not Yente's administrative update token. Redirects are disabled to
prevent cross-origin credential forwarding. HTTP errors retain status codes,
without reflecting provider error bodies or credentials. Timeouts are bounded;
there is no client-owned response cache or automatic retry. The application owns
retry and freshness policy. Raw payloads remain available for provenance.

IDs returned as references in adjacent groups remain strings. Group totals and
pagination must not be treated as a global graph total. Unknown schema properties
fail through the FtM model; deploy matching schema versions or register explicit
extensions. Do not silently drop them.

From mono: `vendor/bin/phpunit -c lib/ftm-resolver/phpunit.xml.dist`.
Snapshot: `resources/yente-5.5.0.openapi.json`, SHA-256 and retrieval provenance in
`resources/provenance.json`. Fixtures test transport serialization, pagination,
null scores, unknown metadata, correlated requests and status handling.
