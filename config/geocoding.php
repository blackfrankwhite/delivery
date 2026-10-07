<?php

return [
    // Photon supports search-as-you-type and returns GeoJSON [longitude, latitude].
    'url' => env('GEOCODING_URL', 'https://photon.komoot.io/api/'),
    'latitude' => (float) env('VITE_MAP_CENTER_LATITUDE', 41.7151),
    'longitude' => (float) env('VITE_MAP_CENTER_LONGITUDE', 44.8271),
];
