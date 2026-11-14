<?php

namespace App\Services;

class BillingLedgerService
{
    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function resolveIncomeAccountCode(?string $category): string
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $category)));

        if (in_array($normalized, ['opd', 'appointment'], true)) {
            return 'OPD_INC';
        }

        if ($normalized === 'ipd') {
            return 'IPD_INC';
        }

        if ($normalized === 'pathology') {
            return 'PATH_INC';
        }

        if (in_array($normalized, ['radiology', 'ultrasound', 'xray', 'ecg', 'ultrasonogram', 'ultrasonography'], true)) {
            return 'RAD_INC';
        }

        if (in_array($normalized, ['medicine', 'pharmacy'], true)) {
            return 'PHARMACY_SALES';
        }

        return 'DIAG_INC';
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, float>
     */
    public function buildIncomeBreakdown(array $items): array
    {
        $breakdown = [];

        foreach ($items as $item) {
            $code = $this->resolveIncomeAccountCode($item['category'] ?? '');
            $amount = round((float) ($item['net_amount'] ?? $item['total_amount'] ?? 0), 2);

            if ($amount <= 0) {
                continue;
            }

            $breakdown[$code] = round(($breakdown[$code] ?? 0) + $amount, 2);
        }

        return $breakdown;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function recordNewBill(
        array $items,
        float $paidAmount,
        float $dueAmount,
        ?string $payMode,
        string $billLabel,
        $date,
        int $billingId,
        ?int $createdBy
    ): void {
        $paidAmount = round(max(0, $paidAmount), 2);
        $dueAmount = round(max(0, $dueAmount), 2);

        if ($paidAmount <= 0 && $dueAmount <= 0) {
            return;
        }

        $breakdown = $this->buildIncomeBreakdown($items);
        if (empty($breakdown)) {
            $breakdown = ['DIAG_INC' => round($paidAmount + $dueAmount, 2)];
        }

        $this->reconcileIncomeBreakdown($breakdown, round($paidAmount + $dueAmount, 2));

        $lines = [];

        if ($paidAmount > 0) {
            $lines[] = [
                'account_code' => $this->ledgerService->resolvePaymentAccountCode($payMode),
                'entry_type' => 'debit',
                'amount' => $paidAmount,
            ];
        }

        if ($dueAmount > 0) {
            $lines[] = [
                'account_code' => 'AR',
                'entry_type' => 'debit',
                'amount' => $dueAmount,
            ];
        }

        foreach ($breakdown as $code => $amount) {
            if ($amount <= 0) {
                continue;
            }

            $lines[] = [
                'account_code' => $code,
                'entry_type' => 'credit',
                'amount' => $amount,
            ];
        }

        if (count($lines) < 2) {
            return;
        }

        $this->ledgerService->recordTransaction(
            $lines,
            'Billing for ' . $billLabel,
            $date,
            'Billing',
            $billingId,
            $createdBy
        );
    }

    /**
     * @param  array<string, float>  $breakdown
     */
    private function reconcileIncomeBreakdown(array &$breakdown, float $targetTotal): void
    {
        $creditTotal = round(array_sum($breakdown), 2);
        $diff = round($targetTotal - $creditTotal, 2);

        if (abs($diff) <= 0.009) {
            return;
        }

        $firstKey = array_key_first($breakdown);
        if ($firstKey === null) {
            $breakdown['DIAG_INC'] = $targetTotal;

            return;
        }

        $breakdown[$firstKey] = round(($breakdown[$firstKey] ?? 0) + $diff, 2);
    }
}
