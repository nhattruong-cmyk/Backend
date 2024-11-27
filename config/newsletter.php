<?php

return [

    /*
     * The driver to use to interact with MailChimp API.
     * You may use "log" or "null" to prevent calling the
     * API directly from your environment.
     */
    'driver' => env('NEWSLETTER_DRIVER', Spatie\Newsletter\Drivers\MailcoachDriver::class),

    /**
     * These arguments will be given to the driver.
     */
    'driver_arguments' => [
        'apiKey' => env('MAILCHIMP_APIKEY'),

        'endpoint' => env('NEWSLETTER_ENDPOINT'),
    ],

    /*
     * The list name to use when no list name is specified in a method.
     */
    'default_list_name' => 'subscribers',
    
    'lists' => [
        'subscribers' => [
            'id' => env('MAILCHIMP_LIST_ID'),
        ],
    ],
];
