<?php

namespace JgDev\Cache\Backend;

class RedisTest extends \PHPUnit_Framework_TestCase
{

    public function testConstructor()
    {
        $object = new Redis();

        $this->assertInstanceOf('Fanid\Cache\Backend\Redis', $object);
        $this->assertEquals(array('server' => '127.0.0.1', 'port' => 6379, 'compress' => false), $object->getOptions());
    }

    public function testConstructorWithOptions()
    {
        $object = new Redis(array('server' => '127.0.0.2', 'port' => 6380));

        $this->assertInstanceOf('Fanid\Cache\Backend\Redis', $object);
        $this->assertEquals(array('server' => '127.0.0.2', 'port' => 6380, 'compress' => false), $object->getOptions());
    }

    public function testGetOptionsAndSetOption()
    {
        $object = new Redis(array('server' => '127.0.0.2', 'port' => 6380));

        $this->assertInstanceOf('Fanid\Cache\Backend\Redis', $object);
        $this->assertEquals(array('server' => '127.0.0.2', 'port' => 6380, 'compress' => false), $object->getOptions());
        $object->setOption('server', '127.0.0.1');
        $this->assertEquals(array('server' => '127.0.0.1', 'port' => 6380, 'compress' => false), $object->getOptions());
    }

    /**
     * @expectedException Zend_Cache_Exception
     * @expectedExceptionMessage Incorrect option
     */
    public function testGetOptionsAndSetOptionWithException()
    {
        $object = new Redis(array('server' => '127.0.0.2', 'port' => 6380));

        $object->setOption(array('foo'), 'bar');
    }

    public function testLoadWithNonExistingId()
    {
        $object = new Redis();

        $this->assertFalse($object->load('foo'));
    }

    public function testTestWithNonExistingId()
    {
        $object = new Redis();

        $this->assertFalse($object->test('foo'));
    }

    public function testSaveAndLoadWithExistingId()
    {

        $object = new Redis();
        $object->save('bar', 'foo');

        $this->assertEquals('bar', $object->load('foo'));
        $object->remove('foo');
    }

    public function testSaveAndLoadWithExistingIdAndTagAsString()
    {

        $object = new Redis();
        $object->save('bar', 'foo', 'tagbar');

        $this->assertEquals('bar', $object->load('foo'));
        $object->remove('foo');
    }

    public function testSaveAndLoadWithExistingIdAndTag()
    {
        $object = new Redis();
        $object->save('datafoo', 'idbar', array('tagbaz'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, 'tagbaz');
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTagAndLifetime()
    {
        $object = new Redis();
        $object->save('datafoo', 'idbar', array('tagbaz'), 0);

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, 'tagbaz');
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz', 'tagtab'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('tagtab'));
        $this->assertFalse($object->load('idbar'));
    }

    public function testRemoveEntryWithTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz', 'tagtab'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->remove('idbar');
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTagsAndCleanWithMatchingAnyTagAndOneTagGiven()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz', 'tagtab'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, array('tagtab'));
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTagsAndCleanWithMatchingAnyTagAllTagsGiven()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz', 'tagtab'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, array('tagtab', 'tagbaz'));
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTagsAndCleanWithNotMatchingTag()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, array('foo1'));
        $this->assertFalse($object->load('idbar'));
    }

    public function testSaveAndLoadWithExistingIdAndTagsAndCleanWithNotMatchingTagWithOneCorrectTag()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);
        $object->save('datafoo', 'idbar', array('tagbaz'));

        $this->assertEquals('datafoo', $object->load('idbar'));

        $object->clean(\Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, array('foo1', 'tagbaz'));
        $this->assertEquals('datafoo', $object->load('idbar'));
    }

    public function testMultipleSetsAndLoadWithTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo1', 'idbar1', array('tagbaz1'));
        $object->save('datafoo2', 'idbar2', array('tagbaz2'));
        $object->save('datafoo3', 'idbar3', array('tagbaz3'));

        $this->assertEquals('datafoo1', $object->load('idbar1'));
        $this->assertEquals('datafoo2', $object->load('idbar2'));
        $this->assertEquals('datafoo3', $object->load('idbar3'));

        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('tagbaz3'));

        $this->assertEquals('datafoo1', $object->load('idbar1'));
        $this->assertEquals('datafoo2', $object->load('idbar2'));

        $this->assertFalse($object->load('idbar3'));
        $this->assertFalse($object->load('idbar3a'));
        $this->assertFalse($object->load('idbar3b'));
        $this->assertFalse($object->load('idbar3c'));
    }

    public function testMultipleSetsAndLoadWithTagsAndCleaningTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo3', 'idbar3', array('tagbaz3'));
        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3', 'tagbaz4'));

        $object->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('tagbaz4'));

        $this->assertEquals('datafoo3', $object->load('idbar3'));
        $this->assertEquals('datafoo3a', $object->load('idbar3a'));
        $this->assertEquals('datafoo3b', $object->load('idbar3b'));
        $this->assertFalse($object->load('idbar3c'));
    }

    public function testGetIdsNotMatchingTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo3', 'idbar3', array('tagbaz3'));
        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3', 'tagbaz4'));

        $this->assertCount(3, $object->getIdsNotMatchingTags(array('tagbaz4')));
        $this->assertCount(0, $object->getIdsNotMatchingTags(array('tagbaz3')));
        $this->assertCount(4, $object->getIdsNotMatchingTags(array('foo')));
    }

    public function testGetIdsMatchingTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo3', 'idbar3', array('tagbaz3'));
        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3', 'tagbaz4'));

        $this->assertCount(1, $object->getIdsMatchingTags(array('tagbaz4')));
        $this->assertCount(4, $object->getIdsMatchingTags(array('tagbaz3')));
        $this->assertCount(1, $object->getIdsMatchingTags(array('tagbaz3', 'tagbaz4')));
        $this->assertCount(0, $object->getIdsMatchingTags(array('foo')));
        $this->assertCount(0, $object->getIdsMatchingTags(array('foo', 'tagbaz4')));
    }

    public function testGetIdsMatchingAnyTags()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo3', 'idbar3', array('tagbaz3'));
        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3', 'tagbaz4'));

        $this->assertCount(1, $object->getIdsMatchingAnyTags(array('tagbaz4')));
        $this->assertCount(4, $object->getIdsMatchingAnyTags(array('tagbaz3')));
        $this->assertCount(4, $object->getIdsMatchingAnyTags(array('tagbaz3', 'tagbaz4')));
        $this->assertCount(0, $object->getIdsMatchingAnyTags(array('foo')));
        $this->assertCount(1, $object->getIdsMatchingAnyTags(array('foo', 'tagbaz4')));
    }

    public function testGetMetadatas()
    {
        $object = new Redis();
        $object->clean(\Zend_Cache::CLEANING_MODE_ALL);

        $object->save('datafoo3', 'idbar3', array('tagbaz3'));
        $object->save('datafoo3a', 'idbar3a', array('tagbaz3'));
        $object->save('datafoo3b', 'idbar3b', array('tagbaz3'));
        $object->save('datafoo3c', 'idbar3c', array('tagbaz3', 'tagbaz4'));

        $metadatas = $object->getMetadatas('idbar3c');

        $this->assertArrayHasKey('expire', $metadatas);
        $this->assertEquals('3600', $metadatas['expire']);

        $this->assertArrayHasKey('tags', $metadatas);
        $this->assertEquals(array('tagbaz3', 'tagbaz4'), $metadatas['tags']);

        $this->assertArrayHasKey('mtime', $metadatas);
    }

}
