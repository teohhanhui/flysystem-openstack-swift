<?php

use GuzzleHttp\Psr7\Stream;
use League\Flysystem\Config;
use Nimbusoft\Flysystem\OpenStack\SwiftAdapter;

class SwiftAdapterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->config = new Config([]);
        $this->container = Mockery::mock('OpenStack\ObjectStore\v1\Models\Container');
        $this->container->name = 'container-name';
        $this->object = Mockery::mock('OpenStack\ObjectStore\v1\Models\Object');
        $this->adapter = new SwiftAdapter($this->container);
    }

    public function testWriteAndUpdate()
    {
        foreach (['write', 'update'] as $method) {
            $this->container->shouldReceive('createObject')->once()->with([
                'name' => 'hello',
                'content' => 'world'
            ])->andReturn($this->object);

            $this->adapter->$method('hello', 'world', $this->config);
        }
    }

    public function testWriteAndUpdateStream()
    {
        foreach (['writeStream', 'updateStream'] as $method) {
            $stream = fopen('data://text/plain;base64,'.base64_encode('world'), 'r');
            $psrStream = new Stream($stream);

            $this->container->shouldReceive('createObject')->once()->with([
                'name' => 'hello',
                'stream' => $psrStream
            ])->andReturn($this->object);

            $this->adapter->$method('hello', $stream, $this->config);
        }
    }

    public function testRename()
    {
        $this->object->shouldReceive('retrieve')->once();
        $this->object->shouldReceive('copy')->once()->with([
            'destination' => '/container-name/world'
        ]);
        $this->object->shouldReceive('delete')->once();

        $this->container->shouldReceive('getObject')
            ->once()
            ->with('hello')
            ->andReturn($this->object);

        $this->adapter->rename('hello', 'world');
    }

    public function testDelete()
    {
        $this->object->shouldReceive('retrieve')->once();
        $this->object->shouldReceive('delete')->once();

        $this->container->shouldReceive('getObject')
            ->once()
            ->with('hello')
            ->andReturn($this->object);

        $this->adapter->delete('hello');
    }

    public function testDeleteDir()
    {
        $times = rand(1, 10);

        $generator = function() use ($times) {
            for ($i = 1; $i <= $times; $i++) {
                yield $this->object;
            }
        };

        $objects = $generator();

        $this->container->shouldReceive('listObjects')
            ->once()
            ->with([
                'prefix' => 'hello'
            ])
            ->andReturn($objects);

        $this->object->shouldReceive('delete')->times($times);

        $this->adapter->deleteDir('hello');
    }

    public function tearDown()
    {
        Mockery::close();
    }
}
