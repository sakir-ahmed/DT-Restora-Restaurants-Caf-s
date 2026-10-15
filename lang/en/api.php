<?php

declare(strict_types=1);

return [
    'unauthenticated' => 'Authentication required.',
    'too_many_attempts' => 'Too many attempts. Please wait before trying again.',
    'otp_sent' => 'Verification code sent.',
    'otp_invalid' => 'The verification code is wrong or has expired.',
    'otp_verified' => 'Verification code accepted.',
    'otp_rate_limited' => 'Too many code requests. Try again later.',
    'server_error' => 'Something went wrong.',
    'validation_failed' => 'The request is not valid.',
    'idempotency_required' => 'Send an Idempotency-Key header.',
    'forbidden' => 'You do not have permission for this action.',
    'google_not_configured' => 'Google sign-in is not configured.',
];
