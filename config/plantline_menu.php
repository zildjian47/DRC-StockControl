<?php

return [
    ['group' => 'Monitor', 'items' => [
        ['label' => 'Dashboard',          'route' => 'dashboard',                 'match' => 'dashboard',             'icon' => 'grid',     'roles' => ['PPIC']],
    ]],
    ['group' => 'Scan QR & History', 'items' => [
        ['label' => 'Inbound',            'route' => 'inbounds.index',            'match' => 'inbounds.*',            'icon' => 'inb',      'roles' => ['PPIC']],
        ['label' => 'Material Out',       'route' => 'material_outs.index',       'match' => 'material_outs.*',       'icon' => 'out',      'roles' => ['PPIC']],
        ['label' => 'Material Return',    'route' => 'material_returns.index',    'match' => 'material_returns.*',    'icon' => 'undo',     'roles' => ['PPIC']],
        ['label' => 'Production',         'route' => 'productions.index',         'match' => 'productions.*',         'icon' => 'factory',  'roles' => ['PPIC']],
        ['label' => 'Delivery',           'route' => 'deliveries.index',          'match' => 'deliveries.*',          'icon' => 'truck',    'roles' => ['PPIC']],
        ['label' => 'Return to Supplier', 'route' => 'return_to_suppliers.index', 'match' => 'return_to_suppliers.*', 'icon' => 'truck',    'flip' => true, 'roles' => ['PPIC']],
    ]],
    ['group' => 'Inventory', 'items' => [
        ['label' => 'Inventory Stock',    'route' => 'inventory_stocks.index',    'match' => 'inventory_stocks.*',    'icon' => 'box',      'roles' => ['PPIC']],
        ['label' => 'Master Item',        'route' => 'items.index',               'match' => 'items.*',               'icon' => 'list',     'roles' => ['PPIC']],
        ['label' => 'Lot Tracer',         'route' => 'lot_tracers.index',         'match' => 'lot_tracers.*',         'icon' => 'route',    'roles' => ['PPIC']],
        ['label' => 'Stock Adjustment',   'route' => 'stock_adjustments.index',   'match' => 'stock_adjustments.*',   'icon' => 'sliders',  'roles' => ['PPIC']],
    ]],
    ['group' => 'Quality & Delivery', 'items' => [
        ['label' => 'Form NG',            'route' => 'production_ngs.index',      'match' => 'production_ngs.*',      'icon' => 'alert',    'roles' => ['PPIC']],
        ['label' => 'Delay Delivery',     'route' => 'problem_delays.index',      'match' => 'problem_delays.*',      'icon' => 'clock',    'roles' => ['PPIC']],
    ]],
    ['group' => 'Master Data', 'items' => [
        ['label' => 'Companies',          'route' => 'companies.index',           'match' => 'companies.*',           'icon' => 'building', 'roles' => ['PPIC']],
        ['label' => 'Manufacture Process','route' => 'processes.index',           'match' => 'processes.*',           'icon' => 'gear',     'roles' => ['PPIC']],
        ['label' => 'BOM',                'route' => 'boms.index',                'match' => 'boms.*',                'icon' => 'layers',   'roles' => ['PPIC']],
    ]],
    ['group' => 'Pengaturan', 'items' => [
        ['label' => 'Manajemen User',     'route' => 'users.index',               'match' => 'users.*',               'icon' => 'user',     'roles' => ['PPIC']],
    ]],
];