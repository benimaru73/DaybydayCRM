<?php

namespace App\Http\Controllers;

use App\Models\Reduction;
use Illuminate\Http\Request;

class ReductionController extends Controller
{
    public function store(Request $request)
    {
        $reduction = new Reduction();
        $reduction->taux = $request->taux;
        $reduction->save();
        return response()->json(['message' => 'Réduction mise à jour avec succès', 'data' => $reduction]);
    }



    public function update(Request $request)
    {
        $reduction = Reduction::find($request->id);

        if (!$reduction) {
            return response()->json(['message' => 'Réduction non trouvée'], 404);
        }

        $reduction->taux = $request->taux;
        $reduction->save();

        return response()->json(['message' => 'Réduction mise à jour avec succès', 'data' => $reduction]);
    }


    public function getByReductionJson()
    {
        $reduction = Reduction::latest()->first();

        return response()->json($reduction);
    }


}
