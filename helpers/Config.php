<?php
/**
 * Created by Pavlo Onysko.
 * Date: 13/10/16
 */

namespace helpers;

class Config
{
    /** @var bool Error log flag */
    public $errorLog;

    /** @var bool Redirect log flag */
    public $redirectLog;

    /** @var bool Flag for parse/not external links */
    public $internalOnly;

    /** @var int Count of parallel cUrl descriptors */
    public $streamCount;

    /** @var array Collection of excluded url parts */
    public $excludeList;

    /**
     * Check config file validity.
     *
     * @param $data array Configuration data
     * @return bool Valid status
     */
    public static function isValid($data)
    {
        return is_bool($data['errorLog']) && is_bool($data['redirectLog']) && is_bool($data['internalOnly']) && is_integer($data['streamCount']) && is_array($data['excludeList']);
    }

    /**
     * Create new config from basic config.json.
     *
     * @return Config
     */
    public static function fromJson()
    {
        // Parse configuration file
        $config = json_decode(file_get_contents('config.json'), true);

        if (self::isValid($config)) {
            $self = new Config();
            $self->errorLog     = $config['errorLog'];
            $self->redirectLog  = $config['redirectLog'];
            $self->internalOnly = $config['internalOnly'];
            $self->streamCount  = $config['streamCount'];
            $self->excludeList  = $config['excludeList'];
            return $self;
        } else {
            die('Please provide valid config.json');
        }
    }
}
