<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * Staff-only channels. The branch prefix keeps every kitchen, table and
 * alert channel isolated per branch; owners and support can listen to
 * any branch.
 */
Broadcast::channel('branch.{branchId}.kitchen', function (User $user, int $branchId): bool {
    return $user->canAccessBranch($branchId);
});

Broadcast::channel('branch.{branchId}.tables', function (User $user, int $branchId): bool {
    return $user->canAccessBranch($branchId);
});

Broadcast::channel('branch.{branchId}.alerts', function (User $user, int $branchId): bool {
    return $user->canAccessBranch($branchId);
});

Broadcast::channel('branch.{branchId}.delivery', function (User $user, int $branchId): bool {
    return $user->canAccessBranch($branchId);
});

Broadcast::channel('owner.alerts', function (User $user): bool {
    return $user->hasRole('owner', 'super-admin');
});

Broadcast::channel('customer.{customerId}', fn (User $user, int $customerId): bool => false);

Broadcast::channel('order.{orderId}.tracking', fn (User $user, int $orderId): bool => false);

Broadcast::channel('rider.{riderId}.deliveries', function (User $user, int $riderId): bool {
    return $user->id === $riderId;
});
