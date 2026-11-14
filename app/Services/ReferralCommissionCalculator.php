<?php

namespace App\Services;

class ReferralCommissionCalculator
{
    public function calculate(array $items, float $targetAmount, callable $resolveCommission): array
    {
        $targetCents = max(0, (int) round($targetAmount * 100));
        $weights = [];

        foreach ($items as $item) {
            $weights[] = max(0, (int) round((float) ($item['net_amount'] ?? 0) * 100));
        }

        if (array_sum($weights) <= 0) {
            $weights = array_map(
                static fn ($item) => max(0, (int) round((float) ($item['total_amount'] ?? 0) * 100)),
                $items
            );
        }

        $weightTotal = array_sum($weights);
        $allocatedCents = array_fill(0, count($items), 0);

        if ($targetCents > 0 && $weightTotal > 0) {
            $remainders = [];
            $allocatedTotal = 0;

            foreach ($weights as $index => $weight) {
                $exactCents = $targetCents * $weight / $weightTotal;
                $allocatedCents[$index] = (int) floor($exactCents);
                $allocatedTotal += $allocatedCents[$index];
                $remainders[] = [
                    'index' => $index,
                    'remainder' => $exactCents - $allocatedCents[$index],
                ];
            }

            usort($remainders, static function ($left, $right) {
                return ($right['remainder'] <=> $left['remainder'])
                    ?: ($left['index'] <=> $right['index']);
            });

            for ($remaining = $targetCents - $allocatedTotal, $index = 0; $remaining > 0; $remaining--, $index++) {
                $allocatedCents[$remainders[$index % count($remainders)]['index']]++;
            }
        }

        $totalCommissionCents = 0;
        $categories = [];

        foreach ($items as $index => $item) {
            $resolved = $resolveCommission($item);
            $category = (string) ($resolved['category'] ?? '');
            $rate = max(0, (float) ($resolved['rate'] ?? 0));
            $amountCents = $allocatedCents[$index];
            $commissionCents = (int) round($amountCents * $rate / 100);

            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'rate' => $rate,
                    'amount' => 0,
                    'base_amount' => 0,
                    'items' => [],
                ];
            }

            $categories[$category]['amount'] += $commissionCents;
            $categories[$category]['base_amount'] += $amountCents;
            $categories[$category]['items'][] = [
                'item_id' => $item['id'] ?? null,
                'item_name' => $item['name'] ?? '',
                'amount' => round($amountCents / 100, 2),
                'commission' => round($commissionCents / 100, 2),
            ];
            $totalCommissionCents += $commissionCents;
        }

        foreach ($categories as &$category) {
            $category['amount'] = round($category['amount'] / 100, 2);
            $category['base_amount'] = round($category['base_amount'] / 100, 2);
        }
        unset($category);

        return [
            'total' => round($totalCommissionCents / 100, 2),
            'categories' => $categories,
        ];
    }
}
