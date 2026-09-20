<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Survos\FtmResolver\{YenteProvider,ProviderException};
use Survos\FollowTheMoney\Model;
use Symfony\Component\HttpClient\{MockHttpClient,Response\MockResponse};
use Symfony\Component\Serializer\{Serializer,Encoder\JsonEncoder};
final class YenteProviderTest extends TestCase
{
    private function client(callable $callback): YenteProvider { return new YenteProvider(new MockHttpClient($callback),new Serializer([],[new JsonEncoder()]),Model::bundled(),'https://example.test/prefix/','secret'); }
    private function wire(): array { return ['id'=>'person-1','schema'=>'Person','properties'=>['name'=>['Alex Example']],'score'=>0.9,'match'=>true,'newField'=>'preserved']; }
    public function testMatchingUsesCorrelatedEntityQueryAndPreservesUnknownMetadata(): void {
        $p=$this->client(function($method,$url,$options) {
            self::assertSame('POST',$method); self::assertStringStartsWith('https://example.test/prefix/match/archive',$url);
            $body=json_decode($options['body'],true); self::assertSame('Person',$body['queries']['subject']['schema']);
            self::assertSame(0,$options['max_redirects']);
            return new MockResponse(json_encode(['responses'=>['subject'=>['results'=>[$this->wire()],'total'=>['value'=>1,'relation'=>'eq']]]]));
        });
        $e=Model::bundled()->create('Person','input-1');$e->name='Alex Example';$result=$p->match('archive',$e);
        self::assertSame('person-1',$result->candidates[0]->entity->id);self::assertSame('preserved',$result->candidates[0]->raw['newField']);
        self::assertSame(0.9,$result->candidates[0]->score);
    }
    public function testPaginationAndMissingScores(): void {
        $p=$this->client(function($method,$url) {
            self::assertStringContainsString('offset=20',$url);
            return new MockResponse(json_encode(['results'=>[['id'=>'p2','schema'=>'Person','properties'=>['name'=>['Another']]]],'total'=>['value'=>10000,'relation'=>'gte']]));
        });
        $result=$p->search('archive','name',20);self::assertSame('gte',$result->totalRelation);self::assertNull($result->candidates[0]->score);self::assertNull($result->candidates[0]->suggestedMatch);
    }
    public function testAdjacencyRetainsPropertyGroupingAndReferences(): void {
        $p=$this->client(fn()=>new MockResponse(json_encode(['entity'=>$this->wire(),'adjacent'=>['family'=>['results'=>['family-1',$this->wire()],'total'=>['value'=>2,'relation'=>'eq']]]])));
        $graph=$p->adjacent('person-1');self::assertSame('family-1',$graph->groups['family']['entities'][0]);self::assertSame('person-1',$graph->groups['family']['entities'][1]->id);
    }
    public function testHttpErrorsRetainStatusWithoutLeakingResponseSecrets(): void {
        $p=$this->client(fn()=>new MockResponse('sensitive body',['http_code'=>403]));
        try { $p->catalog();self::fail('Expected HTTP error'); } catch(ProviderException $e) { self::assertSame(403,$e->status);self::assertStringNotContainsString('sensitive',$e->getMessage()); }
    }
    public function testMalformedMatchIsNotAnEmptySuccess(): void {
        $p=$this->client(fn()=>new MockResponse('{"responses":{}}'));
        $this->expectException(ProviderException::class);$p->match('archive',Model::bundled()->create('Person','input'));
    }
}
