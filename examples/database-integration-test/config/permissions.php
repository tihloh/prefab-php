<?php

/**
 * Application permission template.
 *
 * The permission key is the stable developer-facing identifier used in code.
 * The name and description are intended for management UIs and human logs.
 * The default value is used only when neither the user nor any of the user's
 * groups provides an explicit override.
 */
return [
    'documents.view' => [
        'name' => 'View Documents',
        'description' => 'Allows the user to view documents.',
        'default' => true,
    ],

    'documents.approve' => [
        'name' => 'Approve Documents',
        'description' => 'Allows the user to approve documents.',
        'default' => false,
    ],

    'documents.delete' => [
        'name' => 'Delete Documents',
        'description' => 'Allows the user to delete documents.',
        'default' => false,
    ],
];
