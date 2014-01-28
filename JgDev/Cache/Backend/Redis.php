<?php

namespace JgDev\Cache\Backend;

class Redis extends \Zend_Cache_Backend implements \Zend_Cache_Backend_ExtendedInterface
{
    const SET_IDS = 'jg:all_ids';
    const SET_TAGS = 'jg:all_tags';

    const PREFIX_KEY = 'jg:k:';
    const PREFIX_TAG_IDS = 'jg:ti:';

    const PREFIX_TAGS = 'jg:tag:';
    const PREFIX_ID_TAGS = 'jg:id_tags:';

    /**
     * @var array
     */
    protected $options = array(
        'server' => '127.0.0.1',
        'port' => '6379',
        'compress' => false
    );

    /**
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        if (!extension_loaded('redis')) {
           \Zend_Cache::throwException('The redis extension must be loaded for using this backend !');
        }

        $this->options = $options + $this->options;

        $this->_redis = new \Redis();
        $this->_redis->connect($this->options['server'], $this->options['port']);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function setOption($name, $value)
    {
        if (!is_string($name)) {
            \Zend_Cache::throwException("Incorrect option");
        }
        $name = strtolower($name);
        if (array_key_exists($name, $this->options)) {
            $this->options[$name] = $value;
        }
    }

    /**
     * @param string $id
     * @param bool $doNotTestCacheValidity
     *
     * @return \false|string
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        if ($this->options['compress'] == true) {
            return $this->uncompressData($this->_redis->get(self::PREFIX_KEY . $id));
        }
        return $this->_redis->get(self::PREFIX_KEY . $id);

    }

    /**
     * @param string $id
     *
     * @return \false|string
     */
    public function test($id)
    {
        return $this->_redis->get(self::PREFIX_KEY . $id);
    }

    /**
     * @param string $data
     * @param string $id
     * @param array $tags
     * @param bool $specificLifetime
     *
     * @return bool
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
        if(!is_array($tags)) {
            $tags = array($tags);
        }

        $lifetime = $this->getLifetime($specificLifetime);

        if ($this->options['compress'] == true) {
            $data = $this->compressData($data);
        }
        if ($lifetime) {
            $result = $this->_redis->setex(self::PREFIX_KEY . $id, $lifetime, $data);
        } else {
            $result = $this->_redis->set(self::PREFIX_KEY . $id, $data);
        }

        if (count($tags) > 0) {
            foreach($tags as $tag)
            {
                $this->_redis->sAdd(self::SET_TAGS, $tag);
                $this->_redis->sAdd(self::PREFIX_TAG_IDS . $tag, $id);
                $this->_redis->sAdd(self::PREFIX_ID_TAGS . $id, $tag);
            }
        }

        $this->_redis->sAdd(self::SET_IDS, $id);

        return $result;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function compressData($data)
    {
        return lzf_compress($data);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    private function uncompressData($data)
    {
        if ($data == false) {
            return $data;
        }
        return lzf_decompress($data);
    }

    /**
     * @param $id
     */
    protected function _remove($id)
    {
        $this->_redis->delete( self::PREFIX_KEY . $id );
        $this->_redis->sRemove( self::SET_IDS, $id );

        $tags = $this->_redis->sUnion(self::PREFIX_TAG_IDS . $id);
        foreach($tags as $tag)
        {
            $this->_redis->sRemove(self::PREFIX_TAG_IDS . $tag, $id);
        }
    }

    /**
     * @param string $id
     *
     * @return bool|void
     */
    public function remove($id)
    {
        return $this->_remove($id);
    }

    /**
     * @param string $mode
     * @param array $tags
     *
     * @return bool
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        if( $tags && ! is_array($tags)) {
            $tags = array($tags);
        }

        if($mode == \Zend_Cache::CLEANING_MODE_ALL) {
            return $this->_redis->flushDb();
        }

        if($mode == \Zend_Cache::CLEANING_MODE_OLD) {
            \Zend_Cache::throwException('Cleaning of old not supported');
            return false;
        }

        if(!count($tags)) {
            return false;
        }

        $result = true;

        switch ($mode)
        {
            case \Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                $this->_removeByMatchingTags($tags);
                break;
            case \Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                $this->_removeByNotMatchingTags($tags);
                break;
            case \Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                $this->_removeByMatchingAnyTags($tags);
                break;
            default:
                \Zend_Cache::throwException('Invalid mode for clean() method: '.$mode);
        }
        return (bool) $result;
    }

    /**
     * @param array $tags
     */
    protected function _removeByMatchingTags($tags)
    {
        $ids = $this->getIdsMatchingTags($tags);
        if($ids)
        {
            $this->_redis->pipeline()->multi();
            $this->_redis->del( $this->_preprocessIds($ids));
            $this->_redis->sRem( self::SET_IDS, $ids);
            $this->_redis->exec();
        }
    }

