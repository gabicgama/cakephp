<?php
/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         1.2.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Datasource;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

class FakeConnection
{
    protected $_config = [];

    /**
     * Constructor.
     *
     * @param array $config configuration for connecting to database
     */
    public function __construct($config = [])
    {
        $this->_config = $config;
    }

    /**
     * Returns the set config
     *
     * @return array
     */
    public function config()
    {
        return $this->_config;
    }

    /**
     * Returns the set name
     *
     * @return string
     */
    public function configName()
    {
        if (empty($this->_config['name'])) {
            return '';
        }

        return $this->_config['name'];
    }
}

/**
 * ConnectionManager Test
 */
class ConnectionManagerTest extends TestCase
{

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        parent::tearDown();
        Plugin::unload();
        ConnectionManager::drop('test_variant');
        ConnectionManager::dropAlias('other_name');
    }

    /**
     * Data provider for valid config data sets.
     *
     * @return array
     */
    public static function configProvider()
    {
        return [
            'Array of data using classname key.' => [[
                'className' => __NAMESPACE__ . '\FakeConnection',
                'instance' => 'Sqlite',
                'database' => ':memory:',
            ]],
            'Direct instance' => [new FakeConnection],
        ];
    }

    /**
     * Test the various valid config() calls.
     *
     * @dataProvider configProvider
     * @return void
     */
    public function testConfigVariants($settings)
    {
        $this->assertNotContains('test_variant', ConnectionManager::configured(), 'test_variant config should not exist.');
        ConnectionManager::config('test_variant', $settings);

        $ds = ConnectionManager::get('test_variant');
        $this->assertInstanceOf(__NAMESPACE__ . '\FakeConnection', $ds);
        $this->assertContains('test_variant', ConnectionManager::configured());
    }

    /**
     * Test invalid classes cause exceptions
     *
     * @expectedException \Cake\Datasource\Exception\MissingDatasourceException
     */
    public function testConfigInvalidOptions()
    {
        ConnectionManager::config('test_variant', [
            'className' => 'Herp\Derp'
        ]);
        ConnectionManager::get('test_variant');
    }

    /**
     * Test for errors on duplicate config.
     *
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage Cannot reconfigure existing key "test_variant"
     * @return void
     */
    public function testConfigDuplicateConfig()
    {
        $settings = [
            'className' => __NAMESPACE__ . '\FakeConnection',
            'database' => ':memory:',
        ];
        ConnectionManager::config('test_variant', $settings);
        ConnectionManager::config('test_variant', $settings);
    }

    /**
     * Test get() failing on missing config.
     *
     * @expectedException \Cake\Core\Exception\Exception
     * @expectedExceptionMessage The datasource configuration "test_variant" was not found.
     * @return void
     */
    public function testGetFailOnMissingConfig()
    {
        ConnectionManager::get('test_variant');
    }

    /**
     * Test loading configured connections.
     *
     * @return void
     */
    public function testGet()
    {
        $config = ConnectionManager::config('test');
        $this->skipIf(empty($config), 'No test config, skipping');

        $ds = ConnectionManager::get('test');
        $this->assertSame($ds, ConnectionManager::get('test'));
        $this->assertInstanceOf('Cake\Database\Connection', $ds);
        $this->assertEquals('test', $ds->configName());
    }

    /**
     * Test loading connections without aliases
     *
     * @expectedException \Cake\Core\Exception\Exception
     * @expectedExceptionMessage The datasource configuration "other_name" was not found.
     * @return void
     */
    public function testGetNoAlias()
    {
        $config = ConnectionManager::config('test');
        $this->skipIf(empty($config), 'No test config, skipping');

        ConnectionManager::alias('test', 'other_name');
        ConnectionManager::get('other_name', false);
    }

    /**
     * Test that configured() finds configured sources.
     *
     * @return void
     */
    public function testConfigured()
    {
        ConnectionManager::config('test_variant', [
            'className' => __NAMESPACE__ . '\FakeConnection',
            'database' => ':memory:'
        ]);
        $results = ConnectionManager::configured();
        $this->assertContains('test_variant', $results);
    }

    /**
     * testGetPluginDataSource method
     *
     * @return void
     */
    public function testGetPluginDataSource()
    {
        Plugin::load('TestPlugin');
        $name = 'test_variant';
        $config = ['className' => 'TestPlugin.TestSource', 'foo' => 'bar'];
        ConnectionManager::config($name, $config);
        $connection = ConnectionManager::get($name);

        $this->assertInstanceOf('TestPlugin\Datasource\TestSource', $connection);
        unset($config['className']);
        $this->assertSame($config + ['name' => 'test_variant'], $connection->config());
    }

    /**
     * Tests that a connection configuration can be deleted in runtime
     *
     * @return void
     */
    public function testDrop()
    {
        ConnectionManager::config('test_variant', [
            'className' => __NAMESPACE__ . '\FakeConnection',
            'database' => ':memory:'
        ]);
        $result = ConnectionManager::configured();
        $this->assertContains('test_variant', $result);

        $this->assertTrue(ConnectionManager::drop('test_variant'));
        $result = ConnectionManager::configured();
        $this->assertNotContains('test_variant', $result);

        $this->assertFalse(ConnectionManager::drop('probably_does_not_exist'), 'Should return false on failure.');
    }

    /**
     * Test aliasing connections.
     *
     * @return void
     */
    public function testAlias()
    {
        ConnectionManager::config('test_variant', [
            'className' => __NAMESPACE__ . '\FakeConnection',
            'database' => ':memory:'
        ]);
        ConnectionManager::alias('test_variant', 'other_name');
        $result = ConnectionManager::get('test_variant');
        $this->assertSame($result, ConnectionManager::get('other_name'));
    }

    /**
     * Test alias() raises an error when aliasing an undefined connection.
     *
     * @expectedException \Cake\Datasource\Exception\MissingDatasourceConfigException
     * @return void
     */
    public function testAliasError()
    {
        $this->assertNotContains('test_kaboom', ConnectionManager::configured());
        ConnectionManager::alias('test_kaboom', 'other_name');
    }

    /**
     * provider for DSN strings.
     *
     * @return array
     */
    public function dsnProvider()
    {
        return [
            'no user' => [
                'mysql://localhost:3306/database',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Mysql',
                    'host' => 'localhost',
                    'database' => 'database',
                    'port' => 3306,
                    'scheme' => 'mysql',
                ]
            ],
            'subdomain host' => [
                'mysql://my.host-name.com:3306/database',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Mysql',
                    'host' => 'my.host-name.com',
                    'database' => 'database',
                    'port' => 3306,
                    'scheme' => 'mysql',
                ]
            ],
            'user & pass' => [
                'mysql://root:secret@localhost:3306/database?log=1',
                [
                    'scheme' => 'mysql',
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Mysql',
                    'host' => 'localhost',
                    'username' => 'root',
                    'password' => 'secret',
                    'port' => 3306,
                    'database' => 'database',
                    'log' => '1'
                ]
            ],
            'sqlite memory' => [
                'sqlite:///:memory:',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Sqlite',
                    'database' => ':memory:',
                    'scheme' => 'sqlite',
                ]
            ],
            'sqlite path' => [
                'sqlite:////absolute/path',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Sqlite',
                    'database' => '/absolute/path',
                    'scheme' => 'sqlite',
                ]
            ],
            'sqlite database query' => [
                'sqlite:///?database=:memory:',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Sqlite',
                    'database' => ':memory:',
                    'scheme' => 'sqlite',
                ]
            ],
            'sqlserver' => [
                'sqlserver://sa:Password12!@.\SQL2012SP1/cakephp?MultipleActiveResultSets=false',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Sqlserver',
                    'host' => '.\SQL2012SP1',
                    'MultipleActiveResultSets' => false,
                    'password' => 'Password12!',
                    'database' => 'cakephp',
                    'scheme' => 'sqlserver',
                    'username' => 'sa',
                ]
            ],
            'sqllocaldb' => [
                'sqlserver://username:password@(localdb)\.\DeptSharedLocalDB/database',
                [
                    'className' => 'Cake\Database\Connection',
                    'driver' => 'Cake\Database\Driver\Sqlserver',
                    'host' => '(localdb)\.\DeptSharedLocalDB',
                    'password' => 'password',
                    'database' => 'database',
                    'scheme' => 'sqlserver',
                    'username' => 'username',
                ]
            ],
            'classname query arg' => [
                'mysql://localhost/database?className=Custom\Driver',
                [
                    'className' => 'Cake\Database\Connection',
                    'database' => 'database',
                    'driver' => 'Custom\Driver',
                    'host' => 'localhost',
                    'scheme' => 'mysql',
                ]
            ],
            'classname and port' => [
                'mysql://localhost:3306/database?className=Custom\Driver',
                [
                    'className' => 'Cake\Database\Connection',
                    'database' => 'database',
                    'driver' => 'Custom\Driver',
                    'host' => 'localhost',
                    'scheme' => 'mysql',
                    'port' => 3306,
                ]
            ],
            'custom connection class' => [
                'Cake\Database\Connection://localhost:3306/database?driver=Cake\Database\Driver\Mysql',
                [
                    'className' => 'Cake\Database\Connection',
                    'database' => 'database',
                    'driver' => 'Cake\Database\Driver\Mysql',
                    'host' => 'localhost',
                    'scheme' => 'Cake\Database\Connection',
                    'port' => 3306,
                ]
            ],
            'complex password' => [
                'mysql://user:pas#][{}$%20@!@localhost:3306/database?log=1&quoteIdentifiers=1',
                [
                    'className' => 'Cake\Database\Connection',
                    'database' => 'database',
                    'driver' => 'Cake\Database\Driver\Mysql',
                    'host' => 'localhost',
                    'password' => 'pas#][{}$%20@!',
                    'port' => 3306,
                    'scheme' => 'mysql',
                    'username' => 'user',
                    'log' => 1,
                    'quoteIdentifiers' => 1,
                ]
            ]
        ];
    }

    /**
     * Test parseDsn method.
     *
     * @dataProvider dsnProvider
     * @return void
     */
    public function testParseDsn($dsn, $expected)
    {
        $result = ConnectionManager::parseDsn($dsn);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test parseDsn invalid.
     *
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage The DSN string 'bagof:nope' could not be parsed.
     * @return void
     */
    public function testParseDsnInvalid()
    {
        $result = ConnectionManager::parseDsn('bagof:nope');
    }

    /**
     * Tests that directly setting an instance in a config, will not return a different
     * instance later on
     *
     * @return void
     */
    public function testConfigWithObject()
    {
        $connection = new FakeConnection;
        ConnectionManager::config('test_variant', $connection);
        $this->assertSame($connection, ConnectionManager::get('test_variant'));
    }

    /**
     * Tests configuring an instance with a callable
     *
     * @return void
     */
    public function testConfigWithCallable()
    {
        $connection = new FakeConnection;
        $callable = function ($alias) use ($connection) {
            $this->assertEquals('test_variant', $alias);

            return $connection;
        };

        ConnectionManager::config('test_variant', $callable);
        $this->assertSame($connection, ConnectionManager::get('test_variant'));
    }

    /**
     * Tests that setting a config will also correctly set the name for the connection
     *
     * @return void
     */
    public function testSetConfigName()
    {
        //Set with explicit name
        ConnectionManager::config('test_variant', [
            'className' => __NAMESPACE__ . '\FakeConnection',
            'database' => ':memory:'
        ]);
        $result = ConnectionManager::get('test_variant');
        $this->assertSame('test_variant', $result->configName());

        ConnectionManager::drop('test_variant');
        ConnectionManager::config([
            'test_variant' => [
                'className' => __NAMESPACE__ . '\FakeConnection',
                'database' => ':memory:'
            ]
        ]);
        $result = ConnectionManager::get('test_variant');
        $this->assertSame('test_variant', $result->configName());
    }
}
