<?php

namespace SchoolAid\Zuma\Requests\Concerns;

use InvalidArgumentException;

trait ValidatesInstallments
{
    /**
     * Validate the optional `payments` (installments / "cuotas") field.
     *
     * Omitted, null, empty string, 0, or 1 all mean a single payment (contado)
     * and are accepted unchanged. Any other value must be one of
     * self::ALLOWED_INSTALLMENTS (inherited from BaseRequest).
     *
     * @throws InvalidArgumentException when `payments` is present but unsupported.
     */
    protected function validateInstallments(): void
    {
        if (!array_key_exists('payments', $this->data)) {
            return;
        }

        $payments = $this->data['payments'];

        if ($payments === null || $payments === '') {
            return;
        }

        if (!is_numeric($payments) || (int) $payments != $payments) {
            throw new InvalidArgumentException(
                "Invalid installments value: 'payments' must be an integer."
            );
        }

        $payments = (int) $payments;

        // 0 and 1 are single payment (contado).
        if ($payments === 0 || $payments === 1) {
            return;
        }

        if (!in_array($payments, self::ALLOWED_INSTALLMENTS, true)) {
            $allowed = implode(', ', self::ALLOWED_INSTALLMENTS);

            throw new InvalidArgumentException(
                "Invalid installments value {$payments}: allowed values are 1 (single payment), {$allowed}."
            );
        }
    }
}
