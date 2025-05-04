<?php

// config/attendance.php

return [

    /*
    |--------------------------------------------------------------------------
    | Office Location Coordinates
    |--------------------------------------------------------------------------
    |
    | Define the central coordinates of the office location used for
    | geofencing validation during attendance check-in/check-out.
    | These values are typically loaded from the .env file.
    |
    */
    // -6.1922601054901705
    // 106.90413450139457

    'office_latitude' => env('OFFICE_LATITUDE', -6.1922601054901705), // Default value jika .env tidak ada

    'office_longitude' => env('OFFICE_LONGITUDE', 106.90413450139457), // Default value
    // -6.189876335177134, 106.88774013995769
    /*
    |--------------------------------------------------------------------------
    | Allowed Attendance Radius
    |--------------------------------------------------------------------------
    |
    | Specify the maximum allowed distance (in meters) from the office
    | coordinates within which employees can check-in or check-out.
    |
    */

    'allowed_radius_meters' => env('OFFICE_RADIUS_METERS', 100), // Default 500 meter

];
