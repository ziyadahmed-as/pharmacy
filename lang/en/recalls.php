<?php

return [
    'title' => 'Product Recalls',
    'subtitle' => 'Manage batch recalls, quarantine affected stock, and trace sales',
    'unauthorized' => 'You are not authorized to perform recall operations.',
    'reason_required' => 'A valid reason for the recall is required.',
    'batches_required' => 'At least one batch must be selected for recall.',
    'no_valid_batches_found' => 'No valid batches found for the specified product.',
    'recall_already_closed' => 'This recall is already closed.',
    'severity_class_i' => 'Class I (High Risk)',
    'severity_class_ii' => 'Class II (Moderate Risk)',
    'severity_class_iii' => 'Class III (Low Risk)',
    'status_open' => 'Open',
    'status_closed' => 'Closed',
    'notify' => [
        'started' => 'Product recall :recall_no started for :product.',
    ],
];
