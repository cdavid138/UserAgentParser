<?php
namespace UserAgentParser\Provider;

use UserAgentParser\Exception;
use UserAgentParser\Model;
use WhichBrowser\Parser as WhichBrowserParser;

class WhichBrowser extends AbstractProvider
{
    protected $detectionCapabilities = [

        'browser' => [
            'name'    => true,
            'version' => true,
        ],

        'renderingEngine' => [
            'name'    => true,
            'version' => true,
        ],

        'operatingSystem' => [
            'name'    => true,
            'version' => true,
        ],

        'device' => [
            'model'    => true,
            'brand'    => true,
            'type'     => true,
            'isMobile' => true,
            'isTouch'  => false,
        ],

        'bot' => [
            'isBot' => true,
            'name'  => true,
            'type'  => false,
        ],
    ];

    /**
     * Used for unitTests mocking
     *
     * @var WhichBrowserParser
     */
    private $parser;

    public function __construct()
    {
        if (! class_exists('WhichBrowser\Parser', true)) {
            throw new Exception\PackageNotLoaded('You need to install ' . $this->getComposerPackageName() . ' to use this provider');
        }
    }

    public function getName()
    {
        return 'WhichBrowser';
    }

    public function getComposerPackageName()
    {
        return 'whichbrowser/parser';
    }

    /**
     *
     * @param  array              $headers
     * @return WhichBrowserParser
     */
    public function getParser(array $headers)
    {
        if ($this->parser !== null) {
            return $this->parser;
        }

        return new WhichBrowserParser($headers);
    }

    /**
     *
     * @param Model\Bot                   $bot
     * @param \WhichBrowser\Model\Browser $browserRaw
     */
    private function hydrateBot(Model\Bot $bot, \WhichBrowser\Model\Browser $browserRaw)
    {
        $bot->setIsBot(true);

        if ($this->isRealResult($browserRaw->getName()) === true) {
            $bot->setName($browserRaw->getName());
        }
    }

    /**
     *
     * @param Model\Browser               $browser
     * @param \WhichBrowser\Model\Browser $browserRaw
     */
    private function hydrateBrowser(Model\Browser $browser, \WhichBrowser\Model\Browser $browserRaw)
    {
        if ($this->isRealResult($browserRaw->getName()) === true) {
            $browser->setName($browserRaw->getName());

            if ($this->isRealResult($browserRaw->getVersion()) === true) {
                $browser->getVersion()->setComplete($browserRaw->getVersion());
            }

            return;
        }

        if (isset($browserRaw->using) && $browserRaw->using instanceof \WhichBrowser\Model\Using) {
            /* @var $usingRaw \WhichBrowser\Model\Using */
            $usingRaw = $browserRaw->using;

            if ($this->isRealResult($usingRaw->getName()) === true) {
                $browser->setName($usingRaw->getName());

                if ($this->isRealResult($usingRaw->getVersion()) === true) {
                    $browser->getVersion()->setComplete($usingRaw->getVersion());
                }
            }
        }
    }

    /**
     *
     * @param Model\RenderingEngine      $engine
     * @param \WhichBrowser\Model\Engine $engineRaw
     */
    private function hydrateRenderingEngine(Model\RenderingEngine $engine, \WhichBrowser\Model\Engine $engineRaw)
    {
        if ($this->isRealResult($engineRaw->getName()) === true) {
            $engine->setName($engineRaw->getName());
        }

        if ($this->isRealResult($engineRaw->getVersion()) === true) {
            $engine->getVersion()->setComplete($engineRaw->getVersion());
        }
    }

    /**
     *
     * @param Model\OperatingSystem  $os
     * @param \WhichBrowser\Model\Os $osRaw
     */
    private function hydrateOperatingSystem(Model\OperatingSystem $os, \WhichBrowser\Model\Os $osRaw)
    {
        if ($this->isRealResult($osRaw->getName()) === true) {
            $os->setName($osRaw->getName());
        }

        if ($this->isRealResult($osRaw->getVersion()) === true) {
            $os->getVersion()->setComplete($osRaw->getVersion());
        }
    }

    /**
     *
     * @param Model\Device               $device
     * @param \WhichBrowser\Model\Device $deviceRaw
     * @param WhichBrowserParser         $parser
     */
    private function hydrateDevice(Model\Device $device, \WhichBrowser\Model\Device $deviceRaw, WhichBrowserParser $parser)
    {
        if ($this->isRealResult($deviceRaw->getModel()) === true) {
            $device->setModel($deviceRaw->getModel());
        }

        if ($this->isRealResult($deviceRaw->getManufacturer()) === true) {
            $device->setBrand($deviceRaw->getManufacturer());
        }

        if ($this->isRealResult($parser->getType()) === true) {
            $device->setType($parser->getType());
        }

        if ($parser->isType('mobile', 'tablet', 'ereader', 'media', 'watch', 'camera', 'gaming:portable') === true) {
            $device->setIsMobile(true);
        }
    }

    public function parse($userAgent, array $headers = [])
    {
        $headers['User-Agent'] = $userAgent;

        $parser = $this->getParser($headers);

        /*
         * No result found?
         */
        if ($parser->isDetected() !== true) {
            throw new Exception\NoResultFoundException('No result found for user agent: ' . $userAgent);
        }

        /*
         * Hydrate the model
         */
        $result = new Model\UserAgent();
        $result->setProviderResultRaw($parser->toArray());

        /*
         * Bot detection
         */
        if ($parser->getType() === 'bot') {
            $this->hydrateBot($result->getBot(), $parser->browser);

            return $result;
        }

        /*
         * hydrate the result
         */
        $this->hydrateBrowser($result->getBrowser(), $parser->browser);
        $this->hydrateRenderingEngine($result->getRenderingEngine(), $parser->engine);
        $this->hydrateOperatingSystem($result->getOperatingSystem(), $parser->os);
        $this->hydrateDevice($result->getDevice(), $parser->device, $parser);

        return $result;
    }
}
