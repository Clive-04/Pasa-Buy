<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /** PayMongo API client (tests replace it with Services::injectMock('paymongo', ...)). */
    public static function paymongo(bool $getShared = true): \App\Libraries\PayMongoClient
    {
        if ($getShared) {
            return static::getSharedInstance('paymongo');
        }

        return new \App\Libraries\PayMongoClient(config('PayMongo'));
    }

    /** Xendit API client (tests replace it with Services::injectMock('xendit', ...)). */
    public static function xendit(bool $getShared = true): \App\Libraries\XenditClient
    {
        if ($getShared) {
            return static::getSharedInstance('xendit');
        }

        return new \App\Libraries\XenditClient(config('Xendit'));
    }

    /** TalkJS server client (tests replace it with Services::injectMock('talkjs', ...)). */
    public static function talkjs(bool $getShared = true): \App\Libraries\TalkJsClient
    {
        if ($getShared) {
            return static::getSharedInstance('talkjs');
        }

        return new \App\Libraries\TalkJsClient(config('TalkJS'));
    }

    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */
}