    /**
     * @param array $tags
     */
    protected function _removeByNotMatchingTags($tags)
    {
        $ids = $this->getIdsNotMatchingTags($tags);
        if($ids)
        {
            $this->_redis->pipeline()->multi();
            $this->_redis->del( $this->_preprocessIds($ids));
            $this->_redis->sRem( self::SET_IDS, $ids);
            $this->_redis->exec();
        }
    }

    /**
     * @param array $tags
     */
    protected function _removeByMatchingAnyTags($tags)
    {
        $ids = $this->getIdsMatchingAnyTags($tags);

        $this->_redis->pipeline()->multi();

        if($ids)
        {
            $this->_redis->del( $this->_preprocessIds($ids));
            $this->_redis->sRem( self::SET_IDS, $ids);
        }

        $this->_redis->del( $this->_preprocessTagIds($tags));
        $this->_redis->sRem( self::SET_TAGS, $tags);
        $this->_redis->exec();
    }

    /**
     * @return boolean
     */
    public function isAutomaticCleaningAvailable()
    {
        return false;
    }

    /**
     * @param array $directives
     * @throws\Zend_Cache_Exception
     */
    public function setDirectives($directives)
    {
        parent::setDirectives($directives);
        $lifetime = $this->getLifetime(false);
        if ($lifetime > 2592000) {
           \Zend_Cache::throwException('redis backend has a limit of 30 days (2592000 seconds) for the lifetime');
        }
    }

    /**
     * @return array
     */
    public function getIds()
    {
       \Zend_Cache::throwException("getIds()");
        return array();
    }

    /**
     * @return array
     */
    public function getTags()
    {
       \Zend_Cache::throwException('getTags');
        return array();
    }

    /**
     * @param $item
     * @param $index
     * @param $prefix
     */
    protected function _preprocess(&$item, $index, $prefix)
    {
        $item = $prefix . $item;
    }

    /**
     * @param $ids
     *
     * @return array
     */
    protected function _preprocessIds($ids)
    {
        array_walk($ids, array($this, '_preprocess'), self::PREFIX_KEY);
        return $ids;
    }

    /**
     * @param $tags
     * @return array
     */
    protected function _preprocessTagIds($tags)
    {
        array_walk($tags, array($this, '_preprocess'), self::PREFIX_TAG_IDS);
        return $tags;
    }

    /**
     * @param array $tags
     *
     * @return array
     */
    public function getIdsMatchingTags($tags = array())
    {
        if ($tags) {
            return (array) $this->_redis->sInter( $this->_preprocessTagIds($tags) );
        }
        return array();
    }

    /**
     * @param array $tags
     *
     * @return array|mixed
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        $tags = $this->_preprocessTagIds($tags);
        array_unshift($tags, self::SET_IDS);
        return call_user_func_array( array($this->_redis, 'sDiff'), $tags );
    }

    /**
     * @param array $tags
     *
     * @return array
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        if ($tags) {
            return (array) $this->_redis->sUnion( $this->_preprocessTagIds($tags));
        }
        return array();
    }

    public function getFillingPercentage()
    {
        \Zend_Cache::throwException("Filling percentage not supported by the Redis backend");
    }

    /**
     * @param string $id
     *
     * @return array|bool
     */
    public function getMetadatas($id)
    {
        $ttl = $this->_redis->ttl(self::PREFIX_KEY . $id);
        $mtime = time() - $ttl;

        if(!$ttl) return false;

        $tags = $this->_redis->sMembers(self::PREFIX_ID_TAGS . $id );

        return array(
            'expire' => $ttl,
            'tags' => $tags,
            'mtime' => $mtime,
        );
    }

    /**
     * @param string $id
     * @param int $extraLifetime
     * @return bool
     */
    public function touch($id, $extraLifetime)
    {
       \Zend_Cache::throwException("touch");
    }

    /**
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'automatic_cleaning' => false,
            'tags' => true,
            'expired_read' => false,
            'priority' => false,
            'infinite_lifetime' => false,
            'get_list' => true
        );
    }
}
