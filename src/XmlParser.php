<?php

namespace irpsv\xml;

class XmlParser
{
    public $offset = 0;
    public $limitNode = 1000;
    public $limitTimeSec = 15;
    public $readTypes = [
        \XMLReader::ELEMENT,
    ];

    protected $path;
    protected $callbacks = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function clearCallbacks()
    {
        $this->callbacks = [];
    }

    public function onNode(string $namespace, callable $callback)
    {
        $this->callbacks[$namespace] = $callback;
    }

    public function run()
    {
        $node = 0;
        $isReadAll = true;
        $isForceFinish = false;

        $limitNode = (int) $this->limitNode;
        $offset = (int) $this->offset;

        $startTime = time();
        $limitTimeSec = (int) $this->limitTimeSec;

        $reader = new \XMLReader();
        $namespace = [];

        $availablePrefix = [];
        foreach (array_keys($this->callbacks) as $key) {
            $prefix = [];
            $parts = explode('/', $key);
            foreach ($parts as $part) {
                $prefix[] = $part;
                $availablePrefix[] = join("/", $prefix);
            }
        }
        $availablePrefix = array_unique($availablePrefix);

        try {
            $reader->open($this->path);
            while($reader->read()) {
                if (in_array($reader->nodeType, $this->readTypes) === false) {
                    continue;
                }

                $name = $reader->name;
                $depth = $reader->depth;
                $namespace[$depth] = $name;
                array_splice($namespace, $depth+1);
                $namespaceStr = join("/", $namespace);

                if (in_array($namespaceStr, $availablePrefix) === false) {
                    $reader->next();
                    continue;
                }

                $callback = $this->callbacks[$namespaceStr] ?? null;
                if ($callback) {
                    $node++;
                    $timePassed = time() - $startTime;
                    if ($timePassed > $limitTimeSec) {
                        $isReadAll = false;
                        break;
                    }
                    else if ($offset > 0) {
                        $offset--;
                        $reader->next();
                        continue;
                    }
                    else if ($limitNode < 1) {
                        $isReadAll = false;
                        break;
                    }
                    else {
                        $limitNode--;
                    }

                    $ret = call_user_func($callback, $this, $reader);
                    if ($ret === false) {
                        $isForceFinish = true;
                        break;
                    }
                    $reader->next();
                }
            }
            return [$node, $isForceFinish, $isReadAll];
        }
        finally {
            $reader->close();
        }
    }
}
