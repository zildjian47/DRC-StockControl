<?php

return [
    ['group' => 'Monitor', 'items' => [
        ['label' => 'Dashboard',          'route' => 'dashboard',                'match' => 'dashboard',            'icon' => 'grid'],
    ]],
    ['group' => 'Scan QR & History', 'items' => [
        ['label' => 'Inbound',            'route' => 'inbounds.index',           'match' => 'inbounds.*',           'icon' => 'inb'],
        ['label' => 'Material Out',       'route' => 'material_outs.index',      'match' => 'material_outs.*',      'icon' => 'out'],
        ['label' => 'Material Return',    'route' => 'material_returns.index',   'match' => 'material_returns.*',   'icon' => 'undo'],
        ['label' => 'Production',         'route' => 'productions.index',        'match' => 'productions.*',        'icon' => 'factory'],
        ['label' => 'Delivery',           'route' => 'deliveries.index',         'match' => 'deliveries.*',         'icon' => 'truck'],
        ['label' => 'Return to Supplier', 'route' => 'return_to_suppliers.index','match' => 'return_to_suppliers.*','icon' => 'truck', 'flip' => true],
    ]],
    ['group' => 'Inventory', 'items' => [
        ['label' => 'Inventory Stock',    'route' => 'inventory_stocks.index',   'match' => 'inventory_stocks.*',   'icon' => 'box'],
        ['label' => 'Master Item',        'route' => 'items.index',              'match' => 'items.*',              'icon' => 'list'],
        ['label' => 'Lot Tracer',         'route' => 'lot_tracers.index',        'match' => 'lot_tracers.*',        'icon' => 'route'],
        ['label' => 'Stock Adjustment',   'route' => 'stock_adjustments.index',  'match' => 'stock_adjustments.*',  'icon' => 'sliders'],
    ]],
    ['group' => 'Quality & Delivery', 'items' => [
        ['label' => 'Form NG',            'route' => 'production_ngs.index',     'match' => 'production_ngs.*',     'icon' => 'alert'],
        ['label' => 'Delay Delivery',     'route' => 'problem_delays.index',     'match' => 'problem_delays.*',     'icon' => 'clock'],
    ]],
    ['group' => 'Master Data', 'items' => [
        ['label' => 'Companies',          'route' => 'companies.index',          'match' => 'companies.*',          'icon' => 'building'],
        ['label' => 'Manufacture Process','route' => 'processes.index',          'match' => 'processes.*',          'icon' => 'gear'],
        ['label' => 'BOM',                'route' => 'boms.index',               'match' => 'boms.*',               'icon' => 'layers'],
    ]],
];