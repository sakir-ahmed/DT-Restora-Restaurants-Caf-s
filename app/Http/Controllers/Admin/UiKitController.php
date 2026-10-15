<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UiKitController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/UiKit', [
            'demoRows' => [
                ['id' => 1, 'invoice' => 'BR01-T02-20260101-0001', 'customer' => 'Rahim Uddin', 'items' => 3, 'total' => '1250.00', 'status' => 'paid', 'created_at' => '2026-01-01T10:30:00+06:00'],
                ['id' => 2, 'invoice' => 'BR01-T02-20260101-0002', 'customer' => 'Ayesha Siddiqua', 'items' => 5, 'total' => '2340.50', 'status' => 'pending', 'created_at' => '2026-01-01T11:05:00+06:00'],
                ['id' => 3, 'invoice' => 'BR01-T02-20260101-0003', 'customer' => 'Karim Traders', 'items' => 1, 'total' => '490.00', 'status' => 'void', 'created_at' => '2026-01-01T12:45:00+06:00'],
                ['id' => 4, 'invoice' => 'BR01-T02-20260101-0004', 'customer' => 'Nusrat Jahan', 'items' => 8, 'total' => '5675.25', 'status' => 'paid', 'created_at' => '2026-01-01T13:15:00+06:00'],
            ],
        ]);
    }
}
