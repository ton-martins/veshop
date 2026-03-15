<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractorContextController extends Controller
{
    /**
     * Switch current contractor context for the authenticated user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
        ]);

        $user = $request->user();
        $contractorId = (int) $validated['contractor_id'];

        abort_unless($user, 403);
        abort_if($user->isMaster(), 403, 'Troca de contratante não permitida para usuário master.');

        $allowed = $user->contractors()
            ->where('contractors.id', $contractorId)
            ->exists();

        abort_unless($allowed, 403, 'Contratante não permitido para este usuário.');

        $request->session()->put('current_contractor_id', $contractorId);

        return redirect()
            ->route('admin.home')
            ->with('status', 'Contratante alterado com sucesso.');
    }
}
