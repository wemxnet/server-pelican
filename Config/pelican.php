<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    |
    | Each location has a display name and a list of Pelican node ids that
    | belong to it. When a location is selected for an order, the server is
    | deployed to the first node in that list with room and a free allocation.
    |
    | You can also override this file by creating config/server-pelican.php
    | in the root of your WemX installation.
    |
    */
    'locations' => [

        'germany' => [
            'name' => 'Germany', // display name
            'nodes' => [
                1,
                // add more node ids here (2, 3, 4, ...)
            ],
        ],

        // add more locations here
    ],
];
