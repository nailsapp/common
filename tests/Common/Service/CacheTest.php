<?php

namespace Tests\Common\Service;

use Nails\Common\Exception;
use Nails\Common\Helper\Directory;
use Nails\Common\Resource\Cache\Item;
use Nails\Common\Service\Cache;
use PHPUnit\Framework\TestCase;

/**
 * Class CacheTest
 *
 * @package Tests\Common\Service
 */
class CacheTest extends TestCase
{
    /**
     * @var string
     */
    protected static $sDirPrivate;

    /**
     * @var string
     */
    protected static $sDirPublic;


    /**
     * @var Cache\Cache
     */
    protected static $oCachePrivate;

    /**
     * @var Cache\AccessibleByUrl
     */
    protected static $oCachePublic;

    /**
     * @var Cache
     */
    protected static $oCache;

    // --------------------------------------------------------------------------

    /**
     * @throws Exception\Directory\DirectoryDoesNotExistException
     * @throws Exception\Directory\DirectoryIsNotWritableException
     * @throws Exception\Directory\DirectoryNameException
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$sDirPrivate = Directory::tempdir();
        static::$sDirPublic  = Directory::tempdir();

        //  Place a existing-file file in the cache
        file_put_contents(static::$sDirPrivate . 'existing-file.txt', 'Some data');
        file_put_contents(static::$sDirPublic . 'existing-file.txt', 'Some data');

        static::$oCachePrivate = new Cache\Cache(
            static::$sDirPrivate
        );
        static::$oCachePublic  = new Cache\AccessibleByUrl(
            static::$sDirPublic
        );

        static::$oCache = new Cache(
            static::$oCachePrivate,
            static::$oCachePublic
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::getDir
     */
    public function testPrivateCacheDirIsValid()
    {
        $this->assertEquals(
            static::$sDirPrivate,
            static::$oCache->getDir()
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::write
     */
    public function testCanWriteToPrivateCache()
    {
        $sData = 'Some test data';
        $sKey  = 'cache.txt';

        $oItem = static::$oCache->write($sData, $sKey);

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals($sKey, $oItem->getKey());
        $this->assertEquals($sData, (string) $oItem);
        $this->assertFileExists(static::$sDirPrivate . $sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::write
     */
    public function testCanWriteToPrivateCacheWithoutKey()
    {
        $sData = 'Some test data';

        $oItem = static::$oCache->write($sData);

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals($sData, (string) $oItem);
        $this->assertFileExists(static::$sDirPrivate . $oItem->getKey());
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::read
     */
    public function testCanReadFromPrivateCache()
    {
        $oItem = static::$oCache->read('existing-file.txt');

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals('existing-file.txt', $oItem->getKey());
        $this->assertEquals(static::$sDirPrivate . 'existing-file.txt', $oItem->getPath());
        $this->assertEquals('Some data', (string) $oItem);
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::exists
     */
    public function testCheckValidItemExistsInPrivateCache()
    {
        $this->assertTrue(static::$oCache->exists('existing-file.txt'));
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::exists
     */
    public function testCheckInvalidItemExistsInPrivateCache()
    {
        $this->assertFalse(static::$oCache->exists('non-existing-file.txt'));
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::delete
     */
    public function testCanDeleteValidItemFromPrivateCache()
    {
        $this->assertFileExists(static::$sDirPrivate . 'existing-file.txt');
        $bResult = static::$oCache->delete('existing-file.txt');
        $this->assertTrue($bResult);
        $this->assertFileNotExists(static::$sDirPrivate . 'existing-file.txt');
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\Cache::delete
     */
    public function testCanDeleteInvalidItemFromPrivateCache()
    {
        $this->assertFileNotExists(static::$sDirPrivate . 'non-existing-file.txt');
        $bResult = static::$oCache->delete('non-existing-file.txt');
        $this->assertFalse($bResult);
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache::public
     */
    public function testPublicCacheIsAccessible()
    {
        $this->assertSame(
            static::$oCachePublic,
            static::$oCache->public()
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::getDir
     */
    public function testPublicCacheDirIsValid()
    {
        $this->assertEquals(
            static::$sDirPublic,
            static::$oCache->public()->getDir()
        );
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::write
     */
    public function testCanWriteToPublicCache()
    {
        $sData = 'Some test data';
        $sKey  = 'cache.txt';

        $oItem = static::$oCache->public()->write($sData, $sKey);

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals($sKey, $oItem->getKey());
        $this->assertEquals($sData, (string) $oItem);
        $this->assertFileExists(static::$sDirPublic . $sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::write
     */
    public function testCanWriteToPublicCacheWithoutKey()
    {
        $sData = 'Some test data';

        $oItem = static::$oCache->public()->write($sData);

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals($sData, (string) $oItem);
        $this->assertFileExists(static::$sDirPublic . $oItem->getKey());
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::read
     */
    public function testCanReadFromPublicCache()
    {
        $oItem = static::$oCache->public()->read('existing-file.txt');

        $this->assertInstanceOf(Item::class, $oItem);
        $this->assertEquals('existing-file.txt', $oItem->getKey());
        $this->assertEquals(static::$sDirPublic . 'existing-file.txt', $oItem->getPath());
        $this->assertEquals('Some data', (string) $oItem);
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::exists
     */
    public function testCheckValidItemExistsInPublicCache()
    {
        $this->assertTrue(static::$oCache->public()->exists('existing-file.txt'));
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::exists
     */
    public function testCheckInvalidItemExistsInPublicCache()
    {
        $this->assertFalse(static::$oCache->public()->exists('non-existing-file.txt'));
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::delete
     */
    public function testCanDeleteValidItemFromPublicCache()
    {
        $this->assertFileExists(static::$sDirPublic . 'existing-file.txt');
        static::$oCache->public()->delete('existing-file.txt');
        $this->assertFileNotExists(static::$sDirPublic . 'existing-file.txt');
    }

    // --------------------------------------------------------------------------

    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::delete
     */
    public function testCanDeleteInvalidItemFromPublicCache()
    {
        $this->assertFileNotExists(static::$sDirPublic . 'non-existing-file.txt');
        $bResult = static::$oCache->public()->delete('non-existing-file.txt');
        $this->assertFalse($bResult);
    }

    // --------------------------------------------------------------------------


    /**
     * @covers \Nails\Common\Service\Cache\AccessibleByUrl::getUrl
     */
    public function testPublicCacheReturnsValidUrl()
    {
        $this->assertEquals(
            BASE_URL . 'cache/public',
            static::$oCache->public()->getUrl()
        );
        $this->assertEquals(
            BASE_URL . 'cache/public/existing-file.txt',
            static::$oCache->public()->getUrl('existing-file.txt')
        );
    }
}