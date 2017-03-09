<?php
use Maghead\Testing\ModelTestCase;
use Maghead\Sharding\Manager\ShardManager;
use Maghead\ConfigLoader;
use StoreApp\Model\{Store, StoreSchema};
use StoreApp\StoreTestCase;

/**
 * @group sharding
 * @group manager
 */
class ShardManagerTest extends StoreTestCase
{
    protected $freeConnections = false;

    public function testGetMappingById()
    {
        $shardManager = new ShardManager($this->config, $this->connManager);
        $mapping = $shardManager->getShardMapping('M_store_id');
        $this->assertNotEmpty($mapping);
    }

    public function testGetShards()
    {
        $shardManager = new ShardManager($this->config, $this->connManager);
        $shards = $shardManager->getShardsOf('M_store_id');
        $this->assertNotEmpty($shards);
    }

    public function testCreateShardDispatcher()
    {
        $shardManager = new ShardManager($this->config, $this->connManager);
        $dispatcher = $shardManager->createShardDispatcherOf('M_store_id');
        $this->assertNotNull($dispatcher);
        return $dispatcher;
    }

    /**
     * @depends testCreateShardDispatcher
     */
    public function testDispatchRead($dispatcher)
    {
        $shard = $dispatcher->dispatch('3d221024-eafd-11e6-a53b-3c15c2cb5a5a');
        $this->assertInstanceOf('Maghead\\Sharding\\Shard', $shard);

        $repo = $shard->createRepo('StoreApp\\Model\\StoreRepo');
        $this->assertInstanceOf('Maghead\\Runtime\\BaseRepo', $repo);
        $this->assertInstanceOf('StoreApp\\Model\\StoreRepo', $repo);
    }

    /**
     * @depends testCreateShardDispatcher
     */
    public function testDispatchWrite($dispatcher)
    {
        $shard = $dispatcher->dispatch('3d221024-eafd-11e6-a53b-3c15c2cb5a5a');
        $this->assertInstanceOf('Maghead\\Sharding\\Shard', $shard);

        $repo = $shard->createRepo('StoreApp\\Model\\StoreRepo');
        $this->assertInstanceOf('Maghead\\Runtime\\BaseRepo', $repo);
        $this->assertInstanceOf('StoreApp\\Model\\StoreRepo', $repo);
        return $repo;
    }

    /**
     * @depends testDispatchWrite
     */
    public function testWriteRepo($repo)
    {
        $ret = $repo->create([ 'name' => 'My Store', 'code' => 'MS001' ]);
        $this->assertResultSuccess($ret);
    }

    public function testRequiredField()
    {
        $ret = Store::create([ 'name' => 'testapp2', 'code' => 'testapp2' ]);
        $this->assertResultSuccess($ret);
    }

    public function testCreateWithRequiredFieldNull()
    {
        $ret = Store::create([ 'name' => 'testapp', 'code' => null ]);
        $this->assertResultFail($ret);
    }

    public function testUpdateWithRequiredFieldNull()
    {
        $store = Store::createAndLoad([ 'name' => 'testapp', 'code' => 'testapp' ]);
        $this->assertNotFalse($store);

        $ret = $store->update([ 'name' => 'testapp', 'code' => null ]);
        $this->assertResultFail($ret);

        $ret = $store->update([ 'name' => 'testapp 2' ]);
        $this->assertResultSuccess($ret);
        $this->assertEquals('testapp 2', $store->name);
    }
}