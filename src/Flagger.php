<?php


namespace Flagger;

use FFI;
use RuntimeException;

function nativeResponseParser($response)
{
    $hash = json_decode(FFI::string($response), true);
    if (array_key_exists('error', $hash)) {
        throw new RuntimeException($hash['error']);
    }
    return $hash;
}


final class Flagger
{
    private const SDK_NAME = 'php';
    private const SDK_VERSION = '3.0.0';
    private static FFI $ffi;

    /**
     * init method gets FlaggerConfiguration, establishes and maintains SSE connections and initializes Ingester
     * init must be called only once, at the start of your application.
     * Your program must wait for init to finish before using any other Flagger methods
     *
     * @param $config
     */
    public static function init($config)
    {
        $config['sdkName'] = self::SDK_NAME;
        $config['sdkVersion'] = self::SDK_VERSION;
        Flagger::$ffi->Init(json_encode($config));
    }

    /**
     * Explicitly notifies Airdeploy about an Entity
     *
     * @param $entity
     */
    public static function publish($entity)
    {
        $input = ['entity' => $entity];
        nativeResponseParser(Flagger::$ffi->Publish(json_encode($input)));
    }

    /**
     * Simple event tracking API. Entity is an optional parameter if it was set before.
     *
     * @param $eventName
     * @param $eventProperties
     * @param null $entity
     */
    public static function track($eventName, $eventProperties, $entity = null)
    {
        $input = ['event' => ['name' => $eventName, 'eventProperties' => $eventProperties, 'entity' => $entity]];
        nativeResponseParser(Flagger::$ffi->Track(json_encode($input)));
    }

    /**
     * Stores an entity in Flagger, which allows omission of entity in other API methods.
     * If there is no entity provided to Flagger at all:
     * - flag functions always resolve with the default variation
     * - track method doesn't record an event
     *
     * @param null $entity
     */
    public static function setEntity($entity = null)
    {
        $input = ['entity' => $entity];
        nativeResponseParser(Flagger::$ffi->SetEntity(json_encode($input)));
    }

    /**
     * Determines if flag is enabled for entity.
     *
     * @param string $codename
     * @param null $entity
     * @return bool
     */
    public static function isEnabled(string $codename, $entity = null): bool
    {
        $input = ['codename' => $codename, 'entity' => $entity];
        return nativeResponseParser(Flagger::$ffi->FlagIsEnabled(json_encode($input)))['data'];
    }

    /**
     * Determines if entity is within the targeted subpopulations.
     *
     * @param string $codename
     * @param null $entity
     * @return bool
     */
    public static function isSampled(string $codename, $entity = null): bool
    {
        $input = ['codename' => $codename, 'entity' => $entity];
        return nativeResponseParser(Flagger::$ffi->FlagIsSampled(json_encode($input)))['data'];
    }

    /**
     * Returns the variation assigned to the entity in a multivariate flag.
     *
     * @param string $codename
     * @param null $entity
     * @return string
     */
    public static function getVariation(string $codename, $entity = null): string
    {
        $input = ['codename' => $codename, 'entity' => $entity];
        return nativeResponseParser(Flagger::$ffi->FlagGetVariation(json_encode($input)))['data'];
    }

    /**
     * Returns the payload associated with the treatment assigned to the entity.
     *
     * @param string $codename
     * @param null $entity
     * @return mixed
     */
    public static function getPayload(string $codename, $entity = null)
    {
        $input = ['codename' => $codename, 'entity' => $entity];
        return nativeResponseParser(Flagger::$ffi->FlagGetPayload(json_encode($input)))['data'];
    }

    /**
     * Ingests data(if any), stop ingester and closes SSE connection. shutdown waits to finish current ingestion
     * request, but no longer than a timeoutMillis.
     * returns true if closed by timeout.
     *
     * @param int $timeoutMS
     * @return bool
     */
    public static function shutdown(int $timeoutMS): bool
    {
        $input = ['timeout' => $timeoutMS];
        return nativeResponseParser(Flagger::$ffi->Shutdown(json_encode($input)))['data'];
    }

    private static function load()
    {
        $library = 'flagger';
        $arc = 8 * PHP_INT_SIZE == 64 ? 'amd64' : '386';

        if (PHP_OS_FAMILY == 'Linux') {
            $library = 'lib' . $library . '-' . $arc . '.so';
        } else if (PHP_OS_FAMILY == 'Windows') {
            $library = $library . '-' . $arc . '.dll';
        } else if (PHP_OS_FAMILY == 'Darwin') {
            $library = 'lib' . $library . '.dylib';
        } else {
            // 'BSD, Solaris or Unknown'
            throw new RuntimeException('Unsupported platform: ' . PHP_OS_FAMILY);
        }

        Flagger::$ffi = FFI::cdef("
char* Init(char*  p0);
char* Publish(char*  p0);
char* Track(char*  p0);
char* SetEntity(char*  p0);
char* FlagIsEnabled(char*  p0);
char* FlagIsSampled(char*  p0);
char* FlagGetVariation(char*  p0);
char* FlagGetPayload(char*  p0);
char* Shutdown(char*  p0);
    ",
            __DIR__ . DIRECTORY_SEPARATOR . $library);
    }

}

(static function () {
    static::load();
})->bindTo(null, Flagger::class)();