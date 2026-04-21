<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LedgerTransaction;

class LedgerApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware('permission:ledger');
    }

    public function index(Request $request)
    {
        $query = LedgerTransaction::with(['entries.account'])->orderByDesc('date');

        if ($request->filled('account_id')) {
            $query->whereHas('entries', function ($q) use ($request) {
                $q->where('account_id', $request->account_id);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($sq) use ($q) {
                $sq->where('description', 'like', '%' . $q . '%')
                    ->orWhere('uuid', $q)
                    ->orWhere('reference_type', 'like', '%' . $q . '%');
            });
        }

        $perPage = (int) ($request->numOfData ?? 20);
        $datas = $query->paginate($perPage)->withQueryString();

        $formated = $datas->getCollection()->map(function ($tx) {
            return (object) [
                'id' => $tx->id,
                'uuid' => $tx->uuid,
                'date' => $tx->date,
                'description' => $tx->description,
                'reference_type' => $tx->reference_type,
                'reference_id' => $tx->reference_id,
                'entries' => $tx->entries->map(function ($e) {
                    return [
                        'id' => $e->id,
                        'account_id' => $e->account_id,
                        'account' => $e->account ? ['id' => $e->account->id, 'code' => $e->account->code, 'name' => $e->account->name] : null,
                        'amount' => (float) $e->amount,
                        'entry_type' => $e->entry_type,
                    ];
                })->toArray(),
            ];
        });

        return response()->json(regeneratePagination($formated, $datas->total(), $datas->perPage(), $datas->currentPage()));
    }

    public function show($id)
    {
        $tx = LedgerTransaction::with(['entries.account'])->findOrFail($id);
        return response()->json($tx);
    }
}
