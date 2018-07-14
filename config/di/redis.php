<?php declare(strict_types=1);

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

return [
    Client::class => \DI\factory(function (LoopInterface $loop, string $dsn) {
        return new class($loop, $dsn) implements Client {
            use EventEmitterTrait;

            /**
             * @var Client
             */
            private $redis;

            /**
             * @var SplQueue
             */
            private $callQueue;

            public function __construct(LoopInterface $loop, string $dsn)
            {
                $this->callQueue = new \SplQueue();
                (new Factory($loop))->createClient($dsn)->done(function (Client $client) {
                    $this->redis = $client;

                    while ($this->callQueue->count() > 0) {
                        $call = $this->callQueue->dequeue();
                        $name = $call['name'];
                        $args = $call['args'];
                        $call['deferred']->resolve($this->redis->$name(...$args));
                    }
                });
            }

            public function __call($name, $args)
            {
                if ($this->redis instanceof Client) {
                    return $this->redis->$name(...$args);
                }

                $deferred = new Deferred();

                $this->callQueue->enqueue([
                    'deferred' => $deferred,
                    'name' => $name,
                    'args' => $args,
                ]);

                return $deferred->promise();
            }

            public function isBusy()
            {
                if ($this->redis instanceof Client) {
                    return $this->redis->isBusy();
                }

                return count($this->callQueue) > 1;
            }

            public function end()
            {
                // TODO: Implement end() method.
            }

            public function close()
            {
                // TODO: Implement close() method.
            }
        };
    })
    ->parameter('dsn', \DI\get('config.redis.dsn')),
];
