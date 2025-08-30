<?php
/**
 * Unified Status Badge Component
 * 
 * Usage: include this file and call getStatusBadge($status, $type)
 * 
 * @param string $status - The status value
 * @param string $type - 'shipment', 'order', or 'tracking'
 * @return string - HTML badge
 */

function getStatusBadge($status, $type = 'shipment') {
    $status_map = [
        'shipment' => [
            'pending' => ['class' => 'bg-secondary', 'text' => 'Pending'],
            'assigned' => ['class' => 'bg-info', 'text' => 'Assigned'],
            'in_transit' => ['class' => 'bg-primary', 'text' => 'In Transit'],
            'out_for_delivery' => ['class' => 'bg-warning', 'text' => 'Out for Delivery'],
            'delivered' => ['class' => 'bg-success', 'text' => 'Delivered'],
            'failed' => ['class' => 'bg-danger', 'text' => 'Failed']
        ],
        'order' => [
            'pending' => ['class' => 'bg-secondary', 'text' => 'Pending'],
            'confirmed' => ['class' => 'bg-info', 'text' => 'Confirmed'],
            'completed' => ['class' => 'bg-success', 'text' => 'Completed'],
            'cancelled' => ['class' => 'bg-danger', 'text' => 'Cancelled']
        ],
        'tracking' => [
            'in_transit' => ['class' => 'bg-primary', 'text' => 'In Transit'],
            'out_for_delivery' => ['class' => 'bg-warning', 'text' => 'Out for Delivery'],
            'delivered' => ['class' => 'bg-success', 'text' => 'Delivered'],
            'failed' => ['class' => 'bg-danger', 'text' => 'Failed']
        ]
    ];
    
    $config = $status_map[$type][$status] ?? ['class' => 'bg-secondary', 'text' => ucfirst(str_replace('_', ' ', $status))];
    
    return '<span class="badge ' . $config['class'] . '">' . $config['text'] . '</span>';
}

function getStatusFlow($type = 'shipment') {
    $flows = [
        'shipment' => [
            'pending' => 'assigned',
            'assigned' => 'in_transit', 
            'in_transit' => 'out_for_delivery',
            'out_for_delivery' => 'delivered',
            'delivered' => null,
            'failed' => null
        ],
        'order' => [
            'pending' => 'confirmed',
            'confirmed' => 'completed',
            'completed' => null,
            'cancelled' => null
        ]
    ];
    
    return $flows[$type] ?? [];
}

function getNextStatus($current_status, $type = 'shipment') {
    $flow = getStatusFlow($type);
    return $flow[$current_status] ?? null;
}

function getAvailableStatuses($current_status, $type = 'shipment') {
    $flow = getStatusFlow($type);
    $available = [];
    
    // Add next logical status
    $next = getNextStatus($current_status, $type);
    if ($next) {
        $available[] = $next;
    }
    
    // Add failure status (can happen at any stage)
    if ($type === 'shipment') {
        $available[] = 'failed';
    }
    
    // Add current status (for no change)
    $available[] = $current_status;
    
    return array_unique($available);
}
?>
