<?php

return [
    'disk' => env('IMAGIC_DISK', env('NOVA_STORAGE_DISK', 'public')),
    'directory' => env('IMAGIC_DIRECTORY', 'imagic'),
    'visibility' => env('IMAGIC_VISIBILITY', 'private'),

    'field' => [
        'visibility' => env('IMAGIC_FIELD_VISIBILITY', env('IMAGIC_VISIBILITY', 'private')),
        'media_library' => true,
        'live_crop' => false,
        'crop_aspect_ratio' => null,
        'max_files' => 20,
    ],

    'uploads' => [
        'max_file_size' => 12 * 1024,
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ],
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        'max_width' => 12000,
        'max_height' => 12000,
        'max_pixels' => 40_000_000,
        'max_files' => 20,
        'orientate' => true,
        'strip_metadata' => true,
    ],

    'transform' => [
        'driver' => env('IMAGIC_DRIVER', 'gd'),
        'format' => env('IMAGIC_FORMAT', 'webp'),
        'quality' => 88,
    ],

    'thumbnail' => [
        'width' => 480,
        'height' => 320,
        'quality' => 80,
    ],

    'media_library' => [
        'enabled' => true,
        'api_path' => 'nova-vendor/imagic',
        'page_path' => 'imagic-media',
        'show_in_menu' => true,
        'menu_label' => 'Media Library',
        'authorization_gate' => null,
        'per_page' => 24,
        'max_per_page' => 100,
        'rate_limit' => 120,
        'model' => Ayvazyan10\Imagic\Models\MediaAsset::class,
        'folder_model' => Ayvazyan10\Imagic\Models\MediaFolder::class,
    ],
];
